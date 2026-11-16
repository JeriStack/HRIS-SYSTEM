<?php

/**
 * HrSupportBaseController
 * Shared dependency wiring, authorization error responses, safe attachment streaming,
 * and workflow exception mapping for HR-to-Admin Help & Support controllers.
 */

namespace App\Controllers;

use App\Models\HrSupportTicket;
use App\Services\HrSupportAccessService;
use App\Services\HrSupportWorkflowService;
use Core\Controller;
use Core\Database;
use Core\Session;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

abstract class HrSupportBaseController extends Controller
{
    protected PDO $pdo;
    protected HrSupportTicket $tickets;
    protected HrSupportAccessService $access;
    protected HrSupportWorkflowService $workflow;
    protected bool $terminateOnRedirect;

    /** @var array{url: string, status: int}|null */
    protected ?array $lastRedirect = null;

    public function __construct(
        ?PDO $pdo = null,
        ?HrSupportWorkflowService $workflow = null,
        bool $terminateOnRedirect = true
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->tickets = new HrSupportTicket($this->pdo);
        $this->access = new HrSupportAccessService();
        $this->workflow = $workflow ?? new HrSupportWorkflowService($this->pdo, $this->tickets, $this->access);
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * Expose last redirect target when redirect termination is disabled in integration tests.
     *
     * @return array{url: string, status: int}|null
     */
    public function getLastRedirect(): ?array
    {
        return $this->lastRedirect;
    }

    protected function forbid(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 403);
            return;
        }

        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Access Restricted',
            'message'   => $message,
        ], null);
    }

    protected function notFound(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 404);
            return;
        }

        $this->view('errors.404', [
            'pageTitle' => '404 Not Found',
            'message'   => $message,
        ], null);
    }

    protected function navigate(string $path, int $statusCode = 302): void
    {
        $this->lastRedirect = ['url' => $path, 'status' => $statusCode];
        if ($this->terminateOnRedirect) {
            $this->redirect($path, $statusCode);
        }
    }

    protected function handleWorkflowFailure(
        Throwable $e,
        string $fallbackRedirect,
        bool $flashOldInput = false
    ): void {
        $code = (int)$e->getCode();
        if ($code === 404) {
            $this->notFound($e->getMessage());
            return;
        }
        if ($code === 403) {
            $this->forbid($e->getMessage());
            return;
        }

        $httpCode = in_array($code, [400, 409, 422, 429], true)
            ? $code
            : ($e instanceof InvalidArgumentException ? 422 : 400);

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], $httpCode);
            return;
        }

        if ($flashOldInput) {
            Session::flash('old_input', $_POST);
        }
        Session::flash('error', $e->getMessage());
        $this->navigate($fallbackRedirect);
    }

    protected function streamAttachmentDownload(?array $user, int $attachmentId, string $surface): void
    {
        if (!$user || $attachmentId <= 0) {
            $this->forbid('Authentication and valid attachment identifier required.');
            return;
        }

        try {
            $resolved = $this->workflow->authorizeAttachmentDownload($user, $attachmentId, $surface);
        } catch (RuntimeException | InvalidArgumentException $e) {
            if ((int)$e->getCode() === 404) {
                $this->notFound($e->getMessage());
                return;
            }
            $this->forbid($e->getMessage());
            return;
        }

        $attachment = $resolved['attachment'];
        $filePath = $resolved['file_path'];
        $safeName = str_replace(['"', "\r", "\n"], '', (string)($attachment['original_filename'] ?? 'attachment'));
        $mimeType = (string)($attachment['mime_type'] ?? 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string)filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
        }

        readfile($filePath);
    }
}
