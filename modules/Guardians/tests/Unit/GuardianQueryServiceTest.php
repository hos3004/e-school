<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Testing\Fixtures;

it('returns the verified primary guardian summary for a student', function (): void {
    $guardian = GuardianProfile::factory()->create([
        'preferred_contact_channel' => ContactChannel::WhatsApp,
    ]);
    $studentId = Fixtures::studentProfileId();

    GuardianLink::factory()->primary()->acting()->verified()->create([
        'guardian_profile_id' => $guardian->id,
        'student_profile_id' => $studentId,
        'visible_sections' => ['attendance', 'grades'],
    ]);

    /** @var GuardianQuery $query */
    $query = app(GuardianQuery::class);
    $summary = $query->primaryGuardianForStudent($studentId);

    expect($summary)->not->toBeNull()
        ->and($summary->userId)->toBe($guardian->user_id)
        ->and($summary->isPrimary)->toBeTrue()
        ->and($summary->canActFor)->toBeTrue()
        ->and($summary->verifiedAt)->not->toBeNull()
        ->and($summary->visibleSections)->toEqual(['attendance', 'grades'])
        ->and($summary->preferredContactChannel)->toBe(ContactChannel::WhatsApp);
});

it('returns null when the student has no primary guardian', function (): void {
    $summary = app(GuardianQuery::class)->primaryGuardianForStudent((string) Str::ulid());

    expect($summary)->toBeNull();
});

it('denies acting for a student when verification is required and missing', function (): void {
    config()->set('guardians.links.require_verification_for_acting', true);

    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();
    GuardianLink::factory()->acting()->create([
        'guardian_profile_id' => $guardian->id,
        'student_profile_id' => $studentId,
    ]);

    expect(app(GuardianQuery::class)->userCanActForStudent($guardian->user_id, $studentId))->toBeFalse();
});

it('allows acting for a student once verified', function (): void {
    config()->set('guardians.links.require_verification_for_acting', true);

    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();
    GuardianLink::factory()->acting()->verified()->create([
        'guardian_profile_id' => $guardian->id,
        'student_profile_id' => $studentId,
    ]);

    expect(app(GuardianQuery::class)->userCanActForStudent($guardian->user_id, $studentId))->toBeTrue();
});

it('ignores verification requirement when configured off', function (): void {
    config()->set('guardians.links.require_verification_for_acting', false);

    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();
    GuardianLink::factory()->acting()->create([
        'guardian_profile_id' => $guardian->id,
        'student_profile_id' => $studentId,
    ]);

    expect(app(GuardianQuery::class)->userCanActForStudent($guardian->user_id, $studentId))->toBeTrue();
});
