<?php
/**
 * WebSocket Channel Authorization Policy
 * Employee HRIS & Leave Management System
 *
 * A connection may only ever hold the channels derived from its verified
 * identity: its own private user channel and its own role channel.
 */

namespace App\Realtime;

final class ChannelPolicy
{
    public const ROLE_HR = 'role.hr';
    public const ROLE_EMPLOYEE = 'role.employee';

    public static function userChannel(int $userId): string
    {
        return 'user.' . $userId;
    }

    /**
     * @return string[]
     */
    public function channelsFor(Identity $identity): array
    {
        $channels = [self::userChannel($identity->userId)];

        if ($identity->isHr()) {
            $channels[] = self::ROLE_HR;
        } elseif ($identity->isEmployee()) {
            $channels[] = self::ROLE_EMPLOYEE;
        }

        return $channels;
    }

    public function canSubscribe(Identity $identity, string $channel): bool
    {
        // Strict format first: user.<digits> or role.<hr|employee>
        if (!preg_match('/^(user\.[1-9][0-9]{0,9}|role\.(hr|employee))$/D', $channel)) {
            return false;
        }

        return in_array($channel, $this->channelsFor($identity), true);
    }
}
