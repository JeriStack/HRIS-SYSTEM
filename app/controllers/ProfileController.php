<?php

/**
 * Self-Service Profile & Media Controller
 * Handles authenticated profile picture streaming/upload/removal, self-editable contact
 * details, emergency contacts, and employee profile correction requests with strict ownership.
 */

namespace App\Controllers;

use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ProfileCorrectionRequest;
use App\Realtime\EventAuthorizer;
use App\Services\ProfilePictureService;
use Core\AuditService;
use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;

class ProfileController extends Controller
{
    private PDO $pdo;
    private Employee $employeeModel;
    private EmergencyContact $emergencyModel;
    private ProfileCorrectionRequest $correctionModel;
    private Notification $notificationModel;
    private ProfilePictureService $picService;
    private bool $terminateOnRedirect;

    /** @var array{url: string, status: int}|null */
    private ?array $lastRedirect = null;

    public function __construct(
        ?PDO $pdo = null,
        ?ProfilePictureService $picService = null,
        bool $terminateOnRedirect = true
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->employeeModel = new Employee($this->pdo);
        $this->emergencyModel = new EmergencyContact($this->pdo);
        $this->correctionModel = new ProfileCorrectionRequest($this->pdo);
        $this->notificationModel = new Notification($this->pdo);
        $this->picService = $picService ?? new ProfilePictureService();
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * Expose last navigation target when redirect termination is disabled in tests.
     *
     * @return array{url: string, status: int}|null
     */
    public function getLastRedirect(): ?array
    {
        return $this->lastRedirect;
    }

    /**
     * Stream an employee's profile picture safely through authenticated authorization checks.
     * Route: GET /media/avatars/{id}
     */
    public function avatar(int $employeeId): void
    {
        $user = currentUser();
        $role = strtolower((string)($user['role'] ?? ''));
        $myEmpId = isset($user['employee_id']) ? (int)$user['employee_id'] : 0;

        if (empty($user['id']) || (!in_array($role, ['admin', 'hr'], true) && $employeeId !== $myEmpId)) {
            $this->forbid('You are not authorized to access this employee profile picture.');
            return;
        }

        $employee = $this->employeeModel->findById($employeeId);
        $filename = is_array($employee) ? ($employee['profile_picture'] ?? null) : null;
        $path = $this->picService->resolveStoredFilePath(is_string($filename) ? $filename : null);
        $mime = $path !== null ? $this->picService->detectStoredMime($path) : null;

        if ($employee === null || $path === null || $mime === null) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string)filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=300');
            header('Content-Disposition: inline; filename="avatar-' . $employeeId . '"');
        }

        readfile($path);
    }

    /**
     * Upload, validate, crop, and assign the authenticated employee's profile picture.
     * Route: POST /employee/profile/avatar
     */
    public function uploadAvatar(): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];

        $crop = null;
        if (
            isset($_POST['crop_x'], $_POST['crop_y'], $_POST['crop_size'])
            && is_numeric($_POST['crop_x'])
            && is_numeric($_POST['crop_y'])
            && is_numeric($_POST['crop_size'])
            && (float)$_POST['crop_size'] > 0
        ) {
            $crop = [
                'x'    => (float)$_POST['crop_x'],
                'y'    => (float)$_POST['crop_y'],
                'size' => (float)$_POST['crop_size'],
            ];
        }

        $file = isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture'])
            ? $_FILES['profile_picture']
            : [];

        $stored = $this->picService->store($empId, $file, $crop);
        if (!$stored['ok']) {
            $errMsg = (string)($stored['error'] ?? 'Unable to upload profile picture.');
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => $errMsg], 422);
                return;
            }
            Session::flash('error', $errMsg);
            Session::flash('profile_active_form', 'avatar');
            $this->navigate('/employee/profile#profile-photo');
            return;
        }

        $newFilename = (string)$stored['filename'];
        $oldFilename = is_string($employee['profile_picture'] ?? null) ? $employee['profile_picture'] : null;

        $this->employeeModel->updateProfilePicture($empId, $newFilename);
        $this->syncSessionAvatar($newFilename);

        if ($oldFilename !== null && $oldFilename !== '' && $oldFilename !== $newFilename) {
            $this->picService->deleteStoredFile($oldFilename, $this->pdo, $empId);
        }

        $this->recordAudit(
            (int)$user['id'],
            'EMPLOYEE_AVATAR_UPDATED',
            "Updated profile picture for employee {$employee['employee_no']}.",
            'EMPLOYEE',
            (string)$empId,
            ['has_profile_picture' => $oldFilename !== null && $oldFilename !== ''],
            ['has_profile_picture' => true]
        );

        if (request_wants_json()) {
            $this->json([
                'status'     => 'success',
                'message'    => 'Profile picture updated successfully.',
                'avatar_url' => ProfilePictureService::avatarUrl($empId, $newFilename),
            ]);
            return;
        }

        Session::flash('success', 'Profile picture updated successfully.');
        $this->navigate('/employee/profile#profile-photo');
    }

    /**
     * Remove the authenticated employee's uploaded picture and restore initials fallback.
     * Route: POST /employee/profile/avatar/delete
     */
    public function deleteAvatar(): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];
        $oldFilename = is_string($employee['profile_picture'] ?? null) ? $employee['profile_picture'] : null;

        if ($oldFilename === null || trim($oldFilename) === '') {
            if (request_wants_json()) {
                $this->json(['status' => 'success', 'message' => 'Default initials avatar is already active.']);
                return;
            }
            Session::flash('info', 'Your default initials avatar is already active.');
            $this->navigate('/employee/profile#profile-photo');
            return;
        }

        $this->employeeModel->updateProfilePicture($empId, null);
        $this->syncSessionAvatar(null);
        $this->picService->deleteStoredFile($oldFilename, $this->pdo, $empId);

        $this->recordAudit(
            (int)$user['id'],
            'EMPLOYEE_AVATAR_REMOVED',
            "Removed profile picture for employee {$employee['employee_no']}.",
            'EMPLOYEE',
            (string)$empId,
            ['has_profile_picture' => true],
            ['has_profile_picture' => false]
        );

        if (request_wants_json()) {
            $this->json([
                'status'     => 'success',
                'message'    => 'Profile picture removed. Default initials avatar restored.',
                'avatar_url' => null,
            ]);
            return;
        }

        Session::flash('success', 'Profile picture removed. Your default initials avatar has been restored.');
        $this->navigate('/employee/profile#profile-photo');
    }

    /**
     * Update permitted self-service contact details without modifying HR work email or login identity.
     * Route: POST /employee/profile/contact
     */
    public function updateContact(): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];

        $personalEmailRaw = trim((string)($_POST['personal_email'] ?? ''));
        $personalEmail = $personalEmailRaw !== '' ? strtolower($personalEmailRaw) : '';
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        $errors = [];

        if ($personalEmail !== '') {
            if (!filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['personal_email'] = 'Please enter a valid personal email address.';
            } elseif (mb_strlen($personalEmail) > 100) {
                $errors['personal_email'] = 'Personal email address cannot exceed 100 characters.';
            }
        }

        if ($phone !== '') {
            if (!EmergencyContact::isValidPhoneNumber($phone) || mb_strlen($phone) > 25) {
                $errors['phone'] = 'Please enter a valid mobile or telephone number (7-25 characters).';
            }
        }

        if (mb_strlen($address) > 500) {
            $errors['address'] = 'Residential address cannot exceed 500 characters.';
        }

        if (!empty($errors)) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'errors' => $errors], 422);
                return;
            }
            Session::flash('profile_form_errors', $errors);
            Session::flash('profile_form_old', [
                'personal_email' => $personalEmailRaw,
                'phone'          => $phone,
                'address'        => $address,
            ]);
            Session::flash('profile_active_form', 'contact');
            Session::flash('error', 'Please review the highlighted contact fields and try again.');
            $this->navigate('/employee/profile#contact-info');
            return;
        }

        $newData = [
            'personal_email' => $personalEmail !== '' ? $personalEmail : null,
            'phone'          => $phone !== '' ? $phone : null,
            'address'        => $address !== '' ? $address : null,
        ];

        $this->employeeModel->updateSelfContact($empId, $newData);

        $this->recordAudit(
            (int)$user['id'],
            'EMPLOYEE_CONTACT_UPDATED',
            "Updated self-service contact details for {$employee['employee_no']}.",
            'EMPLOYEE',
            (string)$empId,
            [
                'personal_email' => $employee['personal_email'] ?? null,
                'phone'          => $employee['phone'] ?? null,
                'address'        => $employee['address'] ?? null,
            ],
            $newData
        );

        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'message' => 'Contact information updated successfully.',
                'contact' => $newData,
            ]);
            return;
        }

        Session::flash('success', 'Your contact information has been updated.');
        $this->navigate('/employee/profile#contact-info');
    }

    /**
     * Save or update the authenticated employee's emergency contact record.
     * Route: POST /employee/profile/emergency-contact
     */
    public function saveEmergencyContact(): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];

        $validation = EmergencyContact::validateInput($_POST);
        if (!empty($validation['errors'])) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'errors' => $validation['errors']], 422);
                return;
            }
            Session::flash('profile_form_errors', $validation['errors']);
            Session::flash('profile_form_old', $validation['data']);
            Session::flash('profile_active_form', 'emergency');
            Session::flash('error', 'Please fix the highlighted emergency contact fields.');
            $this->navigate('/employee/profile#emergency-contact');
            return;
        }

        $contactId = $this->emergencyModel->upsertPrimary($empId, $validation['data']);

        $this->recordAudit(
            (int)$user['id'],
            'EMPLOYEE_EMERGENCY_CONTACT_SAVED',
            "Saved emergency contact for employee {$employee['employee_no']}.",
            'EMPLOYEE',
            (string)$empId,
            null,
            [
                'contact_id'   => $contactId,
                'relationship' => $validation['data']['relationship'],
            ]
        );

        if (request_wants_json()) {
            $this->json([
                'status'     => 'success',
                'message'    => 'Emergency contact saved successfully.',
                'contact_id' => $contactId,
            ]);
            return;
        }

        Session::flash('success', 'Emergency contact information saved successfully.');
        $this->navigate('/employee/profile#emergency-contact');
    }

    /**
     * Remove an emergency contact belonging strictly to the authenticated employee.
     * Route: POST /employee/profile/emergency-contact/{id}/delete
     */
    public function deleteEmergencyContact(int $contactId): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];

        $stmtCheck = $this->pdo->prepare("SELECT employee_id FROM employee_emergency_contacts WHERE id = :id LIMIT 1");
        $stmtCheck->execute(['id' => $contactId]);
        $ownerRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($ownerRow && (int)$ownerRow['employee_id'] !== $empId) {
            $this->forbid('Ownership Access Denied: You cannot remove another employee\'s emergency contact.');
            return;
        }

        $deleted = $this->emergencyModel->deleteForEmployee($contactId, $empId);
        if (!$deleted) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => 'Emergency contact record not found.'], 404);
                return;
            }
            Session::flash('error', 'Emergency contact record could not be found.');
            $this->navigate('/employee/profile#emergency-contact');
            return;
        }

        $this->recordAudit(
            (int)$user['id'],
            'EMPLOYEE_EMERGENCY_CONTACT_REMOVED',
            "Removed emergency contact #{$contactId} for employee {$employee['employee_no']}.",
            'EMPLOYEE',
            (string)$empId,
            ['contact_id' => $contactId],
            null
        );

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => 'Emergency contact removed.']);
            return;
        }

        Session::flash('success', 'Emergency contact removed.');
        $this->navigate('/employee/profile#emergency-contact');
    }

    /**
     * Submit a profile correction request for an HR-controlled attribute.
     * Route: POST /employee/profile/correction-requests
     */
    public function storeCorrectionRequest(): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];
        $userId = (int)$user['id'];

        $validation = $this->correctionModel->validateSubmission($employee, $_POST);
        if (!$validation['ok']) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'errors' => $validation['errors']], 422);
                return;
            }
            Session::flash('profile_form_errors', $validation['errors']);
            Session::flash('profile_form_old', [
                'field_name'      => trim((string)($_POST['field_name'] ?? '')),
                'requested_value' => trim((string)($_POST['requested_value'] ?? '')),
                'reason'          => trim((string)($_POST['reason'] ?? '')),
            ]);
            Session::flash('profile_active_form', 'correction');
            Session::flash('error', 'Please review the highlighted correction request fields.');
            $this->navigate('/employee/profile#correction-requests');
            return;
        }

        $payload = $validation['data'];
        $payload['requested_by_user_id'] = $userId;
        $requestId = $this->correctionModel->create($payload);

        $empName = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $fieldLabel = ProfileCorrectionRequest::fieldLabel((string)$payload['field_name']);

        try {
            $this->notificationModel->notifyRole(
                'hr',
                'Profile Correction Requested',
                "{$empName} ({$employee['employee_no']}) requested a correction to {$fieldLabel}.",
                'info',
                EventAuthorizer::CORRECTION_REQUESTED,
                $requestId
            );
        } catch (\Throwable $e) {
            error_log('[ProfileController::storeCorrectionRequest] Notification dispatch failed: ' . $e->getMessage());
        }

        $this->recordAudit(
            $userId,
            'PROFILE_CORRECTION_REQUESTED',
            "Submitted correction request #{$requestId} ({$fieldLabel}) for {$employee['employee_no']}.",
            'PROFILE_CORRECTION_REQUEST',
            (string)$requestId,
            ['field_name' => $payload['field_name'], 'current_value' => $payload['current_value']],
            ['requested_value' => $payload['requested_value'], 'status' => ProfileCorrectionRequest::STATUS_PENDING]
        );

        if (request_wants_json()) {
            $this->json([
                'status'     => 'success',
                'message'    => 'Correction request submitted for HR review.',
                'request_id' => $requestId,
            ], 201);
            return;
        }

        Session::flash('success', "Your correction request for {$fieldLabel} has been submitted to HR for review.");
        $this->navigate('/employee/profile#correction-requests');
    }

    /**
     * Cancel an employee's own still-Pending profile correction request.
     * Route: POST /employee/profile/correction-requests/{id}/cancel
     */
    public function cancelCorrectionRequest(int $requestId): void
    {
        $ctx = $this->resolveAuthenticatedEmployee();
        if ($ctx === null) {
            return;
        }

        $employee = $ctx['employee'];
        $user = $ctx['user'];
        $empId = (int)$employee['id'];

        $existing = $this->correctionModel->findById($requestId);
        if ($existing !== null && (int)$existing['employee_id'] !== $empId) {
            $this->forbid('Ownership Access Denied: You are not authorized to cancel another employee\'s correction request.');
            return;
        }

        if (!$this->correctionModel->cancelByEmployee($requestId, $empId)) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => 'Only pending requests belonging to you can be cancelled.'], 422);
                return;
            }
            Session::flash('error', 'Only pending correction requests can be cancelled.');
            $this->navigate('/employee/profile#correction-requests');
            return;
        }

        $this->recordAudit(
            (int)$user['id'],
            'PROFILE_CORRECTION_CANCELLED',
            "Cancelled profile correction request #{$requestId} for {$employee['employee_no']}.",
            'PROFILE_CORRECTION_REQUEST',
            (string)$requestId,
            ['status' => ProfileCorrectionRequest::STATUS_PENDING],
            ['status' => ProfileCorrectionRequest::STATUS_CANCELLED]
        );

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => 'Profile correction request cancelled.']);
            return;
        }

        Session::flash('success', 'Your profile correction request has been cancelled.');
        $this->navigate('/employee/profile#correction-requests');
    }

    /**
     * Resolve and verify ownership for employee self-service profile operations.
     *
     * @return array{user: array<string, mixed>, employee: array<string, mixed>}|null
     */
    private function resolveAuthenticatedEmployee(): ?array
    {
        $user = currentUser();
        $myEmpId = isset($user['employee_id']) ? (int)$user['employee_id'] : 0;

        if (!is_array($user) || empty($user['id']) || $myEmpId <= 0) {
            $this->forbid('Your user account is not linked to an employee profile.');
            return null;
        }

        $tamperedId = null;
        if (isset($_POST['employee_id']) && $_POST['employee_id'] !== '') {
            $tamperedId = (int)$_POST['employee_id'];
        } elseif (isset($_GET['emp_id']) && $_GET['emp_id'] !== '') {
            $tamperedId = (int)$_GET['emp_id'];
        }

        if ($tamperedId !== null && $tamperedId !== $myEmpId) {
            $this->forbid('Ownership Access Denied: You are not authorized to access or modify another employee\'s private records (NFR-SEC-004).');
            return null;
        }

        $employee = $this->employeeModel->findById($myEmpId);
        if ($employee === null) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => 'Employee profile record not found.'], 404);
                return null;
            }
            Session::flash('error', 'Linked employee record was not found.');
            $this->navigate('/employee/profile');
            return null;
        }

        return [
            'user'     => $user,
            'employee' => $employee,
        ];
    }

    private function syncSessionAvatar(?string $filename): void
    {
        $sessionUser = Session::get('user');
        if (is_array($sessionUser)) {
            $sessionUser['profile_picture'] = $filename;
            Session::set('user', $sessionUser);
        }
    }

    private function recordAudit(
        int $actorUserId,
        string $action,
        string $description,
        string $entityType,
        string $entityId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        try {
            AuditService::getInstance($this->pdo)->record([
                'actor_user_id' => $actorUserId,
                'action'        => $action,
                'category'      => AuditService::CATEGORY_EMPLOYEE,
                'description'   => $description,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'old_values'    => $oldValues,
                'new_values'    => $newValues,
            ]);
        } catch (\Throwable $e) {
            error_log('[ProfileController] Audit recording failed: ' . $e->getMessage());
        }
    }

    private function forbid(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 403);
            return;
        }

        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Ownership Violation',
            'message'   => $message,
        ], null);
    }

    private function navigate(string $path, int $statusCode = 302): void
    {
        $this->lastRedirect = ['url' => $path, 'status' => $statusCode];
        if ($this->terminateOnRedirect) {
            $this->redirect($path, $statusCode);
        }
    }
}
