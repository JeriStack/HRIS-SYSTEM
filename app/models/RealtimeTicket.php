<?php
/**
 * Real-time WebSocket Ticket Model (HTTP application side)
 * Employee HRIS & Leave Management System
 *
 * Tickets are 256-bit random values, single-use, and expire after WS_TICKET_TTL
 * seconds. Only their SHA-256 hash is stored. All time math uses MySQL NOW()
 * so the HTTP app and the WebSocket service share one clock.
 */

namespace App\Models;

use Core\Model;

class RealtimeTicket extends Model
{
    /**
     * Issue a ticket and return the raw value (shown to the browser exactly once).
     */
    public function issue(int $userId, string $sessionRef, int $sessionExpiresAt, int $ttlSeconds, ?string $ipAddress): string
    {
        $raw = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare(
            "INSERT INTO realtime_tickets
                (token_hash, user_id, session_ref, session_expires_at, expires_at, ip_address)
             VALUES
                (:hash, :user_id, :session_ref, FROM_UNIXTIME(:session_expires_at), NOW() + INTERVAL :ttl SECOND, :ip)"
        );
        $stmt->bindValue(':hash', hash('sha256', $raw));
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':session_ref', $sessionRef);
        $stmt->bindValue(':session_expires_at', $sessionExpiresAt, \PDO::PARAM_INT);
        $stmt->bindValue(':ttl', $ttlSeconds, \PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ipAddress !== null ? substr($ipAddress, 0, 45) : null);
        $stmt->execute();

        return $raw;
    }

    /**
     * Number of tickets issued for a browser session in the last N seconds.
     */
    public function countRecentForSession(string $sessionRef, int $windowSeconds): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM realtime_tickets
              WHERE session_ref = :ref AND created_at >= (NOW() - INTERVAL :secs SECOND)"
        );
        $stmt->bindValue(':ref', $sessionRef);
        $stmt->bindValue(':secs', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Revoke every ticket of a browser session. The WebSocket service closes
     * matching live connections on its next revalidation sweep.
     */
    public function revokeSession(string $sessionRef): int
    {
        $stmt = $this->db->prepare(
            "UPDATE realtime_tickets SET revoked_at = NOW()
              WHERE session_ref = :ref AND revoked_at IS NULL"
        );
        $stmt->execute(['ref' => $sessionRef]);
        return $stmt->rowCount();
    }
}
