<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Guardians\Application\Actions\ArchiveGuardianProfile;
use Modules\Guardians\Domain\Events\GuardianProfileArchived;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;

uses(RefreshDatabase::class);

it('archives the profile without deleting its row', function (): void {
    Event::fake([GuardianProfileArchived::class]);

    $profile = GuardianProfile::factory()->create();

    app(ArchiveGuardianProfile::class)->execute($profile->id, 'left the school');

    expect(GuardianProfile::query()->whereKey($profile->id)->exists())->toBeFalse()
        ->and(GuardianProfile::withTrashed()->whereKey($profile->id)->exists())->toBeTrue();

    Event::assertDispatched(GuardianProfileArchived::class, static fn (GuardianProfileArchived $event): bool => $event->guardianProfileId === $profile->id
        && $event->reason === 'left the school');
});

it('revokes acting and primary rights on links after archiving', function (): void {
    $profile = GuardianProfile::factory()->create();
    $actingLink = GuardianLink::factory()->primary()->acting()->verified()
        ->create(['guardian_profile_id' => $profile->id]);
    $passiveLink = GuardianLink::factory()->create(['guardian_profile_id' => $profile->id]);

    app(ArchiveGuardianProfile::class)->execute($profile->id, 'left the school');

    expect($actingLink->refresh()->is_primary)->toBeFalse()
        ->and($actingLink->can_act_for)->toBeFalse()
        ->and($passiveLink->refresh()->can_act_for)->toBeFalse();
});
