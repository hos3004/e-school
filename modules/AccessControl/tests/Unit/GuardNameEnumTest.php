<?php

declare(strict_types=1);

use Modules\AccessControl\Domain\Enums\GuardName;

it('exposes the known guards with stable values', function (): void {
    expect(GuardName::all())->toBe([GuardName::Web, GuardName::Api])
        ->and(GuardName::Web->value)->toBe('web')
        ->and(GuardName::Api->value)->toBe('api');
});

it('labels every guard through translations', function (): void {
    foreach (GuardName::all() as $guard) {
        expect($guard->label())->not->toBe('accesscontrol::enums.guards.'.$guard->value);
    }
});
