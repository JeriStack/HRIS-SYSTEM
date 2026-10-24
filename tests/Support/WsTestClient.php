<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Minimal blocking RFC 6455 client for end-to-end tests (no extra dependency).
 * Supports text frames, ping/pong, and close frames; client frames are masked.
 */
class WsTestClient
{
    /** @var resource */
    private $stream;
    private string $buffer = '';

    public int $handshakeStatus = 0;
    public ?int $closeCode = null;
    public ?string $closeReason = null;
    public bool $closed = false;

    private function __construct()
    {
    }

    public static function connect(string $url, ?string $origin, float $timeout = 5.0): self
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['ws'], true)) {
            throw new RuntimeException('Only ws:// URLs are supported by the test client.');
        }
        $host = $parts['host'];
        $port = $parts['port'] ?? 80;
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $client = new self();
        $stream = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (!$stream) {
            throw new RuntimeException("Cannot connect to {$host}:{$port}");
        }
        $client->stream = $stream;

        $key = base64_encode(random_bytes(16));
        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
        ];
        if ($origin !== null) {
            $headers[] = "Origin: {$origin}";
        }
        fwrite($stream, implode("\r\n", $headers) . "\r\n\r\n");

        $deadline = microtime(true) + $timeout;
        while (!str_contains($client->buffer, "\r\n\r\n")) {
            if (!$client->fill($deadline)) {
                throw new RuntimeException('Handshake timed out.');
            }
        }

        [$head, $rest] = explode("\r\n\r\n", $client->buffer, 2);
        $client->buffer = $rest;
        $client->handshakeStatus = (int)substr($head, 9, 3);

        if ($client->handshakeStatus === 101) {
            $expected = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            if (stripos($head, "Sec-WebSocket-Accept: {$expected}") === false) {
                throw new RuntimeException('Invalid Sec-WebSocket-Accept.');
            }
        } else {
            $client->closed = true;
            fclose($stream);
        }

        return $client;
    }

    public function sendJson(array $data): void
    {
        $this->sendFrame(json_encode($data), 0x1);
    }

    public function sendText(string $text): void
    {
        $this->sendFrame($text, 0x1);
    }

    /**
     * Next JSON text message, or null on timeout/close.
     */
    public function receive(float $timeout = 3.0): ?array
    {
        $deadline = microtime(true) + $timeout;
        while (!$this->closed) {
            $frame = $this->readFrame($deadline);
            if ($frame === null) {
                return null;
            }
            [$opcode, $payload] = $frame;
            if ($opcode === 0x1) {
                $decoded = json_decode($payload, true);
                return is_array($decoded) ? $decoded : ['_raw' => $payload];
            }
            if ($opcode === 0x9) {
                $this->sendFrame($payload, 0xA);
            } elseif ($opcode === 0x8) {
                $this->closeCode = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : 1005;
                $this->closeReason = substr($payload, 2);
                $this->closed = true;
                @fclose($this->stream);
                return null;
            }
        }
        return null;
    }

    /**
     * Wait for the first message matching $predicate; returns null on timeout.
     */
    public function receiveUntil(callable $predicate, float $timeout = 5.0): ?array
    {
        $deadline = microtime(true) + $timeout;
        while (($remaining = $deadline - microtime(true)) > 0) {
            $msg = $this->receive($remaining);
            if ($msg === null) {
                return null;
            }
            if ($predicate($msg)) {
                return $msg;
            }
        }
        return null;
    }

    /**
     * Collect all messages for a period.
     *
     * @return array<int, array>
     */
    public function collect(float $seconds): array
    {
        $out = [];
        $deadline = microtime(true) + $seconds;
        while (($remaining = $deadline - microtime(true)) > 0 && !$this->closed) {
            $msg = $this->receive($remaining);
            if ($msg !== null) {
                $out[] = $msg;
            }
        }
        return $out;
    }

    public function waitForClose(float $timeout): ?int
    {
        $deadline = microtime(true) + $timeout;
        while (!$this->closed && microtime(true) < $deadline) {
            $this->receive($deadline - microtime(true));
        }
        return $this->closeCode;
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->sendFrame(pack('n', 1000), 0x8);
            $this->closed = true;
            @fclose($this->stream);
        }
    }

    private function sendFrame(string $payload, int $opcode): void
    {
        if ($this->closed) {
            return;
        }
        $len = strlen($payload);
        $frame = chr(0x80 | $opcode);
        if ($len < 126) {
            $frame .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }
        $mask = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }
        @fwrite($this->stream, $frame . $mask . $masked);
    }

    /**
     * @return array{0:int,1:string}|null
     */
    private function readFrame(float $deadline): ?array
    {
        if (!$this->need(2, $deadline)) {
            return null;
        }
        $b1 = ord($this->buffer[0]);
        $b2 = ord($this->buffer[1]);
        $opcode = $b1 & 0x0F;
        $len = $b2 & 0x7F;
        $offset = 2;

        if ($len === 126) {
            if (!$this->need(4, $deadline)) {
                return null;
            }
            $len = unpack('n', substr($this->buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($len === 127) {
            if (!$this->need(10, $deadline)) {
                return null;
            }
            $len = unpack('J', substr($this->buffer, 2, 8))[1];
            $offset = 10;
        }

        if (!$this->need($offset + $len, $deadline)) {
            return null;
        }
        $payload = substr($this->buffer, $offset, $len);
        $this->buffer = substr($this->buffer, $offset + $len);

        return [$opcode, $payload];
    }

    private function need(int $bytes, float $deadline): bool
    {
        while (strlen($this->buffer) < $bytes) {
            if (!$this->fill($deadline)) {
                return false;
            }
        }
        return true;
    }

    private function fill(float $deadline): bool
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0 || $this->closed) {
            return false;
        }
        $read = [$this->stream];
        $write = $except = null;
        $sec = (int)floor($remaining);
        $usec = (int)(($remaining - $sec) * 1e6);
        if (@stream_select($read, $write, $except, $sec, $usec) < 1) {
            return false;
        }
        $chunk = @fread($this->stream, 65536);
        if ($chunk === '' || $chunk === false) {
            if (feof($this->stream)) {
                $this->closed = true;
                $this->closeCode ??= 1006;
            }
            return false;
        }
        $this->buffer .= $chunk;
        return true;
    }
}
