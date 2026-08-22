<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Guardians\Application\Actions\VerifyGuardianLink;
use Modules\Guardians\Domain\Events\GuardianLinkVerified;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Support\BusinessRuleViolation;

it('verifies a link and dispatches GuardianLinkVerified', function (): void {
    Event::fake([GuardianLinkVerified::class]);

    $link = GuardianLink::factory()->create();

    $verified = app(VerifyGuardianLink::class)->execute($link->id);

    expect($verified->verified_at)->not->toBeNull();

    Event::assertDispatched(GuardianLinkVerified::class, static fn (GuardianLinkVerified $event): bool => $event->guardianLinkId === $link->id);
});

it('rejects verifying an already verified link', function (): void {
    $link = GuardianLink::factory()->verified()->create();

    try {
        app(VerifyGuardianLink::class)->execute($link->id);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('guardians.link_already_verified');
    }
});
