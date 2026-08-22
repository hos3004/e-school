<?php

declare(strict_types=1);

namespace Modules\Reporting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Reporting\Application\Actions\IngestDomainEventAction;
use Modules\Reporting\Application\Actions\UpdateStudentDashboardAction;
use Modules\Reporting\Application\Actions\UpdateTeacherDashboardAction;
use Modules\Reporting\Application\Listeners\ProjectDomainEventToDashboards;
use Modules\Reporting\Domain\Events\StudentDashboardUpdated;
use Modules\Reporting\Domain\Models\ReportEventLog;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Shared\Domain\DomainEvent;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * حدث مصدر تجريبي محلي — يقلّد شكل أحداث الموديولات الأخرى
 * بلا استيراد أي نموذج أو صنف منها.
 */
final class FakeSessionsCompleted extends DomainEvent
{
    public function __construct(
        public readonly string $orgId,
        public readonly string $enrollmentId,
        public readonly string $studentProfileId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'sessions.completed';
    }

    public function module(): string
    {
        return 'sessions';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->orgId,
            'enrollment_id' => $this->enrollmentId,
            'student_profile_id' => $this->studentProfileId,
            'completed_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}

it('projects a foreign event into the student dashboard once', function (): void {
    config()->set('reporting.projections', [
        'sessions.completed' => [
            ['board' => 'student', 'metric' => 'sessions_completed', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => 'completed_at'],
        ],
    ]);

    Event::fake([StudentDashboardUpdated::class]);

    $event = new FakeSessionsCompleted(
        orgId: Fixtures::organizationId(),
        enrollmentId: (string) str()->ulid(),
        studentProfileId: Fixtures::studentProfileId(),
    );

    $listener = app(ProjectDomainEventToDashboards::class);
    $listener->handle($event);

    expect(StudentDashboard::query()->count())->toBe(1)
        ->and((int) StudentDashboard::query()->sole()->sessions_total)->toBe(1)
        ->and(ReportEventLog::query()->where('event_id', $event->eventId)->exists())->toBeTrue();

    Event::assertDispatched(StudentDashboardUpdated::class);

    // إعادة تسليم نفس الحدث لا تكرر الأثر — idempotency.
    $listener->handle($event);

    expect((int) StudentDashboard::query()->sole()->sessions_total)->toBe(1)
        ->and(ReportEventLog::query()->count())->toBe(1);
});

it('skips the projection when the ingest gate rejects duplicates', function (): void {
    config()->set('reporting.projections', [
        'sessions.completed' => [
            ['board' => 'student', 'metric' => 'sessions_completed', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => 'completed_at'],
        ],
    ]);

    Event::fake();

    $ingest = app(IngestDomainEventAction::class);
    $student = app(UpdateStudentDashboardAction::class);
    $teacher = app(UpdateTeacherDashboardAction::class);

    $listener = new ProjectDomainEventToDashboards($ingest, $student, $teacher);

    $event = new FakeSessionsCompleted(
        orgId: Fixtures::organizationId(),
        enrollmentId: (string) str()->ulid(),
        studentProfileId: Fixtures::studentProfileId(),
    );

    Gate::define('reporting.student.view_any', fn (): bool => true);

    $listener->handle($event);
    $listener->handle($event);

    expect(ReportEventLog::query()->count())->toBe(1)
        ->and(StudentDashboard::query()->count())->toBe(1)
        ->and((int) StudentDashboard::query()->sole()->sessions_total)->toBe(1);
});

it('routes teacher metrics by the config map', function (): void {
    config()->set('reporting.projections', [
        'payroll.entry_recorded' => [
            ['board' => 'teacher', 'metric' => 'payout_credited', 'keys' => ['staff_profile_id'], 'amount_minor' => 'amount_minor'],
        ],
    ]);

    Event::fake();

    $staffId = Fixtures::staffProfileId();
    $orgId = Fixtures::organizationId();

    $ingest = app(IngestDomainEventAction::class);
    $listener = new ProjectDomainEventToDashboards(
        $ingest,
        app(UpdateStudentDashboardAction::class),
        app(UpdateTeacherDashboardAction::class),
    );

    $sourceEvent = new class($orgId, $staffId) extends DomainEvent
    {
        public function __construct(
            private readonly string $orgId,
            private readonly string $staffId,
        ) {
            parent::__construct();
        }

        public function name(): string
        {
            return 'payroll.entry_recorded';
        }

        public function module(): string
        {
            return 'payroll';
        }

        public function payload(): array
        {
            return [
                'organization_id' => $this->orgId,
                'staff_profile_id' => $this->staffId,
                'amount_minor' => 45000,
            ];
        }
    };

    $listener->handle($sourceEvent);

    expect(TeacherDashboard::query()->count())->toBe(1)
        ->and((int) TeacherDashboard::query()->sole()->payout_minor)->toBe(45000)
        ->and(ReportEventLog::query()->count())->toBe(1);
});
