<?php

/**
 * AnnouncementControllerAdapter (Anti-Corruption Layer)
 * HRIS-SYSTEM — Clean Architecture / Application & Presentation Adapter
 *
 * Orchestrates announcement creation, draft editing, immediate/scheduled publication,
 * schedule cancellation, archiving, expiration processing, and employee read receipts
 * with atomic database transactions, outbox persistence, and post-commit domain events.
 */

declare(strict_types=1);

namespace App\Application\Announcement\Adapters;

use App\Domain\Announcement\Entities\Announcement;
use App\Domain\Announcement\Entities\AnnouncementTarget;
use App\Domain\Announcement\Events\AnnouncementRead;
use App\Domain\Announcement\Exceptions\AnnouncementNotFoundException;
use App\Domain\Announcement\Exceptions\InvalidAnnouncementTargetException;
use App\Domain\Announcement\Exceptions\InvalidAnnouncementTransitionException;
use App\Domain\Announcement\Exceptions\UnauthorizedAnnouncementActionException;
use App\Domain\Announcement\ValueObjects\AnnouncementCategory;
use App\Domain\Announcement\ValueObjects\AnnouncementContent;
use App\Domain\Announcement\ValueObjects\AnnouncementPriority;
use App\Domain\Announcement\ValueObjects\AnnouncementTitle;
use App\Domain\Announcement\ValueObjects\ExpirationDate;
use App\Domain\Announcement\ValueObjects\PublicationDate;
use App\Domain\Common\DomainEventInterface;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Events\EventBootstrap;
use App\Infrastructure\Outbox\MySqlOutboxRepository;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Services\AnnouncementAttachmentService;
use Core\AuditService;
use Core\Database;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class AnnouncementControllerAdapter
{
    private MySqlAnnouncementRepository $repository;
    private TransactionManagerInterface $txManager;
    private EventDispatcherInterface $eventDispatcher;
    private ?OutboxRepositoryInterface $outboxRepository;
    private AnnouncementAttachmentService $attachmentService;
    private AuditService $auditService;

    public function __construct(
        ?PDO $pdo = null,
        ?MySqlAnnouncementRepository $repository = null,
        ?TransactionManagerInterface $txManager = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?OutboxRepositoryInterface $outboxRepository = null,
        ?AnnouncementAttachmentService $attachmentService = null,
        ?AuditService $auditService = null
    ) {
        $connection = $pdo ?? Database::getInstance()->getConnection();
        $this->repository = $repository ?? new MySqlAnnouncementRepository($connection);
        $this->txManager = $txManager ?? new PdoTransactionManager($connection);
        $this->eventDispatcher = $eventDispatcher ?? EventBootstrap::createConfiguredDispatcher();
        $this->outboxRepository = $outboxRepository ?? new MySqlOutboxRepository($connection);
        $this->attachmentService = $attachmentService ?? new AnnouncementAttachmentService();
        $this->auditService = $auditService ?? AuditService::getInstance($connection);
    }

    public function getRepository(): MySqlAnnouncementRepository
    {
        return $this->repository;
    }

    public function getAttachmentService(): AnnouncementAttachmentService
    {
        return $this->attachmentService;
    }

    /**
     * Create a new announcement (as Draft, Scheduled, or immediately Published).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $filesInput Raw $_FILES['attachments']
     */
    public function create(
        int $authorUserId,
        string $actorRole,
        array $input,
        ?array $filesInput = null,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            if (strtolower(trim($actorRole)) === 'employee') {
                throw UnauthorizedAnnouncementActionException::forRole($actorRole, 'create');
            }

            $clock = $now ?? new DateTimeImmutable();
            $title = AnnouncementTitle::fromString((string)($input['title'] ?? ''));
            $content = AnnouncementContent::fromString((string)($input['content'] ?? ''));
            $category = AnnouncementCategory::fromString((string)($input['category'] ?? 'General Announcement'));
            $priority = AnnouncementPriority::fromString((string)($input['priority'] ?? 'Normal'));
            $isPinned = !empty($input['is_pinned']);

            $targets = $this->resolveValidatedTargets(
                (string)($input['target_type'] ?? 'all'),
                $input['department_ids'] ?? []
            );

            $expiresAtRaw = trim((string)($input['expires_at'] ?? ''));
            $expiresAt = $expiresAtRaw !== '' ? ExpirationDate::fromString($expiresAtRaw) : null;

            $scheduledAtRaw = trim((string)($input['scheduled_at'] ?? ''));
            $scheduledAt = $scheduledAtRaw !== '' ? PublicationDate::fromString($scheduledAtRaw) : null;

            $publishAction = strtolower(trim((string)($input['publish_mode'] ?? $input['action'] ?? 'draft')));

            // Pre-validate uploaded files before opening DB transaction
            $normalizedFiles = AnnouncementAttachmentService::normalizeFilesArray($filesInput);
            $validatedFiles = !empty($normalizedFiles)
                ? $this->attachmentService->validateBatch($normalizedFiles)
                : [];

            /** @var list<DomainEventInterface> $events */
            $events = [];
            $storedFiles = [];
            $announcementId = 0;
            $finalStatus = 'Draft';

            try {
                $this->txManager->transactional(function () use (
                    $title,
                    $content,
                    $category,
                    $priority,
                    $authorUserId,
                    $targets,
                    $isPinned,
                    $expiresAt,
                    $scheduledAt,
                    $publishAction,
                    $clock,
                    $validatedFiles,
                    &$events,
                    &$storedFiles,
                    &$announcementId,
                    &$finalStatus
                ): void {
                    $announcement = Announcement::createDraft(
                        title: $title,
                        content: $content,
                        category: $category,
                        priority: $priority,
                        authorId: $authorUserId,
                        targets: $targets,
                        isPinned: $isPinned,
                        expiresAt: $expiresAt,
                        now: $clock
                    );

                    if ($publishAction === 'publish' || $publishAction === 'publish_now') {
                        // Release initial draft event so only AnnouncementPublished is emitted for immediate publish
                        $announcement->releaseEvents();
                        $announcement->publish($authorUserId, $clock);
                    } elseif ($publishAction === 'schedule' || ($scheduledAt !== null && $publishAction !== 'draft')) {
                        if ($scheduledAt === null) {
                            throw new InvalidArgumentException('Scheduled publication date and time is required when scheduling.');
                        }
                        $announcement->releaseEvents();
                        $announcement->schedule($scheduledAt, $authorUserId, $expiresAt, $clock);
                    }

                    $announcementId = $this->repository->save($announcement);
                    $finalStatus = $announcement->status()->value();

                    if (!empty($validatedFiles)) {
                        $storedFiles = $this->attachmentService->storeBatch($announcementId, $validatedFiles);
                        $this->repository->addAttachments($announcementId, $authorUserId, $storedFiles);
                    }

                    $events = $announcement->releaseEvents();
                    if ($this->outboxRepository !== null) {
                        foreach ($events as $event) {
                            $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                        }
                    }
                });
            } catch (Throwable $txErr) {
                if (!empty($storedFiles)) {
                    $this->attachmentService->rollbackStoredFiles(array_column($storedFiles, 'storage_relative_path'));
                }
                throw $txErr;
            }

            // Post-commit dispatch (only after DB transaction has committed)
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            $msg = match ($finalStatus) {
                'Published' => 'Announcement published successfully.',
                'Scheduled' => 'Announcement scheduled for publication.',
                default     => 'Announcement saved as draft.',
            };

            return AnnouncementOperationResult::ok(
                $msg,
                ['id' => $announcementId, 'status' => $finalStatus],
                201
            );
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTargetException | InvalidAnnouncementTransitionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'domain_validation_error');
        } catch (InvalidArgumentException | DomainException | RuntimeException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::create] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to create announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Update an existing announcement (and optionally transition Draft/Scheduled -> Published/Scheduled).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $filesInput
     */
    public function update(
        int $announcementId,
        int $actorUserId,
        string $actorRole,
        bool $canManageAll,
        array $input,
        ?array $filesInput = null,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            if (strtolower(trim($actorRole)) === 'employee') {
                throw UnauthorizedAnnouncementActionException::forRole($actorRole, 'update');
            }

            $clock = $now ?? new DateTimeImmutable();
            $title = AnnouncementTitle::fromString((string)($input['title'] ?? ''));
            $content = AnnouncementContent::fromString((string)($input['content'] ?? ''));
            $category = AnnouncementCategory::fromString((string)($input['category'] ?? 'General Announcement'));
            $priority = AnnouncementPriority::fromString((string)($input['priority'] ?? 'Normal'));
            $isPinned = !empty($input['is_pinned']);

            $targets = $this->resolveValidatedTargets(
                (string)($input['target_type'] ?? 'all'),
                $input['department_ids'] ?? []
            );

            $expiresAtRaw = trim((string)($input['expires_at'] ?? ''));
            $expiresAt = $expiresAtRaw !== '' ? ExpirationDate::fromString($expiresAtRaw) : null;

            $scheduledAtRaw = trim((string)($input['scheduled_at'] ?? ''));
            $scheduledAt = $scheduledAtRaw !== '' ? PublicationDate::fromString($scheduledAtRaw) : null;

            $publishAction = strtolower(trim((string)($input['publish_mode'] ?? $input['action'] ?? 'save')));

            $normalizedFiles = AnnouncementAttachmentService::normalizeFilesArray($filesInput);
            $validatedFiles = !empty($normalizedFiles)
                ? $this->attachmentService->validateBatch($normalizedFiles)
                : [];

            /** @var list<DomainEventInterface> $events */
            $events = [];
            $storedFiles = [];
            $removedAttachmentPaths = [];
            $finalStatus = '';

            try {
                $this->txManager->transactional(function () use (
                    $announcementId,
                    $actorUserId,
                    $canManageAll,
                    $title,
                    $content,
                    $category,
                    $priority,
                    $targets,
                    $isPinned,
                    $expiresAt,
                    $scheduledAt,
                    $publishAction,
                    $clock,
                    $validatedFiles,
                    $input,
                    &$events,
                    &$storedFiles,
                    &$removedAttachmentPaths,
                    &$finalStatus
                ): void {
                    $announcement = $this->repository->findByIdForUpdate($announcementId);
                    if ($announcement === null) {
                        throw AnnouncementNotFoundException::forId($announcementId);
                    }

                    $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                    $oldAudienceAll = $announcement->targetsAllEmployees();
                    $oldDeptIds = $announcement->targetedDepartmentIds();

                    $announcement->updateDetails(
                        title: $title,
                        content: $content,
                        category: $category,
                        priority: $priority,
                        targets: $targets,
                        isPinned: $isPinned,
                        expiresAt: $expiresAt,
                        actorUserId: $actorUserId,
                        now: $clock
                    );

                    // Optional attachment removal
                    $removeAttachmentIds = isset($input['remove_attachment_ids']) && is_array($input['remove_attachment_ids'])
                        ? array_map('intval', $input['remove_attachment_ids'])
                        : [];
                    foreach ($removeAttachmentIds as $remId) {
                        if ($remId > 0) {
                            $deleted = $this->repository->deleteAttachment($remId, $announcementId);
                            if ($deleted !== null) {
                                $removedAttachmentPaths[] = (string)$deleted['storage_relative_path'];
                                $this->auditService->record([
                                    'action'        => 'ANNOUNCEMENT_ATTACHMENT_REMOVED',
                                    'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                                    'description'   => sprintf('Removed attachment "%s" from announcement #%d', (string)$deleted['original_filename'], $announcementId),
                                    'actor_user_id' => $actorUserId,
                                    'entity_type'   => 'Announcement',
                                    'entity_id'     => (string)$announcementId,
                                ]);
                            }
                        }
                    }

                    // Check total attachment limit
                    if (!empty($validatedFiles)) {
                        $existingCount = $this->repository->countAttachments($announcementId);
                        $maxAllowed = $this->attachmentService->getMaxFilesPerAnnouncement();
                        if (($existingCount + count($validatedFiles)) > $maxAllowed) {
                            throw new RuntimeException(sprintf('An announcement can have at most %d attachments.', $maxAllowed));
                        }
                        $storedFiles = $this->attachmentService->storeBatch($announcementId, $validatedFiles);
                        $this->repository->addAttachments($announcementId, $actorUserId, $storedFiles);
                        $this->auditService->record([
                            'action'        => 'ANNOUNCEMENT_ATTACHMENT_UPLOADED',
                            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                            'description'   => sprintf('Uploaded %d attachment(s) to announcement #%d', count($storedFiles), $announcementId),
                            'actor_user_id' => $actorUserId,
                            'entity_type'   => 'Announcement',
                            'entity_id'     => (string)$announcementId,
                        ]);
                    }

                    // Audit audience changes explicitly if target scope changed
                    $newAudienceAll = $announcement->targetsAllEmployees();
                    $newDeptIds = $announcement->targetedDepartmentIds();
                    if ($oldAudienceAll !== $newAudienceAll || $oldDeptIds !== $newDeptIds) {
                        $this->auditService->record([
                            'action'        => 'ANNOUNCEMENT_AUDIENCE_CHANGED',
                            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                            'description'   => sprintf('Updated target audience for announcement #%d', $announcementId),
                            'actor_user_id' => $actorUserId,
                            'entity_type'   => 'Announcement',
                            'entity_id'     => (string)$announcementId,
                            'old_values'    => ['all' => $oldAudienceAll, 'departments' => $oldDeptIds],
                            'new_values'    => ['all' => $newAudienceAll, 'departments' => $newDeptIds],
                        ]);
                    }

                    if (($publishAction === 'publish' || $publishAction === 'publish_now') && !$announcement->status()->isPublished()) {
                        $announcement->publish($actorUserId, $clock);
                    } elseif ($publishAction === 'schedule' && !$announcement->status()->isPublished()) {
                        if ($scheduledAt === null) {
                            throw new InvalidArgumentException('Scheduled publication date and time is required when scheduling.');
                        }
                        $announcement->schedule($scheduledAt, $actorUserId, $expiresAt, $clock);
                    } elseif ($announcement->status()->isScheduled() && $scheduledAt !== null) {
                        $announcement->schedule($scheduledAt, $actorUserId, $expiresAt, $clock);
                    }

                    $this->repository->save($announcement);
                    $finalStatus = $announcement->status()->value();
                    $events = $announcement->releaseEvents();

                    if ($this->outboxRepository !== null) {
                        foreach ($events as $event) {
                            $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                        }
                    }
                });
            } catch (Throwable $txErr) {
                if (!empty($storedFiles)) {
                    $this->attachmentService->rollbackStoredFiles(array_column($storedFiles, 'storage_relative_path'));
                }
                throw $txErr;
            }

            // Delete removed attachment files from disk only after the DB transaction commits
            if (!empty($removedAttachmentPaths)) {
                $this->attachmentService->rollbackStoredFiles($removedAttachmentPaths);
            }

            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return AnnouncementOperationResult::ok(
                'Announcement updated successfully.',
                ['id' => $announcementId, 'status' => $finalStatus],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTargetException | InvalidAnnouncementTransitionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'domain_validation_error');
        } catch (InvalidArgumentException | DomainException | RuntimeException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::update] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to update announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Immediately publish a Draft, Scheduled, or Expired announcement.
     */
    public function publish(
        int $announcementId,
        int $actorUserId,
        bool $canManageAll = false,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            $clock = $now ?? new DateTimeImmutable();
            /** @var list<DomainEventInterface> $events */
            $events = [];

            $this->txManager->transactional(function () use (
                $announcementId,
                $actorUserId,
                $canManageAll,
                $clock,
                &$events
            ): void {
                $announcement = $this->repository->findByIdForUpdate($announcementId);
                if ($announcement === null) {
                    throw AnnouncementNotFoundException::forId($announcementId);
                }

                $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                $announcement->publish($actorUserId, $clock);
                $this->repository->save($announcement);
                $events = $announcement->releaseEvents();

                if ($this->outboxRepository !== null) {
                    foreach ($events as $event) {
                        $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                    }
                }
            });

            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return AnnouncementOperationResult::ok(
                'Announcement published and queued for audience notification.',
                ['id' => $announcementId, 'status' => 'Published'],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTransitionException | InvalidArgumentException | DomainException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'invalid_transition');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::publish] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to publish announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Schedule an announcement for future publication.
     */
    public function schedule(
        int $announcementId,
        int $actorUserId,
        string $scheduledAtRaw,
        ?string $expiresAtRaw = null,
        bool $canManageAll = false,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            $clock = $now ?? new DateTimeImmutable();
            $scheduledAt = PublicationDate::fromString($scheduledAtRaw);
            $expiresAt = ($expiresAtRaw !== null && trim($expiresAtRaw) !== '')
                ? ExpirationDate::fromString($expiresAtRaw)
                : null;

            /** @var list<DomainEventInterface> $events */
            $events = [];

            $this->txManager->transactional(function () use (
                $announcementId,
                $actorUserId,
                $scheduledAt,
                $expiresAt,
                $canManageAll,
                $clock,
                &$events
            ): void {
                $announcement = $this->repository->findByIdForUpdate($announcementId);
                if ($announcement === null) {
                    throw AnnouncementNotFoundException::forId($announcementId);
                }

                $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                $announcement->schedule($scheduledAt, $actorUserId, $expiresAt, $clock);
                $this->repository->save($announcement);
                $events = $announcement->releaseEvents();

                if ($this->outboxRepository !== null) {
                    foreach ($events as $event) {
                        $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                    }
                }
            });

            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return AnnouncementOperationResult::ok(
                'Announcement scheduled successfully.',
                [
                    'id'           => $announcementId,
                    'status'       => 'Scheduled',
                    'scheduled_at' => $scheduledAt->toDatabaseString(),
                ],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTransitionException | InvalidArgumentException | DomainException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'invalid_schedule');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::schedule] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to schedule announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Cancel a pending scheduled publication and revert to Draft.
     */
    public function cancelSchedule(
        int $announcementId,
        int $actorUserId,
        bool $canManageAll = false,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            $clock = $now ?? new DateTimeImmutable();
            /** @var list<DomainEventInterface> $events */
            $events = [];

            $this->txManager->transactional(function () use (
                $announcementId,
                $actorUserId,
                $canManageAll,
                $clock,
                &$events
            ): void {
                $announcement = $this->repository->findByIdForUpdate($announcementId);
                if ($announcement === null) {
                    throw AnnouncementNotFoundException::forId($announcementId);
                }

                $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                $announcement->cancelSchedule($actorUserId, $clock);
                $this->repository->save($announcement);
                $events = $announcement->releaseEvents();

                $this->auditService->record([
                    'action'        => 'ANNOUNCEMENT_SCHEDULE_CANCELLED',
                    'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                    'description'   => sprintf('Cancelled scheduled publication for announcement #%d (reverted to Draft)', $announcementId),
                    'actor_user_id' => $actorUserId,
                    'entity_type'   => 'Announcement',
                    'entity_id'     => (string)$announcementId,
                    'old_values'    => ['status' => 'Scheduled'],
                    'new_values'    => ['status' => 'Draft'],
                ]);

                if ($this->outboxRepository !== null) {
                    foreach ($events as $event) {
                        $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                    }
                }
            });

            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return AnnouncementOperationResult::ok(
                'Scheduled publication cancelled. Announcement returned to Draft.',
                ['id' => $announcementId, 'status' => 'Draft'],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTransitionException | InvalidArgumentException | DomainException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'invalid_transition');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::cancelSchedule] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to cancel schedule right now.', 500, 'server_error');
        }
    }

    /**
     * Archive an announcement.
     */
    public function archive(
        int $announcementId,
        int $actorUserId,
        bool $canManageAll = false,
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            $clock = $now ?? new DateTimeImmutable();
            /** @var list<DomainEventInterface> $events */
            $events = [];

            $this->txManager->transactional(function () use (
                $announcementId,
                $actorUserId,
                $canManageAll,
                $clock,
                &$events
            ): void {
                $announcement = $this->repository->findByIdForUpdate($announcementId);
                if ($announcement === null) {
                    throw AnnouncementNotFoundException::forId($announcementId);
                }

                $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                $announcement->archive($actorUserId, $clock);
                $this->repository->save($announcement);
                $events = $announcement->releaseEvents();

                if ($this->outboxRepository !== null) {
                    foreach ($events as $event) {
                        $this->outboxRepository->record($event, 'Announcement', (string)$announcementId);
                    }
                }
            });

            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return AnnouncementOperationResult::ok(
                'Announcement archived.',
                ['id' => $announcementId, 'status' => 'Archived'],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (InvalidAnnouncementTransitionException | InvalidArgumentException | DomainException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'invalid_transition');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::archive] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to archive announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Delete an unpublished Draft announcement.
     */
    public function deleteDraft(
        int $announcementId,
        int $actorUserId,
        bool $canManageAll = false
    ): AnnouncementOperationResult {
        try {
            $attachmentPaths = [];

            $this->txManager->transactional(function () use (
                $announcementId,
                $actorUserId,
                $canManageAll,
                &$attachmentPaths
            ): void {
                $announcement = $this->repository->findByIdForUpdate($announcementId);
                if ($announcement === null) {
                    throw AnnouncementNotFoundException::forId($announcementId);
                }

                $this->assertCanModifyAnnouncement($announcement, $actorUserId, $canManageAll);

                if (!$announcement->status()->isDraft()) {
                    throw new DomainException('Only unpublished Draft announcements can be deleted. Archive published or scheduled announcements instead.');
                }

                foreach ($announcement->attachments() as $att) {
                    $attachmentPaths[] = $att->storageRelativePath();
                }

                if (!$this->repository->deleteDraft($announcementId)) {
                    throw new DomainException('Draft announcement could not be deleted.');
                }

                $this->auditService->record([
                    'action'        => 'ANNOUNCEMENT_DELETED',
                    'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                    'description'   => sprintf('Deleted draft announcement #%d ("%s")', $announcementId, $announcement->title()->value()),
                    'actor_user_id' => $actorUserId,
                    'entity_type'   => 'Announcement',
                    'entity_id'     => (string)$announcementId,
                    'old_values'    => [
                        'title'  => $announcement->title()->value(),
                        'status' => 'Draft',
                    ],
                ]);
            });

            if (!empty($attachmentPaths)) {
                $this->attachmentService->rollbackStoredFiles($attachmentPaths);
            }

            return AnnouncementOperationResult::ok(
                'Draft announcement deleted.',
                ['id' => $announcementId],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (DomainException | InvalidArgumentException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 400, 'cannot_delete');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::deleteDraft] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to delete draft announcement right now.', 500, 'server_error');
        }
    }

    /**
     * Record an employee read receipt idempotently when opening announcement details (Phase 6.1).
     */
    public function recordEmployeeRead(
        int $announcementId,
        int $employeeId,
        ?int $employeeDepartmentId,
        string $employmentStatus = 'Active',
        ?DateTimeImmutable $now = null
    ): AnnouncementOperationResult {
        try {
            $clock = $now ?? new DateTimeImmutable();
            $announcement = $this->repository->findById($announcementId);

            if ($announcement === null) {
                throw AnnouncementNotFoundException::forId($announcementId);
            }

            if (!$announcement->isAccessibleByEmployee($employeeDepartmentId, $employmentStatus, $clock)) {
                throw UnauthorizedAnnouncementActionException::employeeNotInTargetAudience();
            }

            $res = $this->repository->recordReadReceipt($announcementId, $employeeId, $clock);
            $receipt = $res['receipt'];
            $newlyCreated = $res['newly_created'];

            if ($newlyCreated) {
                $readEvent = new AnnouncementRead($announcementId, $employeeId, $receipt->readAt());
                $this->eventDispatcher->dispatch($readEvent);
            }

            return AnnouncementOperationResult::ok(
                'Read receipt recorded.',
                [
                    'announcement_id' => $announcementId,
                    'employee_id'     => $employeeId,
                    'read_at'         => $receipt->readAt()->format('Y-m-d H:i:s'),
                    'newly_recorded'  => $newlyCreated,
                ],
                200
            );
        } catch (AnnouncementNotFoundException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (UnauthorizedAnnouncementActionException $e) {
            return AnnouncementOperationResult::fail($e->getMessage(), 403, 'forbidden');
        } catch (Throwable $e) {
            error_log('[AnnouncementControllerAdapter::recordEmployeeRead] ' . $e->getMessage());
            return AnnouncementOperationResult::fail('Unable to record read receipt.', 500, 'server_error');
        }
    }

    /**
     * Idempotent background CRON processor for scheduled publications and expirations (Phase 4.1 & 4.2).
     * Uses row-level FOR UPDATE locking per announcement so concurrent CRON executions or HR edits
     * never double-publish or double-expire.
     *
     * @return array{published: int, expired: int, failed: int, published_ids: list<int>, expired_ids: list<int>}
     */
    public function processDueLifecycleTransitions(?DateTimeImmutable $now = null, int $batchLimit = 50): array
    {
        $clock = $now ?? new DateTimeImmutable();
        $publishedCount = 0;
        $expiredCount = 0;
        $failedCount = 0;
        $publishedIds = [];
        $expiredIds = [];

        // 1. Process Scheduled -> Published
        $dueScheduledIds = $this->repository->findDueScheduledIds($clock, $batchLimit);
        foreach ($dueScheduledIds as $aid) {
            /** @var list<DomainEventInterface> $events */
            $events = [];
            $didPublish = false;

            try {
                $this->txManager->transactional(function () use ($aid, $clock, &$events, &$didPublish): void {
                    $ann = $this->repository->findByIdForUpdate($aid);
                    if ($ann === null || !$ann->status()->isScheduled()) {
                        return; // Already published, cancelled, or archived by concurrent worker/HR
                    }
                    $schedAt = $ann->publicationSchedule()->scheduledAt();
                    if ($schedAt === null || !$schedAt->isDueAt($clock)) {
                        return;
                    }

                    // If expiration already passed before scheduled publish ran, transition to Expired instead
                    if ($ann->publicationSchedule()->isExpiredAt($clock)) {
                        $ann->publish($ann->authorId(), $schedAt->value());
                        $ann->releaseEvents();
                        $ann->expire($clock);
                        $this->repository->save($ann);
                        $events = $ann->releaseEvents();
                    } else {
                        $ann->publish($ann->authorId(), $clock);
                        $this->repository->save($ann);
                        $events = $ann->releaseEvents();
                        $didPublish = true;
                    }

                    if ($this->outboxRepository !== null) {
                        foreach ($events as $ev) {
                            $this->outboxRepository->record($ev, 'Announcement', (string)$aid);
                        }
                    }
                });

                foreach ($events as $ev) {
                    $this->eventDispatcher->dispatch($ev);
                }

                if ($didPublish) {
                    $publishedCount++;
                    $publishedIds[] = $aid;
                }
            } catch (Throwable $e) {
                $failedCount++;
                error_log(sprintf('[AnnouncementCron::publish #%d] %s', $aid, $e->getMessage()));
            }
        }

        // 2. Process Published -> Expired
        $dueExpiredIds = $this->repository->findDueExpiredIds($clock, $batchLimit);
        foreach ($dueExpiredIds as $aid) {
            /** @var list<DomainEventInterface> $events */
            $events = [];
            $didExpire = false;

            try {
                $this->txManager->transactional(function () use ($aid, $clock, &$events, &$didExpire): void {
                    $ann = $this->repository->findByIdForUpdate($aid);
                    if ($ann === null || !$ann->status()->isPublished()) {
                        return; // Already expired or archived
                    }
                    if (!$ann->publicationSchedule()->isExpiredAt($clock)) {
                        return;
                    }

                    $ann->expire($clock);
                    $this->repository->save($ann);
                    $events = $ann->releaseEvents();
                    $didExpire = true;

                    if ($this->outboxRepository !== null) {
                        foreach ($events as $ev) {
                            $this->outboxRepository->record($ev, 'Announcement', (string)$aid);
                        }
                    }
                });

                foreach ($events as $ev) {
                    $this->eventDispatcher->dispatch($ev);
                }

                if ($didExpire) {
                    $expiredCount++;
                    $expiredIds[] = $aid;
                }
            } catch (Throwable $e) {
                $failedCount++;
                error_log(sprintf('[AnnouncementCron::expire #%d] %s', $aid, $e->getMessage()));
            }
        }

        return [
            'published'     => $publishedCount,
            'expired'       => $expiredCount,
            'failed'        => $failedCount,
            'published_ids' => $publishedIds,
            'expired_ids'   => $expiredIds,
        ];
    }

    /**
     * Check whether an HR/Admin actor is permitted to modify an announcement (Phase 9.1).
     */
    public function canModifyAnnouncement(Announcement $announcement, int $actorUserId, bool $canManageAll): bool
    {
        if ($canManageAll) {
            return true;
        }

        return $actorUserId > 0 && $announcement->authorId() === $actorUserId;
    }

    /**
     * Check whether an HR/Admin actor is permitted to inspect detailed read statistics/receipts (Phase 9.1 & 13.3).
     */
    public function canViewAnnouncementStats(Announcement $announcement, int $actorUserId, bool $canViewAllStats): bool
    {
        if ($canViewAllStats) {
            return true;
        }

        return $actorUserId > 0 && $announcement->authorId() === $actorUserId;
    }

    private function assertCanModifyAnnouncement(Announcement $announcement, int $actorUserId, bool $canManageAll): void
    {
        if (!$this->canModifyAnnouncement($announcement, $actorUserId, $canManageAll)) {
            throw UnauthorizedAnnouncementActionException::notAuthorOrAuthorizedManager();
        }
    }

    /**
     * Validate target_type and department_ids against the database (Phase 3.2).
     * Rejects arbitrary or non-existent department IDs.
     *
     * @param mixed $rawDepartmentIds
     * @return list<AnnouncementTarget>
     */
    private function resolveValidatedTargets(string $targetType, mixed $rawDepartmentIds): array
    {
        $normalizedType = strtolower(trim($targetType));
        if ($normalizedType === AnnouncementTarget::TYPE_ALL) {
            return [AnnouncementTarget::allEmployees()];
        }

        if ($normalizedType !== AnnouncementTarget::TYPE_DEPARTMENT) {
            throw new InvalidArgumentException("Invalid target audience type '{$targetType}'.");
        }

        if (!is_array($rawDepartmentIds)) {
            $rawDepartmentIds = $rawDepartmentIds !== null && $rawDepartmentIds !== '' ? [$rawDepartmentIds] : [];
        }

        $requestedIds = array_values(array_unique(array_filter(
            array_map('intval', $rawDepartmentIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($requestedIds)) {
            throw InvalidAnnouncementTargetException::emptyTargets();
        }

        $validatedIds = $this->repository->validateDepartmentIds($requestedIds);
        foreach ($requestedIds as $reqId) {
            if (!in_array($reqId, $validatedIds, true)) {
                throw InvalidAnnouncementTargetException::invalidDepartmentId($reqId);
            }
        }

        $targets = [];
        foreach ($validatedIds as $deptId) {
            $targets[] = AnnouncementTarget::forDepartment($deptId);
        }

        return $targets;
    }
}
