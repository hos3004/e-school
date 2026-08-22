<?php

declare(strict_types=1);

use Modules\Integrations\Domain\ValueObjects\GatewayResult;

it('never marks an accepted result as retryable', function (): void {
    $result = GatewayResult::accepted(['provider_id' => 'delivery-1']);

    expect($result->isAccepted())->toBeTrue()
        ->and($result->isRetryable())->toBeFalse()
        ->and($result->error())->toBeNull()
        ->and($result->providerResponse())->toBe(['provider_id' => 'delivery-1']);
});

it('preserves the gateway retry classification on rejection', function (): void {
    $retryable = GatewayResult::rejected('temporary outage', true);
    $permanent = GatewayResult::rejected('invalid recipient', false);

    expect($retryable->isAccepted())->toBeFalse()
        ->and($retryable->isRetryable())->toBeTrue()
        ->and($retryable->error())->toBe('temporary outage')
        ->and($permanent->isRetryable())->toBeFalse();
});
