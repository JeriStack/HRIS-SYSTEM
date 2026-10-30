<?php
/**
 * Real-time HTTP + WebSocket end-to-end check (TEST HARNESS, CLI ONLY)
 *
 * Drives the real Apache controllers through Nginx and asserts that:
 *   1. An employee leave submission (POST /leaves/apply) pushes
 *      leave_request.created to the HR socket only.
 *   2. HR approval (POST /leaves/{id}/approve) pushes leave_request.approved
 *      to the owning employee only.
 *   3. Logout (POST /logout) revokes the employee socket (close 4003) and
 *      further tickets are refused (401).
 *
 * Because email OTP codes cannot be read automatically, this harness writes
 * post-OTP session files directly (the same payload AuthController::verifyOtp
 * stores). It must run as the web server user inside the app container:
 *
 *   docker compose exec -u www-data app php tests/E2E/realtime_http_flow.php
 *
 * All rows it creates (leave request, notifications, 2099 balances, tickets)
 * are removed afterwards. Audit log entries written by the controllers are
 * intentionally kept.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

define('HRIS_SKIP_SESSION', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/bootstrap.php';
error_reporting(E_ALL & ~E_DEPRECATED);

// CLI writes session files only; no cookies/headers are involved
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');

use App\Models\User;
use Core\Database;
use Tests\Support\WsTestClient;

$httpBase = rtrim(getenv('E2E_HTTP_BASE') ?: 'http://nginx', '/');
$wsUrl = getenv('E2E_WS_URL') ?: 'ws://nginx/ws';
$origin = getenv('E2E_ORIGIN') ?: 'http://localhost:8080';

$pdo = Database::getInstance()->getConnection();
$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($detail !== '' && !$ok ? " ({$detail})" : '') . "\n";
}

function forgeSession(array $user): array
{
    $sid = bin2hex(random_bytes(16));
    $csrf = bin2hex(random_bytes(32));
    session_id($sid);
    session_start();
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION = [
        'user' => [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'role'        => strtolower($user['role']),
            'employee_id' => $user['employee_id'] ? (int)$user['employee_id'] : null,
            'employee_no' => $user['employee_no'] ?? null,
            'name'        => $fullName !== '' ? $fullName : $user['username'],
            'email'       => $user['email'] ?? null,
            'department'  => $user['department_name'] ?? 'Unassigned',
            'position'    => $user['position_name'] ?? 'Unassigned',
            'login_time'  => time(),
        ],
        'last_activity' => time(),
        '_csrf_token'   => $csrf,
    ];
    session_write_close();
    $_SESSION = [];
    return ['sid' => $sid, 'csrf' => $csrf];
}

function destroySession(string $sid): void
{
    session_id($sid);
    @session_start();
    @session_destroy();
}

function http(string $method, string $url, string $sid, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge(["Cookie: PHPSESSID={$sid}"], $headers),
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response];
}

function ticket(string $httpBase, array $session): array
{
    $res = http('POST', "{$httpBase}/realtime/ticket", $session['sid'], [
        'Accept: application/json',
        'Content-Type: application/json',
        "X-CSRF-Token: {$session['csrf']}",
        'X-HRIS-Background: 1',
    ], '{}');
    $json = json_decode($res['body'], true);
    return ['code' => $res['code'], 'ticket' => $json['data']['ticket'] ?? null];
}

function connectWs(string $wsUrl, string $origin, string $ticket): WsTestClient
{
    $client = WsTestClient::connect($wsUrl, $origin);
    $client->sendJson(['type' => 'auth', 'ticket' => $ticket]);
    $client->receiveUntil(fn ($m) => str_starts_with($m['type'] ?? '', 'auth.'), 5);
    return $client;
}

echo "====================================================================\n";
echo " Real-time HTTP + WebSocket End-to-End Check\n";
echo "====================================================================\n";

// ---------------------------------------------------------------- fixtures
$hrRow = $pdo->query("SELECT id FROM users WHERE role='hr' AND status='Active' ORDER BY id LIMIT 1")->fetch();
$empRow = $pdo->query("SELECT id, employee_id FROM users WHERE role='employee' AND status='Active' AND employee_id IS NOT NULL ORDER BY id LIMIT 1")->fetch();
$leaveType = $pdo->query("SELECT id FROM leave_types WHERE status='Active' AND default_days >= 2 ORDER BY id LIMIT 1")->fetchColumn();
if (!$hrRow || !$empRow || !$leaveType) {
    echo "Missing seed data (HR user, employee user, active leave type).\n";
    exit(1);
}

$users = new User();
$hrUser = $users->findById((int)$hrRow['id']);
$empUser = $users->findById((int)$empRow['id']);
$employeeId = (int)$empRow['employee_id'];

$start = new DateTime('2099-06-01');
while ($start->format('N') !== '1') {
    $start->modify('+1 day');
}
$end = (clone $start)->modify('+1 day');
$hadBalances2099 = (int)$pdo->query("SELECT COUNT(*) FROM leave_balances WHERE employee_id = {$employeeId} AND year = 2099")->fetchColumn() > 0;

$hr = forgeSession($hrUser);
$emp = forgeSession($empUser);
$leaveId = null;
$hrWs = $empWs = null;
$hrTicket = $empTicket = null;

try {
    // ---------------------------------------------------------- 1. tickets
    echo "\n--- 1. Ticket endpoint ---\n";
    $page = http('GET', "{$httpBase}/employee/dashboard", $emp['sid'], ['Accept: text/html']);
    check('Employee dashboard renders (200)', $page['code'] === 200, "HTTP {$page['code']}");
    check('Dashboard contains the real-time indicator', str_contains($page['body'], 'id="rtNotify"') && str_contains($page['body'], 'js/realtime.js'));
    $hrPage = http('GET', "{$httpBase}/hr/dashboard", $hr['sid'], ['Accept: text/html']);
    check('HR dashboard renders with indicator', $hrPage['code'] === 200 && str_contains($hrPage['body'], 'id="rtNotify"'), "HTTP {$hrPage['code']}");

    $noCsrf = http('POST', "{$httpBase}/realtime/ticket", $emp['sid'], ['Accept: application/json'], '{}');
    check('Ticket without CSRF token is refused (403)', $noCsrf['code'] === 403, "HTTP {$noCsrf['code']}");
    $anon = http('POST', "{$httpBase}/realtime/ticket", 'nosuchsession' . bin2hex(random_bytes(4)), ['Accept: application/json'], '{}');
    check('Ticket without session is refused (401)', $anon['code'] === 401, "HTTP {$anon['code']}");

    $hrTicket = ticket($httpBase, $hr);
    $empTicket = ticket($httpBase, $emp);
    check('HR obtains a ticket', $hrTicket['code'] === 200 && is_string($hrTicket['ticket']));
    check('Employee obtains a ticket', $empTicket['code'] === 200 && is_string($empTicket['ticket']));

    // ---------------------------------------------------------- 2. sockets
    echo "\n--- 2. WebSocket connections ---\n";
    $hrWs = connectWs($wsUrl, $origin, $hrTicket['ticket']);
    $empWs = connectWs($wsUrl, $origin, $empTicket['ticket']);
    check('HR socket authenticated', !$hrWs->closed);
    check('Employee socket authenticated', !$empWs->closed);
    $hrWs->collect(0.5);
    $empWs->collect(0.5);

    // ---------------------------------------------------------- 3. submit
    echo "\n--- 3. Employee submits leave (POST /leaves/apply) ---\n";
    $before = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM leave_requests")->fetchColumn();
    $apply = http('POST', "{$httpBase}/leaves/apply", $emp['sid'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        '_csrf_token'   => $emp['csrf'],
        'leave_type_id' => $leaveType,
        'start_date'    => $start->format('Y-m-d'),
        'end_date'      => $end->format('Y-m-d'),
        'reason'        => 'Automated realtime HTTP E2E check',
    ]));
    $leaveId = (int)$pdo->query("SELECT id FROM leave_requests WHERE id > {$before} AND employee_id = {$employeeId} ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
    check('Leave submission accepted (302) and persisted', $apply['code'] === 302 && $leaveId !== null, "HTTP {$apply['code']}");

    $ref = $leaveId ? 'LR-' . str_pad((string)$leaveId, 3, '0', STR_PAD_LEFT) : '';
    $created = $hrWs->receiveUntil(fn ($m) => ($m['event'] ?? '') === 'leave_request.created' && ($m['data']['reference'] ?? '') === $ref, 6);
    check('HR receives leave_request.created in real time', $created !== null);
    check('Event payload carries no reason/leave type', $created !== null && !str_contains(json_encode($created), 'Automated realtime'));
    $empEvents = array_filter($empWs->collect(2), fn ($m) => ($m['type'] ?? '') === 'event');
    check('Employee does not receive the HR event', $empEvents === []);

    // ---------------------------------------------------------- 4. approve
    echo "\n--- 4. HR approves (POST /leaves/{id}/approve) ---\n";
    $forbidden = http('POST', "{$httpBase}/leaves/{$leaveId}/approve", $emp['sid'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf_token' => $emp['csrf']]));
    check('Employee cannot approve (403)', $forbidden['code'] === 403, "HTTP {$forbidden['code']}");
    $approve = http('POST', "{$httpBase}/leaves/{$leaveId}/approve", $hr['sid'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf_token' => $hr['csrf']]));
    check('HR approval accepted (302)', $approve['code'] === 302, "HTTP {$approve['code']}");

    $approved = $empWs->receiveUntil(fn ($m) => ($m['event'] ?? '') === 'leave_request.approved' && ($m['data']['reference'] ?? '') === $ref, 6);
    check('Owner receives leave_request.approved in real time', $approved !== null);
    $count = $empWs->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'notification.unread_count', 3);
    $dbCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . (int)$empRow['id'] . " AND is_read = 0")->fetchColumn();
    check('Owner unread count matches database', $count !== null && $count['data']['count'] === $dbCount);
    $hrEvents = array_filter($hrWs->collect(2), fn ($m) => ($m['event'] ?? '') === 'leave_request.approved');
    check('HR does not receive the employee decision event', $hrEvents === []);

    // ---------------------------------------------------------- 5. logout
    echo "\n--- 5. Employee logs out (POST /logout) ---\n";
    $logout = http('POST', "{$httpBase}/logout", $emp['sid'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf_token' => $emp['csrf']]));
    check('Logout accepted (302)', $logout['code'] === 302, "HTTP {$logout['code']}");
    check('Employee socket closed with 4003 after logout', $empWs->waitForClose(25) === 4003, 'close=' . var_export($empWs->closeCode, true));
    $after = ticket($httpBase, $emp);
    check('No ticket after logout (401)', $after['code'] === 401, "HTTP {$after['code']}");
    check('HR socket unaffected', !$hrWs->closed);
} finally {
    $hrWs?->close();
    $empWs?->close();
    if ($leaveId) {
        $pdo->exec("DELETE FROM notifications WHERE entity_id = {$leaveId} AND event_type LIKE 'leave\\_request.%'");
        $pdo->exec("DELETE FROM leave_requests WHERE id = {$leaveId}");
    }
    if (!$hadBalances2099) {
        $pdo->exec("DELETE FROM leave_balances WHERE employee_id = {$employeeId} AND year = 2099");
    }
    // Remove only this run's tickets (by the session refs they belong to)
    foreach ([$hrTicket['ticket'] ?? null, $empTicket['ticket'] ?? null] as $raw) {
        if (is_string($raw)) {
            $stmt = $pdo->prepare("SELECT session_ref FROM realtime_tickets WHERE token_hash = ?");
            $stmt->execute([hash('sha256', $raw)]);
            $sessionRef = $stmt->fetchColumn();
            if (is_string($sessionRef)) {
                $pdo->prepare("DELETE FROM realtime_tickets WHERE session_ref = ?")->execute([$sessionRef]);
            }
        }
    }
    destroySession($hr['sid']);
    destroySession($emp['sid']);
}

echo "\n====================================================================\n";
echo " Result: {$passed} passed, {$failed} failed\n";
echo "====================================================================\n";
exit($failed === 0 ? 0 : 1);
