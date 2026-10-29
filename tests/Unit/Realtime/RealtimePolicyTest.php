<?php

namespace Tests\Unit\Realtime;

use App\Realtime\ChannelPolicy;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\IpRange;
use App\Realtime\NotificationPayload;
use App\Realtime\OriginPolicy;
use App\Realtime\RealtimeConfig;
use App\Realtime\TokenBucket;
use PHPUnit\Framework\TestCase;

class RealtimePolicyTest extends TestCase
{
    private function identity(int $userId, string $role): Identity
    {
        return new Identity($userId, $role, $userId * 10, str_repeat('a', 32), time() + 600);
    }

    public function test_origin_policy_requires_exact_scheme_host_and_port(): void
    {
        $policy = new OriginPolicy(['http://localhost:8080', 'https://hris.example.com:443']);

        $this->assertTrue($policy->isAllowed('http://localhost:8080'));
        $this->assertTrue($policy->isAllowed('HTTP://LOCALHOST:8080'));
        $this->assertTrue($policy->isAllowed('https://hris.example.com'));

        $this->assertFalse($policy->isAllowed(null));
        $this->assertFalse($policy->isAllowed(''));
        $this->assertFalse($policy->isAllowed('null'));
        $this->assertFalse($policy->isAllowed('http://localhost:8081'));
        $this->assertFalse($policy->isAllowed('https://localhost:8080'));
        $this->assertFalse($policy->isAllowed('http://localhost.evil.test:8080'));
        $this->assertFalse($policy->isAllowed('http://hris.example.com'));
        $this->assertFalse($policy->isAllowed('file://localhost'));
    }

    public function test_empty_origin_allow_list_fails_closed(): void
    {
        $this->assertFalse((new OriginPolicy([]))->isAllowed('http://localhost:8080'));
    }

    public function test_channel_policy_derives_channels_from_identity_only(): void
    {
        $policy = new ChannelPolicy();
        $hr = $this->identity(2, 'hr');
        $emp = $this->identity(7, 'employee');

        $this->assertSame(['user.2', 'role.hr'], $policy->channelsFor($hr));
        $this->assertSame(['user.7', 'role.employee'], $policy->channelsFor($emp));

        $this->assertTrue($policy->canSubscribe($emp, 'user.7'));
        $this->assertFalse($policy->canSubscribe($emp, 'user.8'));
        $this->assertFalse($policy->canSubscribe($emp, 'role.hr'));
        $this->assertFalse($policy->canSubscribe($emp, 'user.07'));
        $this->assertFalse($policy->canSubscribe($emp, "user.7\n"));
        $this->assertFalse($policy->canSubscribe($hr, 'user.7'));
        $this->assertFalse($policy->canSubscribe($hr, 'role.employee'));
    }

    public function test_event_authorizer_matrix(): void
    {
        $auth = new EventAuthorizer();
        $hr = $this->identity(2, 'hr');
        $emp = $this->identity(7, 'employee');

        $row = static fn (int $uid, string $role, string $event, int $owner = 0, string $status = 'Active') => [
            'user_id' => $uid, 'event_type' => $event, 'recipient_role' => $role,
            'recipient_status' => $status, 'owner_matches' => $owner,
        ];

        $this->assertTrue($auth->canDeliver($hr, $row(2, 'hr', EventAuthorizer::LEAVE_CREATED)));
        $this->assertFalse($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::LEAVE_CREATED)), 'Employees never get HR queue events.');
        $this->assertFalse($auth->canDeliver($hr, $row(3, 'hr', EventAuthorizer::LEAVE_CREATED)), 'Rows are private to their owner.');

        $this->assertTrue($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::LEAVE_APPROVED, 1)));
        $this->assertTrue($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::LEAVE_REJECTED, 1)));
        $this->assertTrue($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::PROFILE_UPDATED)));
        $this->assertFalse($auth->canDeliver($emp, $row(8, 'employee', EventAuthorizer::PROFILE_UPDATED)), 'Profile event belongs to matching user only.');
        $this->assertFalse($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::LEAVE_APPROVED, 0)), 'Leave must belong to recipient.');
        $this->assertFalse($auth->canDeliver($emp, $row(8, 'employee', EventAuthorizer::LEAVE_APPROVED, 1)));

        $this->assertFalse($auth->canDeliver($emp, $row(7, 'employee', EventAuthorizer::LEAVE_APPROVED, 1, 'Inactive')));
        $this->assertFalse($auth->canDeliver($emp, $row(7, 'hr', EventAuthorizer::LEAVE_APPROVED, 1)), 'Role changed since connect.');
        $this->assertFalse($auth->canDeliver($emp, $row(7, 'employee', 'unknown.event', 1)));
    }

    public function test_payload_excludes_sensitive_fields(): void
    {
        $payload = NotificationPayload::fromRow([
            'id' => 15, 'event_type' => EventAuthorizer::LEAVE_APPROVED, 'entity_id' => 42, 'created_ts' => 1790000000,
            'title' => 'Sick leave for medical procedure', 'message' => 'Reason: surgery', 'user_id' => 7,
        ]);

        $json = json_encode($payload);
        $this->assertStringNotContainsString('medical', $json);
        $this->assertStringNotContainsString('surgery', $json);
        $this->assertStringNotContainsString('user_id', $json);
        $this->assertSame('Leave request approved', $payload['data']['title']);
        $this->assertSame('Your leave request #LR-042 was approved.', $payload['data']['message']);

        $profilePayload = NotificationPayload::fromRow([
            'id' => 16, 'event_type' => EventAuthorizer::PROFILE_UPDATED, 'entity_id' => 5, 'created_ts' => 1790000000,
            'title' => 'Personal details changed', 'message' => 'New address: Secret Road', 'user_id' => 7,
        ]);
        $this->assertSame('Profile updated', $profilePayload['data']['title']);
        $this->assertSame('Your employee profile record was updated by Human Resources.', $profilePayload['data']['message']);
        $this->assertSame('employee/profile', $profilePayload['data']['link']);

        $this->assertNull(NotificationPayload::fromRow(['id' => 1, 'event_type' => null]));
    }

    public function test_ip_range_matching(): void
    {
        $this->assertTrue(IpRange::matches('172.18.0.5', '172.16.0.0/12'));
        $this->assertFalse(IpRange::matches('172.32.0.1', '172.16.0.0/12'));
        $this->assertTrue(IpRange::matches('127.0.0.1', '127.0.0.1'));
        $this->assertTrue(IpRange::matches('::1', '::1'));
        $this->assertFalse(IpRange::matches('203.0.113.9', '10.0.0.0/8'));
        $this->assertFalse(IpRange::matches('not-an-ip', '10.0.0.0/8'));
        $this->assertFalse(IpRange::matches('10.0.0.1', '10.0.0.0/99'));
    }

    public function test_token_bucket_refills_over_time(): void
    {
        $bucket = new TokenBucket(2, 1.0, 100.0);
        $this->assertTrue($bucket->allow(100.0));
        $this->assertTrue($bucket->allow(100.0));
        $this->assertFalse($bucket->allow(100.0));
        $this->assertTrue($bucket->allow(101.0));
    }

    public function test_config_defaults_and_derived_urls(): void
    {
        $config = RealtimeConfig::fromEnvironment(['BASE_URL' => 'https://hris.example.com']);
        $this->assertSame(['https://hris.example.com'], $config->allowedOrigins);
        $this->assertSame('wss://hris.example.com/ws', $config->browserUrl('https://hris.example.com'));
        $this->assertSame('ws://localhost:8080/ws', $config->browserUrl('http://localhost:8080'));
        $this->assertSame(8090, $config->listenPort);
        $this->assertSame(30, $config->ticketTtl);
        $this->assertTrue($config->enabled);

        $custom = RealtimeConfig::fromEnvironment([
            'WS_PUBLIC_URL'      => 'ws://localhost:8090/ws',
            'WS_ALLOWED_ORIGINS' => 'http://localhost, bogus, http://localhost:80',
            'WS_ENABLED'         => 'false',
            'WS_TICKET_TTL'      => '9999',
        ]);
        $this->assertSame('ws://localhost:8090/ws', $custom->browserUrl('http://ignored'));
        $this->assertSame(['http://localhost'], $custom->allowedOrigins);
        $this->assertFalse($custom->enabled);
        $this->assertSame(120, $custom->ticketTtl, 'TTL is clamped to a short maximum.');
    }
}
