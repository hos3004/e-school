<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Guardians\Application\Actions\CreateGuardianProfile;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Events\GuardianProfileCreated;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

it('creates a guardian profile and dispatches GuardianProfileCreated', function (): void {
    Event::fake([GuardianProfileCreated::class]);

    $action = app(CreateGuardianProfile::class);

    $profile = $action->execute([
        'organization_id' => (string) Illuminate\Support\Str::ulid(),
        'user_id' => (string) Illuminate\Support\Str::ulid(),
        'national_id_last4' => '1234',
        'occupation' => 'engineer',
        'preferred_contact_channel' => ContactChannel::WhatsApp->value,
    ]);

    expect($profile->exists)->toBeTrue()
        ->and($profile->preferred_contact_channel)->toBe(ContactChannel::WhatsApp);

    Event::assertDispatched(GuardianProfileCreated::class, static fn (GuardianProfileCreated $event): bool => $event->guardianProfileId === $profile->id
        && $event->userId === $profile->user_id);
});

it('rejects a second profile for the same user', function (): void {
    $profile = GuardianProfile::factory()->create();

    $action = app(CreateGuardianProfile::class);

    try {
        $action->execute([
            'organization_id' => $profile->organization_id,
            'user_id' => $profile->user_id,
        ]);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('guardians.profile_already_exists');
    }

    expect(GuardianProfile::query()->where('user_id', $profile->user_id)->count())->toBe(1);
});
