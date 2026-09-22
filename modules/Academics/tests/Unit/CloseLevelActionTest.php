<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Academics\Application\Actions\CloseLevelAction;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Application\Actions\ReopenLevelAction;
use Modules\Academics\Domain\Events\LevelClosed;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Models\AuditLog;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function levelClosureSnapshot(array $blockers = []): ClosureSnapshot
{
    return new ClosureSnapshot(
        summary: [
            'kind' => 'level',
            'captured_at' => '2026-09-22T00:00:00+00:00',
            'courses_total' => 3,
            'sessions_total' => 18,
        ],
        blockers: $blockers,
    );
}

it('closes a level and stamps the audit entry with the organization inherited from its program', function (): void {
    Event::fake([LevelClosed::class]);

    $actorId = (string) Str::ulid();
    $program = Program::factory()->create();
    $level = Level::factory()->for($program, 'program')->create();

    $closed = app(CloseLevelAction::class)->execute($level, 'المستوى التمهيدي انتهى', levelClosureSnapshot(), $actorId);

    expect($closed->closed_at)->not->toBeNull()
        ->and($closed->closure_summary['courses_total'])->toBe(3);

    $entry = AuditLog::query()
        ->where('action', 'academics.level_closed')
        ->where('auditable_id', (string) $level->getKey())
        ->sole();

    // المستوى بلا organization_id خاص به؛ القيدة يجب أن تحمل مؤسسة البرنامج.
    expect($entry->organization_id)->toBe((string) $program->organization_id)
        ->and($entry->reason)->toBe('المستوى التمهيدي انتهى');

    Event::assertDispatched(LevelClosed::class);
});

it('refuses to close a level that still has an active course, and leaves it open', function (): void {
    $level = Level::factory()->create();

    $attempt = fn (): mixed => app(CloseLevelAction::class)->execute(
        $level,
        'محاولة مبكرة',
        levelClosureSnapshot(['courses_active' => 2]),
        (string) Str::ulid(),
    );

    expect($attempt)->toThrow(BusinessRuleViolation::class);
    expect($level->fresh()->closed_at)->toBeNull();
});

it('refuses to close a level without a reason', function (): void {
    $level = Level::factory()->create();

    app(CloseLevelAction::class)->execute($level, '  ', levelClosureSnapshot(), (string) Str::ulid());
})->throws(BusinessRuleViolation::class);

it('reopens a closed level and keeps the frozen summary in the audit trail', function (): void {
    $actorId = (string) Str::ulid();
    $level = Level::factory()->create();

    app(CloseLevelAction::class)->execute($level, 'انتهى', levelClosureSnapshot(), $actorId);
    $reopened = app(ReopenLevelAction::class)->execute($level->fresh(), 'دفعة جديدة', $actorId);

    expect($reopened->closed_at)->toBeNull()
        ->and($reopened->closure_summary)->toBeNull();

    $entry = AuditLog::query()
        ->where('action', 'academics.level_reopened')
        ->where('auditable_id', (string) $level->getKey())
        ->sole();

    expect($entry->old_values['closure_summary']['sessions_total'])->toBe(18);
});

it('keeps closed levels out of the open scope but still reachable', function (): void {
    $open = Level::factory()->create();
    $closing = Level::factory()->create();

    app(CloseLevelAction::class)->execute($closing, 'انتهى', levelClosureSnapshot(), (string) Str::ulid());

    expect(Level::query()->open()->pluck('id')->all())->toContain((string) $open->getKey())
        ->and(Level::query()->open()->pluck('id')->all())->not->toContain((string) $closing->getKey())
        ->and(Level::query()->closed()->pluck('id')->all())->toContain((string) $closing->getKey())
        ->and(Level::query()->whereKey($closing->getKey())->exists())->toBeTrue();
});

it('refuses to create a course under an archived level, and says how to proceed', function (): void {
    $level = Level::factory()->create();
    app(CloseLevelAction::class)->execute($level, 'انتهى', levelClosureSnapshot(), (string) Str::ulid());

    $attempt = fn (): mixed => app(CreateCourseAction::class)->execute([
        'organization_id' => (string) $level->program->organization_id,
        'level_id' => (string) $level->getKey(),
        'code' => 'CRS-CLOSED-1',
        'name' => ['ar' => 'كورس', 'en' => 'Course'],
        'total_sessions' => 8,
    ], (string) Str::ulid(), 'محاولة');

    expect($attempt)->toThrow(BusinessRuleViolation::class);

    // بدون هذا الحارس كان الكورس يُنشأ ثم يختفي فورًا خلف مستوى مؤرشَف.
    expect(Course::query()->where('code', 'CRS-CLOSED-1')->exists())->toBeFalse();
});
