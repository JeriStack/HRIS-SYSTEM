<?php

namespace Tests\Support;

use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\Frame;

/**
 * In-memory Ratchet connection double that records frames and close codes.
 */
#[\AllowDynamicProperties]
class FakeConnection implements ConnectionInterface
{
    /** @var string[] */
    public array $sent = [];
    public ?int $closeCode = null;
    public ?string $closeReason = null;
    public bool $closed = false;
    public string $remoteAddress;

    public function __construct(string $remoteAddress = '203.0.113.10')
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function send($data)
    {
        $this->sent[] = (string)$data;
        return $this;
    }

    public function close($data = null)
    {
        $this->closed = true;
        if ($data instanceof Frame) {
            $payload = $data->getPayload();
            $this->closeCode = unpack('n', substr($payload, 0, 2))[1];
            $this->closeReason = substr($payload, 2);
        } elseif (is_int($data)) {
            $this->closeCode = $data;
        }
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function messages(): array
    {
        $out = [];
        foreach ($this->sent as $raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }
        return $out;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function messagesOfType(string $type): array
    {
        return array_values(array_filter($this->messages(), static fn ($m) => ($m['type'] ?? null) === $type));
    }

    public function lastMessage(): ?array
    {
        $all = $this->messages();
        return $all ? end($all) : null;
    }

    public function clear(): void
    {
        $this->sent = [];
    }
}
