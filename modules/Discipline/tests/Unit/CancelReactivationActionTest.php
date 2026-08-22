<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Models\User;
use Modules\Discipline\Application\Actions\CancelReactivationAction;
use Modules\Discipline\Database\Factories\ReactivationRequestFactory;
use Modules\Discipline\Domain\Enums\ReactivationStatus;

uses(RefreshDatabase::class);

it('lets the requester cancel a pending request', function () {
    $this->actingAs(User::factory()->create());

    $request = ReactivationRequestFactory::new()->create();

    $cancelled = app(CancelReactivationAction::class)->execute($request);

    expect($cancelled->status)->toBe(ReactivationStatus::Cancelled);
});

it('refuses to cancel an already decided request', function () {
    $reviewerId = (string) User::factory()->create()->getKey();

    $request = ReactivationRequestFactory::new()->approved($reviewerId, (string) str()->ulid())->create();

    app(CancelReactivationAction::class)->execute($request);
})->throws(Shared\Support\BusinessRuleViolation::class);
