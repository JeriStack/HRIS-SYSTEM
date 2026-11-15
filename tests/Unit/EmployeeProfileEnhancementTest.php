<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\ProfileController;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\ProfileCorrectionRequest;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\NotificationPayload;
use App\Services\EmployeeTenureCalculator;
use App\Services\ProfilePictureService;
use Core\Session;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class EmployeeProfileEnhancementTest extends TestCase
{
    private string $tempAvatarDir;
    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REQUEST_URI'] = '/employee/profile';
        $this->tempAvatarDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_avatar_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempAvatarDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        if (is_dir($this->tempAvatarDir)) {
            $items = scandir($this->tempAvatarDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                @unlink($this->tempAvatarDir . DIRECTORY_SEPARATOR . $item);
            }
            @rmdir($this->tempAvatarDir);
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        parent::tearDown();
    }

    public function testTenureCalculatorHandlesExactDatesLeapYearsMissingAndFutureHireDates(): void
    {
        $asOf = new DateTimeImmutable('2026-11-15');

        $exactTwoYears = EmployeeTenureCalculator::calculate('2024-11-15', $asOf);
        $this->assertTrue($exactTwoYears['valid']);
        $this->assertFalse($exactTwoYears['is_future']);
        $this->assertSame(2, $exactTwoYears['years']);
        $this->assertSame(0, $exactTwoYears['months']);
        $this->assertSame('2 years', $exactTwoYears['formatted']);

        $yearsAndMonths = EmployeeTenureCalculator::calculate('2023-07-10', $asOf);
        $this->assertSame(3, $yearsAndMonths['years']);
        $this->assertSame(4, $yearsAndMonths['months']);
        $this->assertSame('3 years, 4 months', $yearsAndMonths['formatted']);

        $leapYearHire = EmployeeTenureCalculator::calculate('2020-02-29', new DateTimeImmutable('2026-03-01'));
        $this->assertTrue($leapYearHire['valid']);
        $this->assertSame(6, $leapYearHire['years']);

        $sameDay = EmployeeTenureCalculator::calculate('2026-11-15', $asOf);
        $this->assertSame('Less than 1 month (hired today)', $sameDay['formatted']);

        $missing = EmployeeTenureCalculator::calculate(null, $asOf);
        $this->assertFalse($missing['valid']);
        $this->assertSame('Not specified', $missing['formatted']);

        $invalid = EmployeeTenureCalculator::calculate('not-a-date', $asOf);
        $this->assertFalse($invalid['valid']);
        $this->assertSame('Not available', $invalid['formatted']);

        $future = EmployeeTenureCalculator::calculate('2026-12-01', $asOf);
        $this->assertTrue($future['valid']);
        $this->assertTrue($future['is_future']);
        $this->assertStringStartsWith('Starts on ', $future['formatted']);
    }

    public function testProfilePictureValidationAcceptsValidImagesAndRejectsMaliciousOrOversizedFiles(): void
    {
        $service = new ProfilePictureService(
            $this->tempAvatarDir,
            static fn (string $path): bool => is_file($path),
            500 * 1024
        );

        foreach (
            [
                ['type' => 'image/jpeg', 'ext' => 'jpg', 'bytes' => $this->buildValidJpegBytes(120, 120)],
                ['type' => 'image/png',  'ext' => 'png', 'bytes' => $this->buildValidPngBytes(120, 120)],
                ['type' => 'image/webp', 'ext' => 'webp', 'bytes' => $this->buildValidWebpBytes(120, 120)],
            ] as $case
        ) {
            $tmp = $this->createTempFile($case['bytes']);
            $res = $service->store(10, [
                'name'     => 'spoofed.exe.' . $case['ext'],
                'type'     => 'application/octet-stream',
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($case['bytes']),
            ]);
            $this->assertTrue($res['ok'], 'Expected valid image to be accepted for ' . $case['type'] . ': ' . ($res['error'] ?? ''));
            $this->assertMatchesRegularExpression('/^emp-10-[a-f0-9]{32}\.' . $case['ext'] . '$/', $res['filename']);
            $resolved = $service->resolveStoredFilePath($res['filename']);
            $this->assertNotNull($resolved);
            $this->assertSame($case['type'], $service->detectStoredMime($resolved));
        }

        // Reject disguised PHP script
        $phpTmp = $this->createTempFile("<?php echo 'pwn'; ?>");
        $phpRes = $service->store(10, [
            'name'     => 'avatar.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $phpTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 21,
        ]);
        $this->assertFalse($phpRes['ok']);

        // Reject SVG markup disguised as PNG
        $svgTmp = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $svgRes = $service->store(10, [
            'name'     => 'avatar.png',
            'type'     => 'image/png',
            'tmp_name' => $svgTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 72,
        ]);
        $this->assertFalse($svgRes['ok']);

        // Reject tiny image below minimum 64x64 dimensions
        $smallPng = $this->buildValidPngBytes(32, 32);
        $smallTmp = $this->createTempFile($smallPng);
        $smallRes = $service->store(10, [
            'name'     => 'tiny.png',
            'type'     => 'image/png',
            'tmp_name' => $smallTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($smallPng),
        ]);
        $this->assertFalse($smallRes['ok']);
        $this->assertStringContainsString('64x64', (string)$smallRes['error']);

        // Reject oversized upload against configurable max byte limit
        $strictService = new ProfilePictureService(
            $this->tempAvatarDir,
            static fn (string $path): bool => is_file($path),
            100
        );
        $validPng = $this->buildValidPngBytes(120, 120);
        $overTmp = $this->createTempFile($validPng);
        $overRes = $strictService->store(10, [
            'name'     => 'big.png',
            'type'     => 'image/png',
            'tmp_name' => $overTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($validPng),
        ]);
        $this->assertFalse($overRes['ok']);

        // Traversal attempt never resolves
        $this->assertNull($service->resolveStoredFilePath('../../config/database.php'));
        $this->assertNull(ProfilePictureService::avatarUrl(10, '../secret.png'));
    }

    public function testProfileControllerUploadReplaceDeleteAndOwnershipChecksOnAvatar(): void
    {
        $pdo = $this->createSqliteSchema();
        $service = new ProfilePictureService(
            $this->tempAvatarDir,
            static fn (string $path): bool => is_file($path)
        );
        $controller = new ProfileController($pdo, $service, false);

        Session::set('user', [
            'id'              => 5,
            'username'        => 'emp10',
            'role'            => 'employee',
            'employee_id'     => 10,
            'profile_picture' => null,
        ]);

        // Upload initial avatar
        $png1 = $this->buildValidPngBytes(100, 100);
        $_FILES['profile_picture'] = [
            'name'     => 'first.png',
            'type'     => 'image/png',
            'tmp_name' => $this->createTempFile($png1),
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($png1),
        ];
        $controller->uploadAvatar();

        $firstFilename = (string)$pdo->query('SELECT profile_picture FROM employees WHERE id = 10')->fetchColumn();
        $this->assertNotSame('', $firstFilename);
        $this->assertNotNull($service->resolveStoredFilePath($firstFilename));
        $this->assertSame($firstFilename, $_SESSION['user']['profile_picture']);

        // Replace avatar and confirm old file was cleaned up safely
        $jpg2 = $this->buildValidJpegBytes(100, 100);
        $_FILES['profile_picture'] = [
            'name'     => 'second.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $this->createTempFile($jpg2),
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($jpg2),
        ];
        $controller->uploadAvatar();

        $secondFilename = (string)$pdo->query('SELECT profile_picture FROM employees WHERE id = 10')->fetchColumn();
        $this->assertNotSame($firstFilename, $secondFilename);
        $this->assertNull($service->resolveStoredFilePath($firstFilename));
        $this->assertNotNull($service->resolveStoredFilePath($secondFilename));

        // Employee 10 may stream own avatar, but is forbidden (403) from streaming employee 11's avatar
        ob_start();
        $controller->avatar(10);
        $streamed = (string)ob_get_clean();
        $this->assertNotSame('', $streamed);

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        $controller->avatar(11);
        $forbiddenJson = (string)ob_get_clean();
        $this->assertStringContainsString('not authorized', $forbiddenJson);
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        // Tampered employee_id in POST triggers ownership rejection without updating DB
        $_POST['employee_id'] = '11';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        $controller->uploadAvatar();
        $tamperJson = (string)ob_get_clean();
        $this->assertStringContainsString('Ownership Access Denied', $tamperJson);
        $_POST = [];
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        // Delete avatar restores null / fallback state and removes stored file
        $controller->deleteAvatar();
        $cleared = $pdo->query('SELECT profile_picture FROM employees WHERE id = 10')->fetchColumn();
        $this->assertNull($cleared ?: null);
        $this->assertNull($_SESSION['user']['profile_picture']);
        $this->assertNull($service->resolveStoredFilePath($secondFilename));
    }

    public function testSelfContactUpdateLeavesWorkEmailAndLoginAccountUnchanged(): void
    {
        $pdo = $this->createSqliteSchema();
        $controller = new ProfileController($pdo, null, false);

        Session::set('user', [
            'id'          => 5,
            'username'    => 'emp10',
            'role'        => 'employee',
            'employee_id' => 10,
        ]);

        // Attempt to also post forbidden HR-controlled fields (work email, first_name, department_id)
        $_POST = [
            'personal_email' => 'Personal.Jane@Gmail.com',
            'phone'          => '+63 917 555 1234',
            'address'        => '123 Mabini Street, Makati City',
            'email'          => 'hacked.work@nexora.local',
            'first_name'     => 'TamperedName',
            'department_id'  => '2',
        ];

        $controller->updateContact();

        $empRow = $pdo->query('SELECT * FROM employees WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $userRow = $pdo->query('SELECT * FROM users WHERE id = 5')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('personal.jane@gmail.com', $empRow['personal_email']);
        $this->assertSame('+63 917 555 1234', $empRow['phone']);
        $this->assertSame('123 Mabini Street, Makati City', $empRow['address']);
        // Official HR identity & login email MUST remain completely untouched
        $this->assertSame('jane.doe@nexora.local', $empRow['email']);
        $this->assertSame('Jane', $empRow['first_name']);
        $this->assertSame(1, (int)$empRow['department_id']);
        $this->assertSame('jane.doe@nexora.local', $userRow['email']);
        $this->assertSame('emp10', $userRow['username']);

        // Reject invalid contact phone format cleanly without partial update
        $_POST = [
            'personal_email' => 'not-an-email',
            'phone'          => 'abc',
            'address'        => 'Changed',
        ];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        $controller->updateContact();
        $errJson = (string)ob_get_clean();
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $decoded = json_decode($errJson, true);
        $this->assertSame('error', $decoded['status']);
        $this->assertArrayHasKey('personal_email', $decoded['errors']);
        $this->assertArrayHasKey('phone', $decoded['errors']);

        $unchangedPhone = (string)$pdo->query('SELECT phone FROM employees WHERE id = 10')->fetchColumn();
        $this->assertSame('+63 917 555 1234', $unchangedPhone);
    }

    public function testEmergencyContactLifecycleValidationAndOwnership(): void
    {
        $pdo = $this->createSqliteSchema();
        $controller = new ProfileController($pdo, null, false);
        $model = new EmergencyContact($pdo);

        $this->assertSame([], $model->getByEmployee(10));

        Session::set('user', [
            'id'          => 5,
            'username'    => 'emp10',
            'role'        => 'employee',
            'employee_id' => 10,
        ]);

        // Add primary emergency contact (optional fields left blank)
        $_POST = [
            'full_name'      => 'Roberto Doe',
            'relationship'   => 'Spouse',
            'contact_number' => '+63 918 222 3344',
        ];
        $controller->saveEmergencyContact();

        $contacts = $model->getByEmployee(10);
        $this->assertCount(1, $contacts);
        $this->assertSame('Roberto Doe', $contacts[0]['full_name']);
        $this->assertNull($contacts[0]['alternative_contact_number']);

        // Update primary emergency contact in place instead of creating duplicate row
        $_POST = [
            'full_name'                  => 'Roberto M. Doe',
            'relationship'               => 'Spouse',
            'contact_number'             => '+63 918 222 9999',
            'alternative_contact_number' => '(02) 8555-0100',
            'address'                    => 'Quezon City',
        ];
        $controller->saveEmergencyContact();
        $updated = $model->getByEmployee(10);
        $this->assertCount(1, $updated);
        $this->assertSame('(02) 8555-0100', $updated[0]['alternative_contact_number']);

        // Employee 11 cannot delete employee 10's emergency contact
        $contactId = (int)$updated[0]['id'];
        Session::set('user', [
            'id'          => 6,
            'username'    => 'emp11',
            'role'        => 'employee',
            'employee_id' => 11,
        ]);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        $controller->deleteEmergencyContact($contactId);
        $denyOut = (string)ob_get_clean();
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->assertStringContainsString('Ownership Access Denied', $denyOut);
        $this->assertCount(1, $model->getByEmployee(10));

        // Employee 10 can delete their own contact
        Session::set('user', [
            'id'          => 5,
            'username'    => 'emp10',
            'role'        => 'employee',
            'employee_id' => 10,
        ]);
        $controller->deleteEmergencyContact($contactId);
        $this->assertSame([], $model->getByEmployee(10));
    }

    public function testEmploymentHistoryPreservesChronologyAndLastPromotionDate(): void
    {
        $pdo = $this->createSqliteSchema();
        $historyModel = new EmploymentHistory($pdo);

        $this->assertNull($historyModel->getLastPromotion(10));

        $historyModel->recordEvent([
            'employee_id'            => 10,
            'event_type'             => EmploymentHistory::EVENT_INITIAL_HIRING,
            'effective_date'         => '2022-03-01',
            'new_department_id'      => 1,
            'new_position_id'        => 1,
            'employment_type'        => 'Probationary',
            'description'            => 'Initial appointment as Junior Developer.',
            'recorded_by'            => 1,
        ]);

        $historyModel->recordEvent([
            'employee_id'            => 10,
            'event_type'             => EmploymentHistory::EVENT_TYPE_CHANGE,
            'effective_date'         => '2022-09-01',
            'previous_department_id' => 1,
            'new_department_id'      => 1,
            'previous_position_id'   => 1,
            'new_position_id'        => 1,
            'employment_type'        => 'Regular',
            'description'            => 'Regularized after probationary evaluation.',
            'recorded_by'            => 1,
        ]);

        $historyModel->recordEvent([
            'employee_id'            => 10,
            'event_type'             => EmploymentHistory::EVENT_PROMOTION,
            'effective_date'         => '2025-01-15',
            'previous_department_id' => 1,
            'new_department_id'      => 1,
            'previous_position_id'   => 1,
            'new_position_id'        => 2,
            'employment_type'        => 'Regular',
            'description'            => 'Promoted to Senior Software Engineer.',
            'recorded_by'            => 1,
        ]);

        $timeline = $historyModel->getByEmployee(10);
        $this->assertCount(3, $timeline);
        $this->assertSame('Promotion', $timeline[0]['event_type']);
        $this->assertSame('2025-01-15', $timeline[0]['effective_date']);
        $this->assertSame('Initial Hiring', $timeline[2]['event_type']);

        $lastPromo = $historyModel->getLastPromotion(10);
        $this->assertNotNull($lastPromo);
        $this->assertSame('2025-01-15', $lastPromo['effective_date']);
        $this->assertSame('Senior Software Engineer', $lastPromo['new_position_name']);
    }

    public function testProfileCorrectionWorkflowSupportsSubmitCancelAtomicApprovalAndRejection(): void
    {
        $pdo = $this->createSqliteSchema();
        $empController = new ProfileController($pdo, null, false);
        $hrController  = new EmployeeController($pdo, false);
        $corrModel     = new ProfileCorrectionRequest($pdo);

        Session::set('user', [
            'id'          => 5,
            'username'    => 'emp10',
            'role'        => 'employee',
            'employee_id' => 10,
        ]);

        // Submit disallowed field is rejected
        $_POST = [
            'field_name'      => 'employee_no',
            'requested_value' => 'HACKED-999',
            'reason'          => 'Try tampering employee ID',
        ];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        $empController->storeCorrectionRequest();
        $badField = json_decode((string)ob_get_clean(), true);
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->assertSame('error', $badField['status']);

        // Submit valid civil status correction and then cancel it by employee
        $_POST = [
            'field_name'      => 'civil_status',
            'requested_value' => 'Married',
            'reason'          => 'Updated marriage certificate submitted to HR.',
        ];
        $empController->storeCorrectionRequest();
        $empReqs = $corrModel->getByEmployee(10);
        $this->assertCount(1, $empReqs);
        $cancelReqId = (int)$empReqs[0]['id'];

        $empController->cancelCorrectionRequest($cancelReqId);
        $this->assertSame('Cancelled', $corrModel->findById($cancelReqId)['status']);

        // Submit Department Transfer correction request & HR approves atomically
        $_POST = [
            'field_name'      => 'department_id',
            'requested_value' => '2',
            'reason'          => 'Approved internal transfer to People Operations.',
        ];
        $empController->storeCorrectionRequest();
        $pending = $corrModel->getAllForReview('Pending');
        $this->assertCount(1, $pending);
        $deptReqId = (int)$pending[0]['id'];

        // Verify WS authorization & zero-PII notification payload for HR alert
        $authorizer = new EventAuthorizer();
        $hrIdentity = new Identity(2, 'hr', 11, 'sess-hr', time() + 300);
        $empIdentity = new Identity(5, 'employee', 10, 'sess-emp', time() + 300);

        $this->assertTrue($authorizer->canDeliver($hrIdentity, [
            'user_id'          => 2,
            'recipient_status' => 'Active',
            'recipient_role'   => 'hr',
            'event_type'       => EventAuthorizer::CORRECTION_REQUESTED,
        ]));
        $this->assertFalse($authorizer->canDeliver($empIdentity, [
            'user_id'          => 5,
            'recipient_status' => 'Active',
            'recipient_role'   => 'employee',
            'event_type'       => EventAuthorizer::CORRECTION_REQUESTED,
        ]));

        $wsPayload = NotificationPayload::fromRow([
            'id'         => 99,
            'event_type' => EventAuthorizer::CORRECTION_REQUESTED,
            'entity_id'  => $deptReqId,
            'created_ts' => time(),
            'first_name' => 'SecretPII',
            'email'      => 'secret@nexora.local',
            'reason'     => 'private reason text',
        ]);
        $this->assertIsArray($wsPayload);
        $this->assertArrayNotHasKey('first_name', $wsPayload['data']);
        $this->assertArrayNotHasKey('email', $wsPayload['data']);
        $this->assertArrayNotHasKey('reason', $wsPayload['data']);
        $this->assertSame('hr/correction-requests', $wsPayload['data']['link']);

        // HR reviews & approves
        Session::set('user', [
            'id'       => 2,
            'username' => 'hrmanager',
            'role'     => 'hr',
        ]);
        $_POST = ['decision_reason' => 'Transfer memo verified.'];
        $hrController->approveCorrection($deptReqId);

        $approvedReq = $corrModel->findById($deptReqId);
        $this->assertSame('Approved', $approvedReq['status']);
        $this->assertSame(2, (int)$pdo->query('SELECT department_id FROM employees WHERE id = 10')->fetchColumn());

        // Approval should automatically log an EmploymentHistory event and notify user 5
        $histories = (new EmploymentHistory($pdo))->getByEmployee(10);
        $this->assertNotEmpty($histories);
        $this->assertSame(EmploymentHistory::EVENT_DEPT_TRANSFER, $histories[0]['event_type']);

        $notifRow = $pdo->query("SELECT * FROM notifications WHERE user_id = 5 AND event_type = 'profile_correction.approved' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($notifRow);
        $dest = DashboardController::resolveNotificationDestination($notifRow);
        $this->assertSame('/employee/profile#correction-requests', $dest['path']);

        // Submit Work Email correction & verify rejection preserves Work Email and requires reason
        Session::set('user', [
            'id'          => 5,
            'username'    => 'emp10',
            'role'        => 'employee',
            'employee_id' => 10,
        ]);
        $_POST = [
            'field_name'      => 'email',
            'requested_value' => 'jane.alias@nexora.local',
            'reason'          => 'Request corporate email alias update.',
        ];
        $empController->storeCorrectionRequest();
        $emailPending = $corrModel->getAllForReview('Pending');
        $emailReqId = (int)$emailPending[0]['id'];

        Session::set('user', [
            'id'       => 2,
            'username' => 'hrmanager',
            'role'     => 'hr',
        ]);
        // Blank rejection reason fails
        $_POST = ['decision_reason' => ''];
        $hrController->rejectCorrection($emailReqId);
        $this->assertSame('Pending', $corrModel->findById($emailReqId)['status']);

        // Valid rejection reason succeeds and preserves email
        $_POST = ['decision_reason' => 'Alias must be requested via IT provisioning ticket.'];
        $hrController->rejectCorrection($emailReqId);
        $this->assertSame('Rejected', $corrModel->findById($emailReqId)['status']);
        $this->assertSame('jane.doe@nexora.local', (string)$pdo->query('SELECT email FROM employees WHERE id = 10')->fetchColumn());
    }

    public function testHrEmployeeUpdateAutomaticallyRecordsPromotionsAndPreservesPriorHistory(): void
    {
        $pdo = $this->createSqliteSchema();
        $historyModel = new EmploymentHistory($pdo);
        $historyModel->recordEvent([
            'employee_id'            => 10,
            'event_type'             => EmploymentHistory::EVENT_INITIAL_HIRING,
            'effective_date'         => '2022-03-01',
            'new_department_id'      => 1,
            'new_position_id'        => 1,
            'employment_type'        => 'Probationary',
            'description'            => 'Initial hire record.',
            'recorded_by'            => 2,
        ]);

        Session::set('user', [
            'id'       => 2,
            'username' => 'hrmanager',
            'role'     => 'hr',
        ]);

        $hrController = new EmployeeController($pdo, false);
        $_POST = [
            'employee_no'       => 'EMP-010',
            'first_name'        => 'Jane',
            'middle_name'       => 'M.',
            'last_name'         => 'Doe',
            'email'             => 'jane.doe@nexora.local',
            'personal_email'    => 'personal.jane@gmail.com',
            'phone'             => '+63 917 000 0001',
            'address'           => 'Taguig City',
            'birth_date'        => '1994-05-10',
            'gender'            => 'Female',
            'civil_status'      => 'Single',
            'department_id'     => '1',
            'position_id'       => '2',
            'date_hired'        => '2022-03-01',
            'employment_type'   => 'Regular',
            'employment_status' => 'Active',
            'is_promotion'      => '1',
            'transition_note'   => 'Promoted to Senior Software Engineer after Q4 review.',
        ];
        $hrController->update(10);

        $timeline = $historyModel->getByEmployee(10);
        $this->assertCount(2, $timeline);
        $this->assertSame(EmploymentHistory::EVENT_PROMOTION, $timeline[0]['event_type']);
        $this->assertSame('Senior Software Engineer', $timeline[0]['new_position_name']);
        $this->assertSame(EmploymentHistory::EVENT_INITIAL_HIRING, $timeline[1]['event_type']);
        $this->assertSame(2, (int)$pdo->query('SELECT position_id FROM employees WHERE id = 10')->fetchColumn());
    }

    public function testProfileAndHrViewsRenderSafelyWithoutBrokenHireDateOrXss(): void
    {
        Session::set('user', [
            'id'              => 5,
            'username'        => 'emp10',
            'name'            => 'Jane <script>alert(1)</script> Doe',
            'role'            => 'employee',
            'employee_id'     => 10,
            'profile_picture' => 'emp-10-0123456789abcdef0123456789abcdef.png',
        ]);

        $render = static function (string $viewFile, array $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            include dirname(__DIR__, 2) . '/app/views/' . $viewFile;
            return (string)ob_get_clean();
        };

        $profileHtml = $render('dashboard/employee_profile.php', [
            'user'                    => Session::get('user'),
            'profile'                 => [
                'id'                => 10,
                'employee_no'       => 'EMP-010',
                'first_name'        => 'Jane <script>alert(1)</script>',
                'middle_name'       => 'M.',
                'last_name'         => 'Doe',
                'email'             => 'jane.doe@nexora.local',
                'personal_email'    => 'personal.jane@gmail.com',
                'phone'             => '+63 917 555 1234',
                'address'           => 'Makati City',
                'birth_date'        => '1994-05-10',
                'gender'            => 'Female',
                'civil_status'      => 'Single',
                'department_id'     => 1,
                'department_name'   => 'Engineering',
                'position_id'       => 1,
                'position_name'     => 'Software Engineer',
                'date_hired'        => '2024-01-15',
                'employment_type'   => 'Regular',
                'employment_status' => 'Active',
                'profile_picture'   => 'emp-10-0123456789abcdef0123456789abcdef.png',
            ],
            'avatarUrl'               => '/media/avatars/10?v=test123',
            'avatarMaxBytes'          => 5 * 1024 * 1024,
            'tenure'                  => EmployeeTenureCalculator::calculate('2024-01-15', new DateTimeImmutable('2026-11-15')),
            'lastPromotion'           => ['effective_date' => '2025-06-01', 'new_position_name' => 'Senior Engineer'],
            'emergencyContacts'       => [],
            'employmentHistory'       => [],
            'correctionRequests'      => [],
            'allowedCorrectionFields' => ProfileCorrectionRequest::getAllowedFields(),
            'departments'             => [['id' => 1, 'department_name' => 'Engineering']],
            'positions'               => [['id' => 1, 'position_name' => 'Software Engineer', 'department_id' => 1]],
            'civilStatuses'           => ['Single', 'Married'],
            'employmentTypes'         => ['Regular', 'Probationary'],
            'relationships'           => EmergencyContact::VALID_RELATIONSHIPS,
            'formErrors'              => [],
            'formOld'                 => [],
            'activeForm'              => '',
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $profileHtml);
        $this->assertStringContainsString('/media/avatars/10?v=test123', $profileHtml);
        $this->assertStringContainsString('Jan 15, 2024', $profileHtml);
        $this->assertStringContainsString('2 years, 10 months', $profileHtml);
        $this->assertStringContainsString('Jun 1, 2025', $profileHtml);
        $this->assertStringContainsString('No emergency contact on file yet', $profileHtml);
        $this->assertStringContainsString('No career transitions recorded yet', $profileHtml);

        // Ensure navbar and sidebar render consistent avatar image
        $navbarHtml = $render('partials/navbar.php', ['pageTitle' => 'My Profile']);
        $this->assertStringContainsString('/media/avatars/10?v=', $navbarHtml);

        $sidebarHtml = $render('partials/sidebar.php', ['currentPath' => '/employee/profile']);
        $this->assertStringContainsString('/media/avatars/10?v=', $sidebarHtml);

        // Verify unique DOM IDs across profile view
        preg_match_all('/\sid="([^"]+)"/', $profileHtml, $idMatches);
        $this->assertCount(count(array_unique($idMatches[1])), $idMatches[1], 'Duplicate DOM IDs found in employee_profile.php');
    }

    private function createSqliteSchema(): PDO
    {
        if (class_exists(\Pdo\Sqlite::class)) {
            $pdo = new \Pdo\Sqlite('sqlite::memory:');
            $pdo->createFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        } else {
            $pdo = new PDO('sqlite::memory:');
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
            }
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                department_name TEXT NOT NULL
            );
            CREATE TABLE positions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                position_name TEXT NOT NULL,
                department_id INTEGER NOT NULL
            );
            CREATE TABLE employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_no TEXT NOT NULL UNIQUE,
                first_name TEXT NOT NULL,
                middle_name TEXT NULL,
                last_name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                personal_email TEXT NULL,
                phone TEXT NULL,
                address TEXT NULL,
                birth_date TEXT NOT NULL,
                gender TEXT NOT NULL,
                civil_status TEXT NULL,
                department_id INTEGER NOT NULL,
                position_id INTEGER NOT NULL,
                date_hired TEXT NOT NULL,
                employment_type TEXT NOT NULL DEFAULT 'Regular',
                employment_status TEXT NOT NULL DEFAULT 'Active',
                profile_picture TEXT NULL,
                created_at TEXT NULL
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NULL,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT 'employee',
                status TEXT NOT NULL DEFAULT 'Active',
                created_at TEXT NULL
            );
            CREATE TABLE employee_emergency_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                full_name TEXT NOT NULL,
                relationship TEXT NOT NULL,
                contact_number TEXT NOT NULL,
                alternative_contact_number TEXT NULL,
                address TEXT NULL,
                is_primary INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE employment_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                effective_date TEXT NOT NULL,
                previous_department_id INTEGER NULL,
                new_department_id INTEGER NULL,
                previous_position_id INTEGER NULL,
                new_position_id INTEGER NULL,
                employment_type TEXT NULL,
                description TEXT NULL,
                recorded_by INTEGER NULL,
                created_at TEXT NULL
            );
            CREATE TABLE profile_correction_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                requested_by_user_id INTEGER NOT NULL,
                field_name TEXT NOT NULL,
                current_value TEXT NULL,
                requested_value TEXT NOT NULL,
                reason TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'Pending',
                reviewed_by INTEGER NULL,
                reviewed_at TEXT NULL,
                decision_reason TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'info',
                event_type TEXT NULL,
                entity_id INTEGER NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL
            );
            CREATE TABLE activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NULL,
                user_id INTEGER NULL,
                actor_role TEXT NULL,
                action TEXT NOT NULL,
                category TEXT NULL,
                description TEXT NOT NULL,
                entity_type TEXT NULL,
                entity_id TEXT NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                status TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                request_id TEXT NULL,
                created_at TEXT NULL
            );
        ");

        $pdo->exec("
            INSERT INTO departments (id, department_name) VALUES (1, 'Engineering'), (2, 'People Operations');
            INSERT INTO positions (id, position_name, department_id) VALUES
                (1, 'Software Engineer', 1),
                (2, 'Senior Software Engineer', 1),
                (3, 'HR Specialist', 2);
            INSERT INTO employees (id, employee_no, first_name, middle_name, last_name, email, phone, address, birth_date, gender, civil_status, department_id, position_id, date_hired, employment_type, employment_status, created_at) VALUES
                (10, 'EMP-010', 'Jane', 'M.', 'Doe', 'jane.doe@nexora.local', '+63 917 000 0001', 'Taguig City', '1994-05-10', 'Female', 'Single', 1, 1, '2022-03-01', 'Regular', 'Active', '2022-03-01 09:00:00'),
                (11, 'EMP-011', 'Mark', NULL, 'Santos', 'mark.santos@nexora.local', '+63 917 000 0002', 'Pasig City', '1991-09-20', 'Male', 'Married', 2, 3, '2021-06-15', 'Regular', 'Active', '2021-06-15 09:00:00');
            INSERT INTO users (id, employee_id, username, email, role, status, created_at) VALUES
                (2, 11, 'hrmanager', 'hr@nexora.local', 'hr', 'Active', '2021-06-15 09:00:00'),
                (5, 10, 'emp10', 'jane.doe@nexora.local', 'employee', 'Active', '2022-03-01 09:00:00'),
                (6, 11, 'emp11', 'mark.santos@nexora.local', 'employee', 'Active', '2021-06-15 09:00:00');
        ");

        return $pdo;
    }

    private function createTempFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nexora_upload_');
        if ($path === false) {
            $this->fail('Unable to create temporary upload file.');
        }
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function buildValidPngBytes(int $width, int $height): string
    {
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $rowLen = 1 + ($width * 3);
        $rawScanlines = str_repeat("\x00" . str_repeat("\x1D\x4E\xD8", $width), $height);
        if (strlen($rawScanlines) !== $height * $rowLen) {
            $rawScanlines = str_repeat("\x00", $height * $rowLen);
        }
        $idatData = (string)gzcompress($rawScanlines);

        return "\x89PNG\r\n\x1a\n"
            . $this->pngChunk('IHDR', $ihdrData)
            . $this->pngChunk('IDAT', $idatData)
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function buildValidJpegBytes(int $width, int $height): string
    {
        $sof0Data = "\x08" . pack('nn', $height, $width) . "\x01\x01\x11\x00";
        return "\xFF\xD8"
            . "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "\xFF\xC0" . pack('n', strlen($sof0Data) + 2) . $sof0Data
            . "\xFF\xD9";
    }

    private function buildValidWebpBytes(int $width, int $height): string
    {
        $wMinus = $width - 1;
        $hMinus = $height - 1;
        $vp8xPayload = "\x00\x00\x00\x00"
            . chr($wMinus & 0xFF) . chr(($wMinus >> 8) & 0xFF) . chr(($wMinus >> 16) & 0xFF)
            . chr($hMinus & 0xFF) . chr(($hMinus >> 8) & 0xFF) . chr(($hMinus >> 16) & 0xFF);
        $vp8xChunk = 'VP8X' . pack('V', strlen($vp8xPayload)) . $vp8xPayload;

        return 'RIFF' . pack('V', 4 + strlen($vp8xChunk)) . 'WEBP' . $vp8xChunk;
    }
}
