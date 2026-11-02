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
    private Notification $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
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

        if (!$this->notificationModel->belongsToUser($id, (int)$user['id'])) {
            Session::flash('error', 'That notification could not be found.');
            redirect('/notifications');
            return;
        }

        $this->notificationModel->markAsRead($id, (int)$user['id']);
        Session::flash('success', 'Notification marked as read.');
        redirect('/notifications');
    }

    /**
     * Mark all notifications as read
     * Route: POST /notifications/mark-all-read
     */
    public function markAllRead(): void
    {
        $user = currentUser();
        $this->notificationModel->markAllAsRead((int)$user['id']);

        Session::flash('success', 'All notifications marked as read.');
        redirect('/notifications');
    }
}
