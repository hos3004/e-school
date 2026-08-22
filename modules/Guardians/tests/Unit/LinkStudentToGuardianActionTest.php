<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Events\GuardianLinkedToStudent;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

it('links a student to a guardian and dispatches GuardianLinkedToStudent', function (): void {
    Event::fake([GuardianLinkedToStudent::class]);

    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();

    $link = app(LinkStudentToGuardian::class)->execute($guardian->id, $studentId, [
        'relationship' => GuardianRelationship::Father,
        'is_primary' => true,
        'can_act_for' => true,
        'visible_sections' => ['attendance', 'billing', 'hacker_section'],
    ]);

    expect($link->exists)->toBeTrue()
        ->and($link->relationship)->toBe(GuardianRelationship::Father)
        ->and($link->is_primary)->toBeTrue()
        ->and($link->can_act_for)->toBeTrue()
        ->and($link->visible_sections)->toEqual(['attendance', 'billing']);

    Event::assertDispatched(GuardianLinkedToStudent::class, static fn (GuardianLinkedToStudent $event): bool => $event->guardianLinkId === $link->id
        && $event->studentProfileId === $studentId
        && $event->relationship === GuardianRelationship::Father);
});

it('defaults visible sections to the configured defaults', function (): void {
    $guardian = GuardianProfile::factory()->create();
    /** @var list<string> $defaults */
    $defaults = config('guardians.links.default_visible_sections');

    $link = app(LinkStudentToGuardian::class)->execute(
        $guardian->id,
        Fixtures::studentProfileId(),
        ['relationship' => GuardianRelationship::Mother],
    );

    expect($link->visible_sections)->toEqual($defaults);
});

it('rejects linking the same student twice to the same guardian', function (): void {
    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();

    $action = app(LinkStudentToGuardian::class);
    $action->execute($guardian->id, $studentId, ['relationship' => GuardianRelationship::Father]);

    try {
        $action->execute($guardian->id, $studentId, ['relationship' => GuardianRelationship::Uncle]);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('guardians.link_already_exists');
    }

    expect(GuardianLink::query()->forStudent($studentId)->count())->toBe(1);
});

it('enforces the configured maximum of guardians per student', function (): void {
    config()->set('guardians.limits.max_links_per_student', 2);

    $studentId = Fixtures::studentProfileId();
    $action = app(LinkStudentToGuardian::class);

    GuardianLink::factory()->create(['student_profile_id' => $studentId]);
    GuardianLink::factory()->create(['student_profile_id' => $studentId]);

    try {
        $action->execute(
            GuardianProfile::factory()->create()->id,
            $studentId,
            ['relationship' => GuardianRelationship::Aunt],
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('guardians.max_links_per_student_reached')
            ->and($violation->context['max'])->toBe(2);
    }
});

it('enforces the configured maximum of students per guardian', function (): void {
    config()->set('guardians.limits.max_students_per_guardian', 1);

    $guardian = GuardianProfile::factory()->create();
    GuardianLink::factory()->create(['guardian_profile_id' => $guardian->id]);

    $action = app(LinkStudentToGuardian::class);

    try {
        $action->execute(
            $guardian->id,
            Fixtures::studentProfileId(),
            ['relationship' => GuardianRelationship::Uncle],
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('guardians.max_students_per_guardian_reached');
    }
});

it('keeps only one primary guardian per student', function (): void {
    $studentId = Fixtures::studentProfileId();

    $firstPrimary = GuardianLink::factory()->primary()->create(['student_profile_id' => $studentId]);

    app(LinkStudentToGuardian::class)->execute(
        GuardianProfile::factory()->create()->id,
        $studentId,
        [
            'relationship' => GuardianRelationship::Mother,
            'is_primary' => true,
        ],
    );

    expect($firstPrimary->refresh()->is_primary)->toBeFalse()
        ->and(GuardianLink::query()->forStudent($studentId)->primary()->count())->toBe(1);
});
