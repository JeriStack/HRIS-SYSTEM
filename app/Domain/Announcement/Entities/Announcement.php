<?php

/**
 * Announcement Aggregate Root Entity
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 *
 * Enforces all domain invariants (Phase 2.3):
 * - Valid title, content, category, priority, and author
 * - Valid target audience (all active employees or verified departments)
 * - Controlled lifecycle transitions (Draft, Scheduled, Published, Archived, Expired)
 * - Draft, Scheduled (before publication), Expired, and Archived announcements cannot appear in active employee feeds
 * - Published announcements must have a valid publication timestamp
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Entities;

use App\Domain\Announcement\Events\AnnouncementArchived;
use App\Domain\Announcement\Events\AnnouncementCreated;
use App\Domain\Announcement\Events\AnnouncementExpired;
use App\Domain\Announcement\Events\AnnouncementPublished;
use App\Domain\Announcement\Events\AnnouncementScheduled;
use App\Domain\Announcement\Events\AnnouncementUpdated;
use App\Domain\Announcement\Exceptions\InvalidAnnouncementTargetException;
use App\Domain\Announcement\Exceptions\InvalidAnnouncementTransitionException;
use App\Domain\Announcement\ValueObjects\AnnouncementCategory;
use App\Domain\Announcement\ValueObjects\AnnouncementContent;
use App\Domain\Announcement\ValueObjects\AnnouncementPriority;
use App\Domain\Announcement\ValueObjects\AnnouncementPublicationSchedule;
use App\Domain\Announcement\ValueObjects\AnnouncementStatus;
use App\Domain\Announcement\ValueObjects\AnnouncementTitle;
use App\Domain\Announcement\ValueObjects\ExpirationDate;
use App\Domain\Announcement\ValueObjects\PublicationDate;
use App\Domain\Common\DomainEventInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class Announcement
{
    private ?int $id;
    private AnnouncementTitle $title;
    private AnnouncementContent $content;
    private AnnouncementCategory $category;
    private AnnouncementPriority $priority;
    private AnnouncementStatus $status;
    private int $authorId;
    private AnnouncementPublicationSchedule $schedule;
    private bool $isPinned;
    /** @var list<AnnouncementTarget> */
    private array $targets = [];
    /** @var list<AnnouncementAttachment> */
    private array $attachments = [];
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $archivedAt;
    /** @var list<DomainEventInterface> */
    private array $recordedEvents = [];

    /**
     * @param list<AnnouncementTarget> $targets
     * @param list<AnnouncementAttachment> $attachments
     */
    private function __construct(
        ?int $id,
        AnnouncementTitle $title,
        AnnouncementContent $content,
        AnnouncementCategory $category,
        AnnouncementPriority $priority,
        AnnouncementStatus $status,
        int $authorId,
        AnnouncementPublicationSchedule $schedule,
        bool $isPinned,
        array $targets,
        array $attachments = [],
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $archivedAt = null
    ) {
        if ($authorId <= 0) {
            throw new InvalidArgumentException('Announcement author ID must be a positive integer.');
        }

        $this->assertValidTargets($targets);

        if ($status->isPublished() && $schedule->publishedAt() === null) {
            throw new InvalidArgumentException('A published announcement must have a valid publication timestamp.');
        }

        if ($status->isScheduled() && $schedule->scheduledAt() === null) {
            throw new InvalidArgumentException('A scheduled announcement must have a valid scheduled publication timestamp.');
        }

        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->priority = $priority;
        $this->status = $status;
        $this->authorId = $authorId;
        $this->schedule = $schedule;
        $this->isPinned = $isPinned;
        $this->targets = array_values($targets);
        $this->attachments = array_values($attachments);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->archivedAt = $archivedAt;
    }

    /**
     * @param list<AnnouncementTarget> $targets
     */
    public static function createDraft(
        AnnouncementTitle $title,
        AnnouncementContent $content,
        AnnouncementCategory $category,
        AnnouncementPriority $priority,
        int $authorId,
        array $targets,
        bool $isPinned = false,
        ?ExpirationDate $expiresAt = null,
        ?DateTimeImmutable $now = null
    ): self {
        $clock = $now ?? new DateTimeImmutable();
        if ($expiresAt !== null && !$expiresAt->isAfter($clock)) {
            throw new InvalidArgumentException('Expiration date must be in the future.');
        }

        $schedule = new AnnouncementPublicationSchedule(
            scheduledAt: null,
            publishedAt: null,
            expiresAt: $expiresAt
        );

        $entity = new self(
            id: null,
            title: $title,
            content: $content,
            category: $category,
            priority: $priority,
            status: AnnouncementStatus::draft(),
            authorId: $authorId,
            schedule: $schedule,
            isPinned: $isPinned,
            targets: $targets,
            attachments: [],
            createdAt: $clock
        );

        $entity->recordEvent(new AnnouncementCreated(
            announcementId: 0,
            authorUserId: $authorId,
            status: AnnouncementStatus::DRAFT,
            occurredAt: $clock
        ));

        return $entity;
    }

    /**
     * @param list<AnnouncementTarget> $targets
     * @param list<AnnouncementAttachment> $attachments
     */
    public static function reconstitute(
        int $id,
        AnnouncementTitle $title,
        AnnouncementContent $content,
        AnnouncementCategory $category,
        AnnouncementPriority $priority,
        AnnouncementStatus $status,
        int $authorId,
        AnnouncementPublicationSchedule $schedule,
        bool $isPinned,
        array $targets,
        array $attachments = [],
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $archivedAt = null
    ): self {
        return new self(
            id: $id,
            title: $title,
            content: $content,
            category: $category,
            priority: $priority,
            status: $status,
            authorId: $authorId,
            schedule: $schedule,
            isPinned: $isPinned,
            targets: $targets,
            attachments: $attachments,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            archivedAt: $archivedAt
        );
    }

    /**
     * Update editable announcement fields and target audience.
     * Archived announcements cannot be modified.
     *
     * @param list<AnnouncementTarget> $targets
     */
    public function updateDetails(
        AnnouncementTitle $title,
        AnnouncementContent $content,
        AnnouncementCategory $category,
        AnnouncementPriority $priority,
        array $targets,
        bool $isPinned,
        ?ExpirationDate $expiresAt,
        int $actorUserId,
        ?DateTimeImmutable $now = null
    ): void {
        if ($this->status->isArchived()) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), 'Edit');
        }

        $clock = $now ?? new DateTimeImmutable();
        $this->assertValidTargets($targets);

        if ($expiresAt !== null && !$expiresAt->isAfter($clock)) {
            throw new InvalidArgumentException('Expiration date must be in the future.');
        }

        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->priority = $priority;
        $this->targets = array_values($targets);
        $this->isPinned = $isPinned;
        $this->schedule = new AnnouncementPublicationSchedule(
            scheduledAt: $this->schedule->scheduledAt(),
            publishedAt: $this->schedule->publishedAt(),
            expiresAt: $expiresAt
        );
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementUpdated(
            announcementId: $this->id ?? 0,
            actorUserId: $actorUserId,
            status: $this->status->value(),
            occurredAt: $clock
        ));
    }

    /**
     * Schedule an announcement for future publication.
     */
    public function schedule(
        PublicationDate $scheduledAt,
        int $actorUserId,
        ?ExpirationDate $expiresAt = null,
        ?DateTimeImmutable $now = null
    ): void {
        $clock = $now ?? new DateTimeImmutable();
        $targetStatus = AnnouncementStatus::scheduled();

        if ($this->status->isPublished() || $this->status->isArchived()) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        if (!$scheduledAt->isInFuture($clock)) {
            throw new InvalidArgumentException('Scheduled publication date and time must be in the future.');
        }

        $effectiveExpiresAt = $expiresAt ?? $this->schedule->expiresAt();
        $this->schedule = new AnnouncementPublicationSchedule(
            scheduledAt: $scheduledAt,
            publishedAt: null,
            expiresAt: $effectiveExpiresAt
        );
        $this->status = $targetStatus;
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementScheduled(
            announcementId: $this->id ?? 0,
            actorUserId: $actorUserId,
            scheduledAt: $scheduledAt->toDatabaseString(),
            occurredAt: $clock
        ));
    }

    /**
     * Cancel a pending scheduled publication and return the announcement to Draft.
     */
    public function cancelSchedule(int $actorUserId, ?DateTimeImmutable $now = null): void
    {
        if (!$this->status->isScheduled()) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), AnnouncementStatus::DRAFT);
        }

        $clock = $now ?? new DateTimeImmutable();
        $this->status = AnnouncementStatus::draft();
        $this->schedule = $this->schedule->withoutSchedule($this->schedule->expiresAt());
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementUpdated(
            announcementId: $this->id ?? 0,
            actorUserId: $actorUserId,
            status: AnnouncementStatus::DRAFT,
            occurredAt: $clock
        ));
    }

    /**
     * Publish an announcement immediately or when a scheduled time arrives.
     * Prevents duplicate publication if already Published.
     */
    public function publish(int $actorUserId, ?DateTimeImmutable $publishedAt = null): void
    {
        if ($this->status->isPublished()) {
            throw InvalidAnnouncementTransitionException::fromTransition(
                AnnouncementStatus::PUBLISHED,
                AnnouncementStatus::PUBLISHED
            );
        }

        $targetStatus = AnnouncementStatus::published();
        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        $clock = $publishedAt ?? new DateTimeImmutable();
        $pubDate = PublicationDate::fromDateTime($clock);

        if ($this->schedule->expiresAt() !== null && !$this->schedule->expiresAt()->isAfter($clock)) {
            throw new InvalidArgumentException('Cannot publish an announcement whose expiration date has already passed.');
        }

        $this->schedule = $this->schedule->withPublishedAt($pubDate, $this->schedule->expiresAt());
        $this->status = $targetStatus;
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementPublished(
            announcementId: $this->id ?? 0,
            actorUserId: $actorUserId,
            priority: $this->priority->value(),
            publishedAt: $pubDate->toDatabaseString(),
            occurredAt: $clock
        ));
    }

    /**
     * Archive an announcement so it is removed from active feeds while preserving historical audit state.
     */
    public function archive(int $actorUserId, ?DateTimeImmutable $archivedAt = null): void
    {
        if ($this->status->isArchived()) {
            throw InvalidAnnouncementTransitionException::fromTransition(
                AnnouncementStatus::ARCHIVED,
                AnnouncementStatus::ARCHIVED
            );
        }

        $targetStatus = AnnouncementStatus::archived();
        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        $clock = $archivedAt ?? new DateTimeImmutable();
        $this->status = $targetStatus;
        $this->archivedAt = $clock;
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementArchived(
            announcementId: $this->id ?? 0,
            actorUserId: $actorUserId,
            occurredAt: $clock
        ));
    }

    /**
     * Transition a Published announcement to Expired when its expiration timestamp has passed.
     */
    public function expire(?DateTimeImmutable $now = null): void
    {
        if (!$this->status->isPublished()) {
            throw InvalidAnnouncementTransitionException::fromTransition(
                $this->status->value(),
                AnnouncementStatus::EXPIRED
            );
        }

        $clock = $now ?? new DateTimeImmutable();
        if (!$this->schedule->isExpiredAt($clock)) {
            throw new InvalidArgumentException('Announcement cannot be marked Expired before its expiration date.');
        }

        $this->status = AnnouncementStatus::expired();
        $this->updatedAt = $clock;

        $this->recordEvent(new AnnouncementExpired(
            announcementId: $this->id ?? 0,
            occurredAt: $clock
        ));
    }

    /**
     * Domain invariant check: whether this announcement is currently eligible to appear in the active employee feed.
     */
    public function isVisibleInEmployeeFeed(?DateTimeImmutable $now = null): bool
    {
        if (!$this->status->isPublished()) {
            return false;
        }

        $clock = $now ?? new DateTimeImmutable();

        if ($this->schedule->publishedAt() === null || !$this->schedule->publishedAt()->isDueAt($clock)) {
            return false;
        }

        if ($this->schedule->isExpiredAt($clock)) {
            return false;
        }

        return true;
    }

    /**
     * Domain invariant check: whether a given employee matches the audience targeting rules
     * AND the announcement is visible in the employee feed.
     */
    public function isAccessibleByEmployee(
        ?int $employeeDepartmentId,
        string $employmentStatus = 'Active',
        ?DateTimeImmutable $now = null
    ): bool {
        if (!$this->isVisibleInEmployeeFeed($now)) {
            return false;
        }

        return $this->isEmployeeTargeted($employeeDepartmentId, $employmentStatus);
    }

    public function isEmployeeTargeted(?int $employeeDepartmentId, string $employmentStatus = 'Active'): bool
    {
        foreach ($this->targets as $target) {
            if ($target->matchesEmployee($employeeDepartmentId, $employmentStatus)) {
                return true;
            }
        }

        return false;
    }

    public function assignPersistedId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Persisted announcement ID must be positive.');
        }
        $this->id = $id;
    }

    /**
     * @return list<DomainEventInterface>
     */
    public function releaseEvents(): array
    {
        $events = [];
        foreach ($this->recordedEvents as $ev) {
            if ($this->id !== null && $this->id > 0) {
                $events[] = $this->rebindEventAnnouncementId($ev, $this->id);
            } else {
                $events[] = $ev;
            }
        }
        $this->recordedEvents = [];

        return $events;
    }

    private function recordEvent(DomainEventInterface $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private function rebindEventAnnouncementId(DomainEventInterface $event, int $id): DomainEventInterface
    {
        if ($event instanceof AnnouncementCreated && $event->announcementId === 0) {
            return new AnnouncementCreated($id, $event->authorUserId, $event->status, $event->occurredAt);
        }
        if ($event instanceof AnnouncementUpdated && $event->announcementId === 0) {
            return new AnnouncementUpdated($id, $event->actorUserId, $event->status, $event->occurredAt);
        }
        if ($event instanceof AnnouncementScheduled && $event->announcementId === 0) {
            return new AnnouncementScheduled($id, $event->actorUserId, $event->scheduledAt, $event->occurredAt);
        }
        if ($event instanceof AnnouncementPublished && $event->announcementId === 0) {
            return new AnnouncementPublished($id, $event->actorUserId, $event->priority, $event->publishedAt, $event->occurredAt);
        }
        if ($event instanceof AnnouncementArchived && $event->announcementId === 0) {
            return new AnnouncementArchived($id, $event->actorUserId, $event->occurredAt);
        }
        if ($event instanceof AnnouncementExpired && $event->announcementId === 0) {
            return new AnnouncementExpired($id, $event->occurredAt);
        }

        return $event;
    }

    /**
     * @param list<AnnouncementTarget> $targets
     */
    private function assertValidTargets(array $targets): void
    {
        if (empty($targets)) {
            throw InvalidAnnouncementTargetException::emptyTargets();
        }

        foreach ($targets as $target) {
            if (!$target instanceof AnnouncementTarget) {
                throw InvalidAnnouncementTargetException::emptyTargets();
            }
        }
    }

    // --- Getters ---

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): AnnouncementTitle
    {
        return $this->title;
    }

    public function content(): AnnouncementContent
    {
        return $this->content;
    }

    public function category(): AnnouncementCategory
    {
        return $this->category;
    }

    public function priority(): AnnouncementPriority
    {
        return $this->priority;
    }

    public function status(): AnnouncementStatus
    {
        return $this->status;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function publicationSchedule(): AnnouncementPublicationSchedule
    {
        return $this->schedule;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    /**
     * @return list<AnnouncementTarget>
     */
    public function targets(): array
    {
        return $this->targets;
    }

    /**
     * @return list<int>
     */
    public function targetedDepartmentIds(): array
    {
        $ids = [];
        foreach ($this->targets as $t) {
            if ($t->isDepartment() && $t->departmentId() !== null) {
                $ids[] = $t->departmentId();
            }
        }

        return array_values(array_unique($ids));
    }

    public function targetsAllEmployees(): bool
    {
        foreach ($this->targets as $t) {
            if ($t->isAllEmployees()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AnnouncementAttachment>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function archivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }
}
