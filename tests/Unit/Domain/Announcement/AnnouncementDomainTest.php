<?php

/**
 * Announcement Domain Unit Test Suite (TDD Phase 13.1)
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Announcement;

use App\Domain\Announcement\Entities\Announcement;
use App\Domain\Announcement\Entities\AnnouncementAttachment;
use App\Domain\Announcement\Entities\AnnouncementReadReceipt;
use App\Domain\Announcement\Entities\AnnouncementTarget;
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
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AnnouncementDomainTest extends TestCase
{
    public function test_valid_draft_announcement_creation_and_event_recording(): void
    {
        $now = new DateTimeImmutable('2026-11-16 09:00:00');
        $announcement = Announcement::createDraft(
            title: AnnouncementTitle::fromString('2026 Year-End Town Hall & Benefits Briefing'),
            content: AnnouncementContent::fromString('All active personnel are invited to attend the annual company briefing.'),
            category: AnnouncementCategory::fromString(AnnouncementCategory::EVENTS_AND_ACTIVITIES),
            priority: AnnouncementPriority::important(),
            authorId: 2,
            targets: [AnnouncementTarget::allEmployees()],
            isPinned: true,
            expiresAt: ExpirationDate::fromString('2026-12-31 18:00:00'),
            now: $now
        );

        $this->assertTrue($announcement->status()->isDraft());
        $this->assertSame('2026 Year-End Town Hall & Benefits Briefing', $announcement->title()->value());
        $this->assertSame(AnnouncementCategory::EVENTS_AND_ACTIVITIES, $announcement->category()->value());
        $this->assertSame(AnnouncementPriority::IMPORTANT, $announcement->priority()->value());
        $this->assertSame(2, $announcement->priority()->weight());
        $this->assertTrue($announcement->isPinned());
        $this->assertTrue($announcement->targetsAllEmployees());
        $this->assertFalse($announcement->isVisibleInEmployeeFeed($now), 'Drafts must never appear in the employee feed.');

        $announcement->assignPersistedId(42);
        $events = $announcement->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AnnouncementCreated::class, $events[0]);
        $this->assertSame(42, $events[0]->announcementId);
    }

    public function test_invalid_announcement_title_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementTitle::fromString('  ');
    }

    public function test_too_short_announcement_title_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementTitle::fromString('Hi');
    }

    public function test_too_long_announcement_title_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementTitle::fromString(str_repeat('A', 201));
    }

    public function test_empty_announcement_content_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementContent::fromString('   ');
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementPriority::fromString('SuperUrgent');
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AnnouncementCategory::fromString('Random Unapproved Category');
    }

    public function test_invalid_publication_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicationDate::fromString('not-a-valid-date');
    }

    public function test_invalid_expiration_date_before_publication_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AnnouncementPublicationSchedule(
            scheduledAt: PublicationDate::fromString('2026-11-20 12:00:00'),
            publishedAt: null,
            expiresAt: ExpirationDate::fromString('2026-11-19 12:00:00')
        );
    }

    public function test_empty_targets_are_rejected(): void
    {
        $this->expectException(InvalidAnnouncementTargetException::class);
        Announcement::createDraft(
            title: AnnouncementTitle::fromString('Policy Notice'),
            content: AnnouncementContent::fromString('Updated workplace attendance policy details.'),
            category: AnnouncementCategory::fromString(AnnouncementCategory::HR_POLICY_UPDATES),
            priority: AnnouncementPriority::normal(),
            authorId: 2,
            targets: []
        );
    }

    public function test_scheduled_announcement_visibility_and_publication_lifecycle(): void
    {
        $now = new DateTimeImmutable('2026-11-16 09:00:00');
        $scheduledTime = PublicationDate::fromString('2026-11-17 08:00:00');
        $expiresTime = ExpirationDate::fromString('2026-11-30 18:00:00');

        $announcement = Announcement::createDraft(
            title: AnnouncementTitle::fromString('Scheduled IT Maintenance Advisory'),
            content: AnnouncementContent::fromString('Core HR portal maintenance window scheduled this weekend.'),
            category: AnnouncementCategory::fromString(AnnouncementCategory::OFFICE_OPERATIONS),
            priority: AnnouncementPriority::urgent(),
            authorId: 2,
            targets: [AnnouncementTarget::forDepartment(1, 'Information Technology')],
            isPinned: false,
            expiresAt: $expiresTime,
            now: $now
        );
        $announcement->assignPersistedId(10);
        $announcement->releaseEvents();

        // Schedule for future
        $announcement->schedule($scheduledTime, 2, $expiresTime, $now);
        $this->assertTrue($announcement->status()->isScheduled());
        $this->assertFalse($announcement->isVisibleInEmployeeFeed($now));
        $this->assertFalse(
            $announcement->isVisibleInEmployeeFeed(new DateTimeImmutable('2026-11-17 09:00:00')),
            'Scheduled announcement must not appear in feed until formally transitioned to Published.'
        );

        $schedEvents = $announcement->releaseEvents();
        $this->assertCount(1, $schedEvents);
        $this->assertInstanceOf(AnnouncementScheduled::class, $schedEvents[0]);

        // Publish when due
        $publishClock = new DateTimeImmutable('2026-11-17 08:00:05');
        $announcement->publish(2, $publishClock);
        $this->assertTrue($announcement->status()->isPublished());
        $this->assertTrue($announcement->isVisibleInEmployeeFeed($publishClock));

        // Audience check: Dept 1 Active employee can access; Dept 2 or Inactive cannot
        $this->assertTrue($announcement->isAccessibleByEmployee(1, 'Active', $publishClock));
        $this->assertFalse($announcement->isAccessibleByEmployee(2, 'Active', $publishClock));
        $this->assertFalse($announcement->isAccessibleByEmployee(1, 'Inactive', $publishClock));

        $pubEvents = $announcement->releaseEvents();
        $this->assertCount(1, $pubEvents);
        $this->assertInstanceOf(AnnouncementPublished::class, $pubEvents[0]);
    }

    public function test_duplicate_publication_is_prevented(): void
    {
        $now = new DateTimeImmutable('2026-11-16 09:00:00');
        $announcement = Announcement::createDraft(
            title: AnnouncementTitle::fromString('Immediate Company Update'),
            content: AnnouncementContent::fromString('Important quarterly business review announcement.'),
            category: AnnouncementCategory::fromString(AnnouncementCategory::COMPANY_UPDATES),
            priority: AnnouncementPriority::normal(),
            authorId: 2,
            targets: [AnnouncementTarget::allEmployees()],
            now: $now
        );
        $announcement->publish(2, $now);

        $this->expectException(InvalidAnnouncementTransitionException::class);
        $announcement->publish(2, $now);
    }

    public function test_expired_announcement_behavior_and_archive_invariants(): void
    {
        $now = new DateTimeImmutable('2026-11-16 09:00:00');
        $expiresAt = ExpirationDate::fromString('2026-11-18 00:00:00');

        $announcement = Announcement::createDraft(
            title: AnnouncementTitle::fromString('Short-Term Weather Advisory'),
            content: AnnouncementContent::fromString('Typhoon signal advisory for main office personnel.'),
            category: AnnouncementCategory::fromString(AnnouncementCategory::EMERGENCY_ADVISORY),
            priority: AnnouncementPriority::urgent(),
            authorId: 2,
            targets: [AnnouncementTarget::allEmployees()],
            isPinned: true,
            expiresAt: $expiresAt,
            now: $now
        );
        $announcement->assignPersistedId(15);
        $announcement->publish(2, $now);
        $announcement->releaseEvents();

        // Visible before expiration
        $this->assertTrue($announcement->isVisibleInEmployeeFeed(new DateTimeImmutable('2026-11-17 12:00:00')));

        // Automatically hidden from feed once expiration timestamp passes, even before CRON runs
        $afterExpiry = new DateTimeImmutable('2026-11-18 00:01:00');
        $this->assertFalse($announcement->isVisibleInEmployeeFeed($afterExpiry));

        // Formal CRON transition to Expired
        $announcement->expire($afterExpiry);
        $this->assertTrue($announcement->status()->isExpired());
        $expEvents = $announcement->releaseEvents();
        $this->assertCount(1, $expEvents);
        $this->assertInstanceOf(AnnouncementExpired::class, $expEvents[0]);

        // Archive
        $announcement->archive(2, $afterExpiry);
        $this->assertTrue($announcement->status()->isArchived());
        $this->assertFalse($announcement->isVisibleInEmployeeFeed($afterExpiry));

        // Archived announcement cannot be edited or re-published
        $this->expectException(InvalidAnnouncementTransitionException::class);
        $announcement->publish(2, $afterExpiry);
    }

    public function test_attachments_and_read_receipt_entities_validate_invariants(): void
    {
        $att = new AnnouncementAttachment(
            id: 1,
            announcementId: 10,
            originalFilename: 'memo.pdf',
            storedFilename: 'anndoc-10-abcdef.pdf',
            storageRelativePath: '2026/11/anndoc-10-abcdef.pdf',
            mimeType: 'application/pdf',
            fileSize: 20480,
            sha256Checksum: str_repeat('a', 64),
            uploadedBy: 2,
            createdAt: new DateTimeImmutable()
        );
        $this->assertSame('memo.pdf', $att->originalFilename());
        $this->assertSame(20480, $att->fileSize());

        $receipt = new AnnouncementReadReceipt(
            id: 5,
            announcementId: 10,
            employeeId: 4,
            readAt: new DateTimeImmutable('2026-11-16 10:00:00')
        );
        $this->assertSame(10, $receipt->announcementId());
        $this->assertSame(4, $receipt->employeeId());
    }
}
