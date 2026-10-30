<?php
/**
 * Minimal structured logger for the WebSocket service (stdout/stderr -> docker logs).
 * Never pass tickets, cookies, message bodies, or notification text to this logger.
 */

namespace App\Realtime;

class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40, 'none' => 100];

    private int $threshold;

    /** @var resource|null */
    private $out;

    /** @var resource|null */
    private $err;

    public function __construct(string $level = 'info', $out = null, $err = null)
    {
        $this->threshold = self::LEVELS[$level] ?? self::LEVELS['info'];
        $this->out = $out ?? (defined('STDOUT') ? STDOUT : null);
        $this->err = $err ?? (defined('STDERR') ? STDERR : null);
    }

    public static function silent(): self
    {
        return new self('none');
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (self::LEVELS[$level] < $this->threshold) {
            return;
        }

        $line = sprintf(
            "%s [%s] %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        $stream = in_array($level, ['warning', 'error'], true) ? $this->err : $this->out;
        if ($stream) {
            fwrite($stream, $line);
        }
    }
}
