<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Guardians\Application\Actions\SetPrimaryGuardianLink;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

it('moves primary guardianship to the chosen link', function (): void {
    $studentId = Fixtures::studentProfileId();

    $oldPrimary = GuardianLink::factory()->primary()->create(['student_profile_id' => $studentId]);
    $newPrimary = GuardianLink::factory()->create(['student_profile_id' => $studentId]);

    app(SetPrimaryGuardianLink::class)->execute($newPrimary->id);

    expect($newPrimary->refresh()->is_primary)->toBeTrue()
        ->and($oldPrimary->refresh()->is_primary)->toBeFalse();
});

it('is a no-op when the link is already primary', function (): void {
    $link = GuardianLink::factory()->primary()->create();

    $result = app(SetPrimaryGuardianLink::class)->execute($link->id);

    expect($result->is_primary)->toBeTrue()
        ->and(GuardianLink::query()->forStudent($link->student_profile_id)->primary()->count())->toBe(1);
});
