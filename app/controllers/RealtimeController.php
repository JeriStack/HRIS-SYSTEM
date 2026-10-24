<?php
/**
 * Real-time Connection Ticket Controller
 * Employee HRIS & Leave Management System
 *
 * Route: POST /realtime/ticket  [AuthMiddleware, CsrfMiddleware]
 * Returns a single-use ticket (valid WS_TICKET_TTL seconds) that the browser
 * sends as the first WebSocket message. Session cookies, passwords and OTPs
 * never reach the WebSocket service or its URLs.
 */

namespace App\Controllers;

use App\Services\RealtimeTicketService;
use Core\Controller;

class RealtimeController extends Controller
{
    public function ticket(): void
    {
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');

        $result = (new RealtimeTicketService())->issueForCurrentSession();

        if (!$result['ok']) {
            $this->json([
                'status'  => 'error',
                'code'    => $result['status'],
                'message' => $result['error'],
            ], $result['status']);
            return;
        }

        $this->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => [
                'ticket'     => $result['ticket'],
                'expires_in' => $result['expires_in'],
                'url'        => $result['url'],
            ],
        ]);
    }
}
