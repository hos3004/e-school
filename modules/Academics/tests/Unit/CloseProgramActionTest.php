<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Academics\Application\Actions\CloseProgramAction;
use Modules\Academics\Application\Actions\ReopenProgramAction;
use Modules\Academics\Domain\Events\ProgramClosed;
use Modules\Academics\Domain\Events\ProgramReopened;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Models\AuditLog;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function programClosureSnapshot(array $blockers = []): ClosureSnapshot
{
    return new ClosureSnapshot(
        summary: [
            'kind' => 'program',
            'captured_at' => '2026-09-21T00:00:00+00:00',
            'sessions_total' => 42,
            'students_distinct' => 7,
        ],
        blockers: $blockers,
    );
}

it('closes a program, freezes its summary, and leaves the row itself untouched', function (): void {
    Event::fake([ProgramClosed::class]);

    $actorId = (string) Str::ulid();
    $program = Program::factory()->create();

    $closed = app(CloseProgramAction::class)->execute(
        $program,
        'البرنامج الصيفي انتهى',
        programClosureSnapshot(),
        $actorId,
    );

    expect($closed->closed_at)->not->toBeNull()
        ->and($closed->closed_by)->toBe($actorId)
        ->and($closed->closure_reason)->toBe('البرنامج الصيفي انتهى')
        ->and($closed->closure_summary['sessions_total'])->toBe(42)
        // الإقفال أرشفة لا حذف: لا يُمسّ الحذف الناعم ولا يختفي الصف عن الاستعلامات.
        ->and($closed->trashed())->toBeFalse()
        ->and(Program::query()->whereKey($program->getKey())->exists())->toBeTrue();

    Event::assertDispatched(
        ProgramClosed::class,
        fn (ProgramClosed $event): bool => $event->programId === (string) $program->getKey(),
    );
});

it('records the frozen summary in the audit trail', function (): void {
    $actorId = (string) Str::ulid();
    $program = Program::factory()->create();

    app(CloseProgramAction::class)->execute($program, 'انتهى', programClosureSnapshot(), $actorId);

    $entry = AuditLog::query()
        ->where('action', 'academics.program_closed')
        ->where('auditable_id', (string) $program->getKey())
        ->sole();

    expect($entry->reason)->toBe('انتهى')
        ->and($entry->new_values['closure_summary']['students_distinct'])->toBe(7)
        ->and($entry->actor_id)->toBe($actorId);
});

it('refuses to close a program without a reason', function (): void {
    $program = Program::factory()->create();

    app(CloseProgramAction::class)->execute($program, '   ', programClosureSnapshot(), (string) Str::ulid());
})->throws(BusinessRuleViolation::class);

it('refuses to close a program that still has live work, and leaves it open', function (): void {
    $program = Program::factory()->create();

    $attempt = fn (): mixed => app(CloseProgramAction::class)->execute(
        $program,
        'محاولة مبكرة',
        programClosureSnapshot(['enrollments_live' => 3, 'sessions_open' => 2]),
        (string) Str::ulid(),
    );

    expect($attempt)->toThrow(BusinessRuleViolation::class);

    expect($program->fresh()->closed_at)->toBeNull();
});

it('names what blocks the closure so the admin knows how to proceed', function (): void {
    $program = Program::factory()->create();

    try {
        app(CloseProgramAction::class)->execute(
            $program,
            'محاولة مبكرة',
            programClosureSnapshot(['enrollments_live' => 3]),
            (string) Str::ulid(),
        );
    } catch (BusinessRuleViolation $violation) {
        expect($violation->getMessage())->toContain('3');

        return;
    }

    $this->fail('كان يجب رفض الإقفال.');
});

it('refuses to close a program twice', function (): void {
    $program = Program::factory()->create();
    $actorId = (string) Str::ulid();

    app(CloseProgramAction::class)->execute($program, 'انتهى', programClosureSnapshot(), $actorId);
    app(CloseProgramAction::class)->execute($program, 'مرة أخرى', programClosureSnapshot(), $actorId);
})->throws(BusinessRuleViolation::class);

it('reopens a closed program and keeps the old summary readable in the audit trail', function (): void {
    Event::fake([ProgramReopened::class]);

    $actorId = (string) Str::ulid();
    $program = Program::factory()->create();

    app(CloseProgramAction::class)->execute($program, 'انتهى', programClosureSnapshot(), $actorId);

    $reopened = app(ReopenProgramAction::class)->execute($program->fresh(), 'عاد بدفعة جديدة', $actorId);

    expect($reopened->closed_at)->toBeNull()
        ->and($reopened->closure_summary)->toBeNull();

    $entry = AuditLog::query()
        ->where('action', 'academics.program_reopened')
        ->where('auditable_id', (string) $program->getKey())
        ->sole();

    expect($entry->old_values['closure_summary']['sessions_total'])->toBe(42);

    Event::assertDispatched(ProgramReopened::class);
});

it('refuses to reopen a program that is not closed', function (): void {
    $program = Program::factory()->create();

    app(ReopenProgramAction::class)->execute($program, 'بلا سبب حقيقي', (string) Str::ulid());
})->throws(BusinessRuleViolation::class);

it('keeps closed programs out of the open scope but still reachable', function (): void {
    $open = Program::factory()->create();
    $closing = Program::factory()->create();

    app(CloseProgramAction::class)->execute($closing, 'انتهى', programClosureSnapshot(), (string) Str::ulid());

    expect(Program::query()->open()->pluck('id')->all())->toContain((string) $open->getKey())
        ->and(Program::query()->open()->pluck('id')->all())->not->toContain((string) $closing->getKey())
        ->and(Program::query()->closed()->pluck('id')->all())->toContain((string) $closing->getKey());
});
