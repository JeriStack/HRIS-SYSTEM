<?php

/**
 * Authoritative Queue Job Registry
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Enforces a strict whitelist of permitted asynchronous job handlers.
 * Prevents execution of arbitrary or unsanctioned code from job payloads.
 */

declare(strict_types=1);

namespace App\Queue;

use App\Queue\Handlers\LeaveReminderHandler;
use App\Queue\Handlers\OutboxRelayHandler;
use App\Queue\Handlers\ReportExportHandler;
use App\Queue\Handlers\SendEmailHandler;
use App\Queue\Handlers\SendNotificationHandler;
use InvalidArgumentException;

class JobRegistry
{
    public const TYPE_SEND_EMAIL         = 'send_email';
    public const TYPE_SEND_NOTIFICATION  = 'send_notification';
    public const TYPE_LEAVE_REMINDER     = 'leave_reminder';
    public const TYPE_REPORT_EXPORT      = 'report_export';
    public const TYPE_OUTBOX_RELAY       = 'outbox_relay';

    /**
     * Whitelist mapping job types to concrete JobInterface handler class names.
     *
     * @var array<string, class-string<JobInterface>>
     */
    private static array $handlers = [
        self::TYPE_SEND_EMAIL        => SendEmailHandler::class,
        self::TYPE_SEND_NOTIFICATION => SendNotificationHandler::class,
        self::TYPE_LEAVE_REMINDER    => LeaveReminderHandler::class,
        self::TYPE_REPORT_EXPORT     => ReportExportHandler::class,
        self::TYPE_OUTBOX_RELAY      => OutboxRelayHandler::class,
    ];

    /**
     * Check if a given job type is registered and allowed.
     */
    public static function isRegistered(string $jobType): bool
    {
        return array_key_exists(trim($jobType), self::$handlers);
    }

    /**
     * Resolve a job handler instance for the given job type.
     *
     * @throws InvalidArgumentException When the job type is unknown or invalid
     */
    public static function resolve(string $jobType): JobInterface
    {
        $normalizedType = trim($jobType);

        if (!self::isRegistered($normalizedType)) {
            throw new InvalidArgumentException(sprintf(
                "Unregistered job type '%s'. Job execution rejected by security policy.",
                $normalizedType
            ));
        }

        $className = self::$handlers[$normalizedType];
        $instance = new $className();

        if (!$instance instanceof JobInterface) {
            throw new InvalidArgumentException(sprintf(
                "Job handler '%s' must implement %s.",
                $className,
                JobInterface::class
            ));
        }

        return $instance;
    }

    /**
     * Register or override a handler (useful for testing or extensions).
     *
     * @param class-string<JobInterface> $handlerClass
     */
    public static function register(string $jobType, string $handlerClass): void
    {
        if (!is_subclass_of($handlerClass, JobInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                "Class '%s' must implement %s.",
                $handlerClass,
                JobInterface::class
            ));
        }

        self::$handlers[trim($jobType)] = $handlerClass;
    }

    /**
     * Get all currently registered job types.
     *
     * @return list<string>
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$handlers);
    }
}
