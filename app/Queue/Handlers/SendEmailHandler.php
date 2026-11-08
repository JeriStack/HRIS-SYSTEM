<?php

/**
 * Send Email Queue Job Handler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Asynchronously sends emails using MailService.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Queue\JobInterface;
use App\Services\MailService;
use InvalidArgumentException;
use RuntimeException;

class SendEmailHandler implements JobInterface
{
    private MailService $mailService;

    public function __construct(?MailService $mailService = null)
    {
        $this->mailService = $mailService ?? new MailService();
    }

    public function handle(array $payload): void
    {
        $to = trim((string)($payload['to'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        $body = (string)($payload['body'] ?? '');
        $recipientName = trim((string)($payload['name'] ?? ''));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("SendEmailHandler: Missing or invalid 'to' email address.");
        }

        if ($subject === '') {
            throw new InvalidArgumentException("SendEmailHandler: Missing 'subject'.");
        }

        if ($body === '') {
            throw new InvalidArgumentException("SendEmailHandler: Missing 'body'.");
        }

        $sent = $this->mailService->sendMail($to, $recipientName, $subject, $body);

        if (!$sent) {
            $err = $this->mailService->getLastError() ?? 'Unknown SMTP failure';
            throw new RuntimeException("SendEmailHandler failed to deliver email: {$err}");
        }
    }
}
