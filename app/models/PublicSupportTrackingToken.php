<?php

/**
 * Public Support Tracking Token Model
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Manages CSPRNG selector + HMAC-SHA256 hashed verifier tracking credentials
 * so unauthenticated public login-support requesters can safely view redacted
 * status and public HR replies for their AUTH-YYYY-NNNNN case without exposing
 * sequential ticket IDs or guessable references.
 */

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PublicSupportTrackingToken extends Model
{
    /**
     * Store a new secret tracking token hash for a public support ticket.
     */
    public function createToken(int $ticketId, string $selector, string $tokenHash, int $ttlDays = 30): int
    {
        $safeDays = max(1, min(90, $ttlDays));

        $this->execute(
            "INSERT INTO public_support_tracking_tokens
                (ticket_id, selector, token_hash, expires_at)
             VALUES
                (:ticket_id, :selector, :token_hash, DATE_ADD(NOW(), INTERVAL {$safeDays} DAY))",
            [
                'ticket_id'  => $ticketId,
                'selector'   => $selector,
                'token_hash' => $tokenHash,
            ]
        );

        return (int)$this->lastInsertId();
    }

    /**
     * Look up an active tracking record together with ticket metadata by hex selector.
     */
    public function findBySelector(string $selector): ?array
    {
        return $this->fetchOne(
            "SELECT
                pstt.*,
                t.ticket_ref,
                t.is_public_ticket,
                t.support_channel,
                (pstt.expires_at <= NOW()) AS is_expired
             FROM public_support_tracking_tokens pstt
             INNER JOIN support_tickets t ON pstt.ticket_id = t.id
             WHERE pstt.selector = :selector
             LIMIT 1",
            ['selector' => $selector]
        );
    }

    /**
     * Revoke any active tracking tokens for a ticket (e.g. if reissued).
     */
    public function revokeForTicket(int $ticketId): int
    {
        $stmt = $this->query(
            "UPDATE public_support_tracking_tokens
             SET revoked_at = NOW()
             WHERE ticket_id = :ticket_id
               AND revoked_at IS NULL",
            ['ticket_id' => $ticketId]
        );

        return $stmt->rowCount();
    }

    /**
     * Record last viewed timestamp for audit telemetry.
     */
    public function touchLastViewed(int $tokenId): void
    {
        $this->execute(
            "UPDATE public_support_tracking_tokens SET last_viewed_at = NOW() WHERE id = :id",
            ['id' => $tokenId]
        );
    }
}
