<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\ArchiveProgramAction;
use Modules\Academics\Domain\Events\ProgramArchived;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

uses(RefreshDatabase::class);

it('archives a program and publishes ProgramArchived with the reason', function () {
    Event::fake([ProgramArchived::class]);

    $program = Program::factory()->create();

    $archived = app(ArchiveProgramAction::class)->execute($program, 'إيقاف البرنامج نهائيًا');

    expect($archived->trashed())->toBeTrue();

    Event::assertDispatched(
        ProgramArchived::class,
        fn (ProgramArchived $event): bool => $event->programId === (string) $program->getKey()
            && $event->reason === 'إيقاف البرنامج نهائيًا'
    );
});

it('refuses to archive a program that still has active courses', function () {
    $program = Program::factory()->create();
    Level::factory()->for($program, 'program')->create();
    Course::factory()->create([
        'organization_id' => $program->organization_id,
        'level_id' => Level::query()->where('program_id', $program->getKey())->first()->getKey(),
    ]);

    app(ArchiveProgramAction::class)->execute($program, 'محاولة مرفوضة');
})->throws(Shared\Support\BusinessRuleViolation::class);
