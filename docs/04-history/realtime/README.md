# Real-time Notifications (Secure WebSockets)

Live HR/Employee notifications for leave workflow events, delivered by a dedicated Ratchet WebSocket service. MySQL remains the source of truth; the socket is only a delivery channel.

## Architecture and data flow

```
Browser ──HTTPS/HTTP──> Nginx ──> Apache/PHP (app)  ── writes ──> MySQL
   │                      │                                         ▲
   └──── ws(s)://…/ws ────┴──> realtime (Ratchet, PHP CLI) ── reads ┘
```

1. A user signs in with email + password, then email OTP (unchanged). Only then does the session hold the `user` payload.
2. The page script calls `POST /realtime/ticket` (session cookie + CSRF header). `RealtimeTicketService` re-checks the account in `users` (Active, role unchanged) and stores a single-use ticket hash in `realtime_tickets` (TTL 30 s, bound to the session's inactivity deadline).
3. The browser opens `ws://host/ws` and sends `{"type":"auth","ticket":"…"}` as its first message. The WebSocket service consumes the ticket with a conditional `UPDATE … WHERE used_at IS NULL AND expires_at > NOW()` and builds the identity (user id, role) from the `users` table.
4. Leave actions run in the HTTP app. The notification rows are written in the same database transaction as the leave change (`Leave::createRequest()` / `Leave::updateRequestStatus()` callbacks + `LeaveNotificationService`).
5. The WebSocket service polls `notifications` (transactional outbox) on its own connection, so it only sees committed rows. Each row is re-authorized (`EventAuthorizer`) and pushed with a generic payload, followed by the user's unread count.
6. After every (re)connect the browser re-syncs history from `GET /api/v1/notifications`, so nothing is lost while disconnected.

Pipeline inside the service: `IoServer → HttpServer → HandshakeGuard → WsServer → RealtimeHub`.

## Events

| Event | Recipients | Payload `data` |
|---|---|---|
| `leave_request.created` | Each active HR user (own row) | generic title/message, `reference` (`LR-005`), `link`, `level`, `created_at` |
| `leave_request.approved` | Only the employee who owns the request | same generic shape |
| `leave_request.rejected` | Only the owner | same generic shape (no rejection reason) |
| `notification.unread_count` | The authenticated user only | `{ "count": n }` |

Payloads never include names, leave types, dates, reasons or rejection notes. Details stay behind the authenticated HTTP pages.

Client → server messages: `auth`, `ping`, `subscribe`/`unsubscribe` (only `user.<own id>` and `role.<own role>`), `notification.unread_count`.

Close codes: `4001` session deadline (client re-tickets), `4002` role changed, `4003` logged out/deactivated (no reconnect), `4401` bad/expired/reused ticket, `4408` no auth within 5 s, `4429` per-user connection limit, `1008` policy/rate limit, `1009` message too large.

## Files

Created
- `app/realtime/` – `RealtimeConfig`, `OriginPolicy`, `IpRange`, `Identity`, `ChannelPolicy`, `EventAuthorizer`, `NotificationPayload`, `TokenBucket`, `CloseCodes`, `Logger`, `ClientState`, `RealtimeHub`, `HandshakeGuard`, `NotificationOutbox`, `PdoRealtimeStore`, `Server`, `Contracts/RealtimeStore`
- `app/models/RealtimeTicket.php`, `app/services/RealtimeTicketService.php`, `app/services/LeaveNotificationService.php`, `app/controllers/RealtimeController.php`
- `bin/realtime-server.php`, `bin/realtime-healthcheck.php`
- `database/migrations/2026_10_23_add_realtime_notifications.sql`
- `public/assets/js/realtime.js`, `public/assets/css/realtime.css`
- Tests: `tests/Unit/Realtime/*`, `tests/Integration/RealtimeDatabaseTest.php`, `tests/Feature/RealtimeEndToEndTest.php`, `tests/E2E/realtime_http_flow.php`, `tests/Support/*`

Changed
- `app/models/Leave.php` (transaction callbacks; concurrent-review guard), `app/models/Notification.php` (`event_type`, `entity_id`)
- `app/controllers/LeaveController.php`, `app/controllers/Api/LeaveController.php` (notifications inside the transaction)
- `app/controllers/AuthController.php`, `app/controllers/Api/AuthController.php` (revoke tickets on logout)
- `core/Session.php` (`X-HRIS-Background` does not extend inactivity; realtime ref rotated on timeout), `core/bootstrap.php`
- `public/index.php` (route), `app/views/partials/navbar.php`, `app/views/layouts/hr.php`
- `docker-compose.yml` (`realtime` service), `docker/nginx/default.conf` (`/ws` upgrade proxy)
- `composer.json` / `composer.lock` (`cboden/ratchet` 0.4.4), `database/schema.sql`, `.env.example`, `.env.docker.example`, `phpunit.xml`

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `WS_ENABLED` | `true` | Turn the feature off (ticket endpoint returns 503) |
| `WS_PORT` | `8090` | Listen port of the WebSocket process |
| `WS_PATH` | `/ws` | Upgrade path |
| `WS_PUBLIC_URL` | derived from `BASE_URL` | Browser URL, e.g. `wss://hris.example.com/ws` |
| `WS_ALLOWED_ORIGINS` | origin of `BASE_URL` | Comma-separated allow-list (`scheme://host[:port]`) |
| `WS_TICKET_TTL` | `30` | Ticket lifetime in seconds (5–120) |
| `WS_TICKETS_PER_MINUTE` | `10` | Ticket issuance limit per browser session |
| `WS_TRUSTED_PROXIES` | loopback + private ranges | Peers allowed to supply `X-Real-IP` |
| `WS_MAX_CONNECTIONS` / `_PER_USER` / `_PER_IP` | `1000` / `5` / `25` | Connection limits |
| `WS_MAX_MESSAGE_BYTES` | `4096` | Max client message |
| `WS_MAX_INBOUND_BYTES_PER_10S` | `65536` | Raw byte budget per connection |
| `WS_RATE_LIMIT_BURST` / `_PER_SECOND` | `20` / `2` | Message rate limit |
| `WS_AUTH_TIMEOUT`, `WS_POLL_INTERVAL`, `WS_REVALIDATE_INTERVAL`, `WS_KEEPALIVE_INTERVAL` | `5`, `1.0`, `15`, `30` | Timers (seconds) |
| `WS_LOG_LEVEL` | `info` | `debug`, `info`, `warning`, `error`, `none` |

No new secrets are required. The service uses the existing `DB_*` variables.

In Docker the endpoint values are fixed in `docker-compose.yml` next to `BASE_URL`, so a host `.env` written for XAMPP cannot point Docker browsers at an unpublished port. The internal port `8090` appears in `docker-compose.yml` and `docker/nginx/default.conf`; change both together.

### Local URLs
- Docker: `ws://localhost:8080/ws` (through Nginx; the realtime container is not published to the host).
- XAMPP (no proxy): run `php bin/realtime-server.php` and set `WS_PUBLIC_URL=ws://localhost:8090/ws` and `WS_ALLOWED_ORIGINS=http://localhost` in `.env`.

### Production behind HTTPS
- Terminate TLS at the reverse proxy and proxy `/ws` with `Upgrade`/`Connection` headers (see `docker/nginx/default.conf`).
- Set `WS_PUBLIC_URL=wss://<domain>/ws`, `WS_ALLOWED_ORIGINS=https://<domain>`, `BASE_URL=https://<domain>`.
- Restrict `WS_TRUSTED_PROXIES` to the proxy addresses. Never publish port 8090.
- The browser client upgrades `ws://` to `wss://` automatically on HTTPS pages.

## Docker commands

```powershell
docker compose exec app php bin/migrate.php        # once: adds columns + realtime_tickets
docker compose up -d --build realtime              # start / rebuild the service
docker compose up -d --no-deps --force-recreate nginx   # reload proxy config
docker compose ps realtime                         # status + health
docker compose logs -f realtime                    # logs (no tickets or message bodies are logged)
docker compose restart realtime
docker compose stop realtime                       # site keeps working; live updates pause
```

## Testing

```powershell
# Unit + integration (host or container)
php vendor/bin/phpunit
docker compose exec app php vendor/bin/phpunit

# Live socket tests through Nginx
docker compose exec -e WS_E2E_URL=ws://nginx/ws -e WS_E2E_ORIGIN=http://localhost:8080 app php vendor/bin/phpunit --group realtime-e2e

# Full HTTP + WebSocket flow (apply -> HR event, approve -> owner event, logout -> 4003)
docker compose exec -u www-data app php tests/E2E/realtime_http_flow.php
```

`tests/E2E/realtime_http_flow.php` writes post-OTP session files directly because OTP emails cannot be read automatically. It is CLI-only, removes the rows it creates, and keeps the audit-log entries the controllers write.

### Manual check (two browsers)
1. Browser A (or a private window): sign in as an HR user and open the HR dashboard. The bell's dot turns green and the panel shows "Live".
2. Browser B: sign in as an employee and submit a leave request.
3. Browser A shows a "New leave request" toast and the unread count increases without a refresh.
4. In Browser A approve the request; Browser B receives "Leave request approved".
5. Sign out in Browser B; its socket closes within about 15 s (revalidation interval).

Quick handshake probes:
```powershell
# Expect 403 (origin not allowed)
curl.exe -i -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" -H "Origin: http://evil.example" http://localhost:8080/ws
# Expect 401 (no session)
curl.exe -i -X POST -H "Accept: application/json" http://localhost:8080/realtime/ticket
```

## Security decisions

- **Identity**: derived only from the ticket row joined with `users`. Client-sent ids, emails and roles are ignored; channels come from that identity.
- **Credentials out of URLs**: the ticket is sent in the first frame, and handshakes with a query string are rejected. Session IDs, passwords and OTPs never reach the socket service.
- **OTP preserved**: tickets require the post-OTP session payload (`AuthMiddleware`) and a CSRF token. A pending-OTP session gets 401.
- **Tickets**: 256-bit random, SHA-256 hashed at rest, 30 s TTL, single use, bound to the session's inactivity deadline, rate limited per session.
- **Revocation**: logout revokes the session's tickets, and a 15 s sweep closes sockets for revoked sessions, deactivated accounts and role changes. Sockets also close at the session's inactivity deadline. Background ticket and history requests send `X-HRIS-Background: 1` so they don't keep an idle session alive.
- **Origin allow-list** with exact scheme/host/port; fail-closed when empty.
- **Limits**: global/per-IP/per-user connections, 5 s auth timeout, message size, byte budget, token-bucket rate limit, strikes for malformed messages, keep-alive pings.
- **Proxy awareness**: `X-Real-IP`/`X-Forwarded-For` are used only when the TCP peer is in `WS_TRUSTED_PROXIES`.
- **Commit-only delivery**: events come from committed rows. Rolled-back or rejected operations produce no event. A concurrent-review guard (`rowCount() === 1`) stops double approvals from producing duplicate notifications.
- **Safe errors and logs**: clients get short error codes. Logs hold connection ids, user ids, IPs and reasons only. The browser client never logs to the console and renders text with `textContent`.

## Known limitations and production requirements

- **Ratchet 0.4.4** is the latest stable release. It runs on PHP 8.5.12 (container) and 8.5.9 (host) but emits PHP 8.4+ deprecations (implicit nullable parameters, dynamic properties). The server suppresses `E_DEPRECATED`, and PHPUnit ignores indirect vendor deprecations. Re-evaluate before PHP 9 or when Ratchet 0.5 is released.
- **Single instance**: connection state lives in one process. Horizontal scaling needs sticky sessions plus a shared fan-out such as Redis pub/sub.
- **Polling outbox**: about 1 s latency. Blocking PDO calls run on the event loop, which is fine at HRIS scale but not for thousands of events per second. A row whose transaction commits more than 200 ids late is not pushed live; it still shows up after the next history sync.
- **Revocation latency**: up to `WS_REVALIDATE_INTERVAL` (15 s) after logout or deactivation.
- **Docker init scripts** (`docker/mysql/init/01-schema.sql`) are generated copies of `database/schema.sql`, so fresh volumes include the realtime columns and tables. Still run `php bin/migrate.php` once so the `migrations` table records every file (all migrations are idempotent).
- **Internal port** `8090` is duplicated in compose and Nginx; keep them in sync.
- Browser behaviour (reconnect UI, toasts) was checked by code review and the protocol-level tests. Run the two-browser manual check before release.
