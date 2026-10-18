<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\MailService;

class MailServiceTest extends TestCase
{
    private array $testConfig;

    protected function setUp(): void
    {
        $this->testConfig = [
            'driver'     => 'smtp',
            'host'       => 'smtp.gmail.com',
            'port'       => 587,
            'encryption' => 'tls',
            'username'   => 'test-sender@example.com',
            'password'   => 'test-app-password',
            'from'       => [
                'address' => 'no-reply@hris.local',
                'name'    => 'HRIS Security',
            ],
            'timeout'    => 5,
            'debug'      => 0,
        ];
    }

    public function test_mail_service_mock_mode_captures_outbound_emails(): void
    {
        $mailService = new MailService($this->testConfig, true);
        $this->assertTrue($mailService->isMockMode());

        $sent = $mailService->sendOtpEmail(
            'employee@company.local',
            'David Cruz',
            '482910',
            5
        );

        $this->assertTrue($sent);
        $sentMails = $mailService->getSentMails();
        $this->assertCount(1, $sentMails);

        $mail = $sentMails[0];
        $this->assertSame('employee@company.local', $mail['to_address']);
        $this->assertSame('David Cruz', $mail['to_name']);
        $this->assertStringContainsString('482910', $mail['subject']);
        $this->assertStringContainsString('482910', $mail['html_body']);
        $this->assertStringContainsString('5 minutes', $mail['html_body']);
        $this->assertStringContainsString('Never share this code', $mail['html_body']);
        $this->assertStringContainsString('482910', $mail['alt_body']);
    }

    public function test_send_mail_in_mock_mode_accumulates_and_clears_records(): void
    {
        $mailService = new MailService($this->testConfig, true);

        $mailService->sendMail('user1@company.local', 'User One', 'Test 1', '<p>Body 1</p>');
        $mailService->sendMail('user2@company.local', 'User Two', 'Test 2', '<p>Body 2</p>');

        $this->assertCount(2, $mailService->getSentMails());

        $mailService->clearSentMails();
        $this->assertCount(0, $mailService->getSentMails());
    }

    public function test_plain_text_template_contains_all_security_warnings(): void
    {
        $mailService = new MailService($this->testConfig, true);
        $mailService->sendOtpEmail('hr01@company.local', 'Marcus Tan', '918234', 5);

        $sentMails = $mailService->getSentMails();
        $mail = $sentMails[0];

        $this->assertStringContainsString('918234', $mail['alt_body']);
        $this->assertStringContainsString('SECURITY ADVISORY', $mail['alt_body']);
        $this->assertStringContainsString('Never share this verification code', $mail['alt_body']);
        $this->assertStringContainsString('5 minutes', $mail['alt_body']);
    }

    public function test_sensitive_passwords_are_never_exposed_in_error_logs(): void
    {
        // Force an invalid SMTP connection in non-mock mode to verify safe error masking
        $failingConfig = array_merge($this->testConfig, [
            'host'     => '127.0.0.1',
            'port'     => 19899, // Unreachable port
            'password' => 'SuperSecretPassword999!',
            'timeout'  => 1,
        ]);

        $tempLog = tempnam(sys_get_temp_dir(), 'mail_err');
        $oldLog = ini_get('error_log');
        ini_set('error_log', $tempLog);

        try {
            $mailService = new MailService($failingConfig, false);
            $result = $mailService->sendMail('test@test.local', 'Test', 'Subject', 'Body');

            $this->assertFalse($result, 'Connection to closed port must fail gracefully.');
            $lastError = (string)$mailService->getLastError();
            $this->assertStringNotContainsString('SuperSecretPassword999!', $lastError, 'SMTP password must never be exposed in error logs.');
            
            // Also assert the masked password is NOT in the error_log output file
            if (file_exists($tempLog)) {
                $loggedContent = file_get_contents($tempLog);
                $this->assertStringNotContainsString('SuperSecretPassword999!', $loggedContent, 'SMTP password must not be written to error log file.');
            }
        } finally {
            if ($oldLog !== false) {
                ini_set('error_log', $oldLog);
            }
            if (file_exists($tempLog)) {
                @unlink($tempLog);
            }
        }
    }
}
