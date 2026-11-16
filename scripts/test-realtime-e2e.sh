#!/bin/sh
# Run the live WebSocket end-to-end tests inside the app container.
#
# phpunit.xml forces DB_DATABASE=hris_test so tests can never touch hris_db.
# The always-on `realtime` service reads hris_db, so it cannot see the tickets
# the tests create. This script starts a throwaway realtime server on port 8091
# that uses the same isolated test database, runs the tests, then stops it.
#
# Usage (from the repository root):  make test-realtime
set -u

PORT="${WS_E2E_PORT:-8091}"
ORIGIN="${WS_E2E_ORIGIN:-http://localhost:8080}"
LOG="/tmp/ws-e2e-server.log"

DB_DATABASE=hris_test WS_PORT="$PORT" WS_ALLOWED_ORIGINS="$ORIGIN" \
    php bin/realtime-server.php >"$LOG" 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null' EXIT INT TERM

sleep 3
if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo "Realtime test server failed to start (is port $PORT already in use?):" >&2
    cat "$LOG" >&2
    exit 1
fi

WS_E2E_URL="ws://127.0.0.1:$PORT/ws" WS_E2E_ORIGIN="$ORIGIN" \
    php vendor/bin/phpunit --group realtime-e2e
STATUS=$?

if [ "$STATUS" -ne 0 ]; then
    echo "--- realtime test server log (last 20 lines) ---" >&2
    tail -n 20 "$LOG" >&2
fi
exit "$STATUS"
