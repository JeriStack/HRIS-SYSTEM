<?php
/**
 * MySQL-backed store for the long-running WebSocket service.
 * Employee HRIS & Leave Management System
 *
 * Long-lived PHP processes lose MySQL connections (wait_timeout, restarts).
 * Every operation retries once on a fresh connection.
 */

namespace App\Realtime;

use App\Realtime\Contracts\RealtimeStore;
use PDO;
use PDOException;
use RuntimeException;

final class PdoRealtimeStore implements RealtimeStore
{
    private ?PDO $pdo = null;

    /** @var callable(): PDO */
    private $connectionFactory;

    /**
     * @param callable(): PDO $connectionFactory
     */
    public function __construct(callable $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    /**
     * Factory using the same DB_* environment variables as Core\Database.
     */
    public static function fromEnvironment(): self
    {
        return new self(static function (): PDO {
            $config = require __DIR__ . '/../../config/database.php';
            $driver   = getenv('DB_DRIVER')   ?: ($config['driver'] ?? 'mysql');
            $host     = getenv('DB_HOST')     ?: ($config['host'] ?? '127.0.0.1');
            $port     = getenv('DB_PORT')     ?: ($config['port'] ?? 3306);
            $database = getenv('DB_DATABASE') ?: ($config['database'] ?? 'hris_db');
            $username = getenv('DB_USERNAME') ?: ($config['username'] ?? 'root');
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? '');
            $charset  = getenv('DB_CHARSET')  ?: ($config['charset'] ?? 'utf8mb4');

            return new PDO(
                "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]
            );
        });
    }

    public function consumeTicket(string $tokenHash): ?Identity
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $tokenHash)) {
            return null;
        }

        return $this->run(function (PDO $pdo) use ($tokenHash): ?Identity {
            // Single-use: the conditional UPDATE succeeds for exactly one caller
            $stmt = $pdo->prepare(
                "UPDATE realtime_tickets
                    SET used_at = NOW()
                  WHERE token_hash = :hash
                    AND used_at IS NULL
                    AND revoked_at IS NULL
                    AND expires_at > NOW()
                    AND session_expires_at > NOW()"
            );
            $stmt->execute(['hash' => $tokenHash]);
            if ($stmt->rowCount() !== 1) {
                return null;
            }

            $stmt = $pdo->prepare(
                "SELECT t.user_id, t.session_ref, UNIX_TIMESTAMP(t.session_expires_at) AS session_expires_ts,
                        u.role, u.status, u.employee_id
                   FROM realtime_tickets t
                   INNER JOIN users u ON u.id = t.user_id
                  WHERE t.token_hash = :hash
                  LIMIT 1"
            );
            $stmt->execute(['hash' => $tokenHash]);
            $row = $stmt->fetch();

            if (!$row || $row['status'] !== 'Active') {
                return null;
            }

            $role = strtolower((string)$row['role']);
            if (!in_array($role, ['admin', 'hr', 'employee'], true)) {
                return null;
            }

            return new Identity(
                userId: (int)$row['user_id'],
                role: $role,
                employeeId: $row['employee_id'] !== null ? (int)$row['employee_id'] : null,
                sessionRef: (string)$row['session_ref'],
                sessionExpiresAt: (int)$row['session_expires_ts'],
            );
        });
    }

    public function findInvalidSessions(array $sessions): array
    {
        if ($sessions === []) {
            return [];
        }

        return $this->run(function (PDO $pdo) use ($sessions): array {
            $refs = array_values(array_unique(array_map(static fn ($s) => (string)$s['session_ref'], $sessions)));
            $userIds = array_values(array_unique(array_map(static fn ($s) => (int)$s['user_id'], $sessions)));

            // Session revocation state (logout revokes every ticket of the session)
            $refState = [];
            foreach (array_chunk($refs, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $pdo->prepare(
                    "SELECT session_ref, MAX(revoked_at IS NOT NULL) AS revoked
                       FROM realtime_tickets
                      WHERE session_ref IN ({$placeholders})
                      GROUP BY session_ref"
                );
                $stmt->execute($chunk);
                foreach ($stmt->fetchAll() as $row) {
                    $refState[(string)$row['session_ref']] = (int)$row['revoked'] === 1;
                }
            }

            // Account state
            $userState = [];
            foreach (array_chunk($userIds, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE id IN ({$placeholders})");
                $stmt->execute($chunk);
                foreach ($stmt->fetchAll() as $row) {
                    $userState[(int)$row['id']] = $row;
                }
            }

            $invalid = [];
            foreach ($sessions as $connectionId => $session) {
                $ref = (string)$session['session_ref'];
                $user = $userState[(int)$session['user_id']] ?? null;

                if (!array_key_exists($ref, $refState) || $refState[$ref] === true) {
                    $invalid[$connectionId] = 'revoked';
                } elseif ($user === null || $user['status'] !== 'Active') {
                    $invalid[$connectionId] = 'inactive';
                } elseif (strtolower((string)$user['role']) !== $session['role']) {
                    $invalid[$connectionId] = 'role_changed';
                }
            }

            return $invalid;
        });
    }

    public function unreadCount(int $userId): int
    {
        return $this->run(function (PDO $pdo) use ($userId): int {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
            $stmt->execute(['uid' => $userId]);
            return (int)$stmt->fetchColumn();
        });
    }

    public function latestNotificationId(): int
    {
        return $this->run(fn (PDO $pdo): int => (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM notifications")->fetchColumn());
    }

    public function fetchNotificationsAfter(int $afterId, int $limit): array
    {
        $limit = max(1, min(1000, $limit));

        return $this->run(function (PDO $pdo) use ($afterId, $limit): array {
            // Only committed rows are visible to this separate connection, so
            // events can never be emitted for rolled-back transactions.
            $stmt = $pdo->prepare(
                "SELECT n.id, n.user_id, n.type, n.event_type, n.entity_id,
                        UNIX_TIMESTAMP(n.created_at) AS created_ts,
                        u.role AS recipient_role, u.status AS recipient_status,
                        CASE WHEN lr.id IS NOT NULL AND u.employee_id IS NOT NULL
                                  AND lr.employee_id = u.employee_id THEN 1 ELSE 0 END AS owner_matches,
                        CASE WHEN st.id IS NOT NULL AND u.employee_id IS NOT NULL
                                  AND st.employee_id = u.employee_id THEN 1 ELSE 0 END AS support_owner_matches,
                        CASE WHEN st.id IS NOT NULL AND st.subject_employee_id IS NOT NULL
                                  AND u.employee_id IS NOT NULL
                                  AND st.subject_employee_id = u.employee_id THEN 1 ELSE 0 END AS support_coi_blocked,
                        CASE WHEN st.id IS NOT NULL AND st.is_confidential = 1 THEN
                            CASE WHEN sth.id IS NOT NULL AND sth.is_active = 1 AND sth.can_handle_confidential = 1 THEN 1
                                 WHEN st.escalated_flag = 1 AND LOWER(u.role) = 'admin' THEN 1
                                 ELSE 0 END
                        ELSE 1 END AS support_confidential_authorized,
                        hst.ticket_ref AS hr_support_ref,
                        CASE WHEN hst.id IS NOT NULL AND hst.submitted_by_user_id = u.id THEN 1 ELSE 0 END AS hr_support_owner_matches,
                        CASE WHEN ann.id IS NOT NULL AND ann.status = 'Published'
                                  AND (ann.expires_at IS NULL OR ann.expires_at > :now_exp)
                                  AND emp.id IS NOT NULL AND emp.employment_status = 'Active'
                                  AND EXISTS (
                                      SELECT 1 FROM announcement_targets annt
                                      WHERE annt.announcement_id = ann.id
                                        AND (annt.target_type = 'all' OR (annt.target_type = 'department' AND annt.department_id = emp.department_id))
                                  ) THEN 1 ELSE 0 END AS announcement_targeted
                   FROM notifications n
                   INNER JOIN users u ON u.id = n.user_id
                   LEFT JOIN employees emp ON emp.id = u.employee_id
                   LEFT JOIN leave_requests lr ON lr.id = n.entity_id
                   LEFT JOIN support_tickets st ON st.id = n.entity_id AND n.event_type LIKE 'support_%'
                   LEFT JOIN support_ticket_handlers sth ON sth.user_id = u.id
                   LEFT JOIN hr_support_tickets hst ON hst.id = n.entity_id AND n.event_type LIKE 'hr_support.%'
                   LEFT JOIN announcements ann ON ann.id = n.entity_id AND n.event_type LIKE 'announcement.%'
                  WHERE n.id > :after
                  ORDER BY n.id ASC
                  LIMIT {$limit}"
            );
            $stmt->execute([
                'after'   => $afterId,
                'now_exp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            return $stmt->fetchAll();
        });
    }

    public function purgeTickets(int $olderThanSeconds): int
    {
        return $this->run(function (PDO $pdo) use ($olderThanSeconds): int {
            $stmt = $pdo->prepare("DELETE FROM realtime_tickets WHERE created_at < (NOW() - INTERVAL :secs SECOND)");
            $stmt->bindValue('secs', max(60, $olderThanSeconds), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount();
        });
    }

    public function assertReady(): void
    {
        $this->run(function (PDO $pdo): void {
            $pdo->query("SELECT 1 FROM realtime_tickets LIMIT 1");
            $pdo->query("SELECT event_type, entity_id FROM notifications LIMIT 1");
        });
    }

    /**
     * @template T
     * @param callable(PDO): T $operation
     * @return T
     */
    private function run(callable $operation): mixed
    {
        try {
            return $operation($this->connection());
        } catch (PDOException $first) {
            // Retry once on a fresh connection (server gone away, restarts, timeouts)
            $this->pdo = null;
            try {
                return $operation($this->connection());
            } catch (PDOException $second) {
                $this->pdo = null;
                throw new RuntimeException('Realtime store unavailable: ' . $second->getCode(), 0, $second);
            }
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = ($this->connectionFactory)();
        }
        return $this->pdo;
    }
}
