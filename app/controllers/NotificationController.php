<?php
/**
 * Notification Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use App\Models\Notification;
use Core\Controller;
use Core\Session;

class NotificationController extends Controller
{
    private const ALLOWED_REDIRECTS = [
        '/notifications',
        '/employee/dashboard',
    ];

    private Notification $notificationModel;

    public function __construct(?Notification $notificationModel = null)
    {
        $this->notificationModel = $notificationModel ?? new Notification();
    }

    /**
     * Allowlist internal return paths after state mutations to prevent open redirects.
     */
    private function resolveSafeRedirect(?string $candidate, string $default = '/notifications'): string
    {
        $target = trim((string)$candidate);
        if (in_array($target, self::ALLOWED_REDIRECTS, true)) {
            return $target;
        }
        return $default;
    }

    /**
     * Display current user's notifications inbox
     * Route: GET /notifications
     */
    public function index(): void
    {
        $user = currentUser();
        $notifications = $this->notificationModel->getByUser((int)$user['id'], 50);
        $unreadCount = $this->notificationModel->getUnreadCount((int)$user['id']);

        $role = strtolower((string)($user['role'] ?? ''));
        foreach ($notifications as &$notification) {
            $notification['link'] = \App\Realtime\NotificationPayload::linkFor(
                $notification['event_type'] ?? null,
                (int)($notification['entity_id'] ?? 0),
                $role
            );
        }
        unset($notification);

        $this->view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'currentUser'   => $user,
            'pageTitle'     => 'Notifications & Alerts'
        ], 'hr');
    }

    /**
     * Mark single notification as read
     * Route: POST /notifications/{id}/read
     */
    public function markRead(int $id): void
    {
        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        $redirectTarget = $this->resolveSafeRedirect($_POST['redirect_to'] ?? $_GET['redirect_to'] ?? null);

        if ($userId <= 0 || !$this->notificationModel->belongsToUser($id, $userId)) {
            if (request_wants_json()) {
                json_response([
                    'status'  => 'error',
                    'code'    => 404,
                    'message' => 'That notification could not be found.',
                ], 404);
                return;
            }
            Session::flash('error', 'That notification could not be found.');
            redirect($redirectTarget);
            return;
        }

        $this->notificationModel->markAsRead($id, $userId);
        $unreadCount = $this->notificationModel->getUnreadCount($userId);

        if (request_wants_json()) {
            json_response([
                'status'  => 'success',
                'code'    => 200,
                'message' => 'Notification marked as read.',
                'data'    => [
                    'notification_id' => $id,
                    'unread_count'    => $unreadCount,
                ],
            ], 200);
            return;
        }

        Session::flash('success', 'Notification marked as read.');
        redirect($redirectTarget);
    }

    /**
     * Mark all notifications as read
     * Route: POST /notifications/mark-all-read
     */
    public function markAllRead(): void
    {
        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        $redirectTarget = $this->resolveSafeRedirect($_POST['redirect_to'] ?? $_GET['redirect_to'] ?? null);

        if ($userId > 0) {
            $this->notificationModel->markAllAsRead($userId);
        }

        if (request_wants_json()) {
            json_response([
                'status'  => 'success',
                'code'    => 200,
                'message' => 'All notifications marked as read.',
                'data'    => ['unread_count' => 0],
            ], 200);
            return;
        }

        Session::flash('success', 'All notifications marked as read.');
        redirect($redirectTarget);
    }

    /**
     * Delete a single notification
     * Route: POST /notifications/{id}/delete
     */
    public function delete(int $id): void
    {
        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        $redirectTarget = $this->resolveSafeRedirect($_POST['redirect_to'] ?? $_GET['redirect_to'] ?? null);

        if ($userId <= 0 || !$this->notificationModel->deleteForUser($id, $userId)) {
            if (request_wants_json()) {
                json_response([
                    'status'  => 'error',
                    'code'    => 404,
                    'message' => 'That notification could not be found.',
                ], 404);
                return;
            }
            Session::flash('error', 'That notification could not be found.');
            redirect($redirectTarget);
            return;
        }

        if (request_wants_json()) {
            json_response([
                'status'  => 'success',
                'code'    => 200,
                'message' => 'Notification deleted.',
                'data'    => [
                    'notification_id' => $id,
                    'unread_count'    => $this->notificationModel->getUnreadCount($userId),
                ],
            ], 200);
            return;
        }

        Session::flash('success', 'Notification deleted.');
        redirect($redirectTarget);
    }
}
