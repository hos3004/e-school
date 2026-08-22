<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shared\Support\BusinessRuleViolation;

it('renders business rule violations with the documented API contract', function (): void {
    Route::get('/_testing/business-rule-violation', static function (): never {
        throw new BusinessRuleViolation(
            rule: 'testing.rule_violation',
            message: 'Readable failure message.',
            context: ['limit' => 3],
        );
    });

    $correlationId = '01K35BUSINESSRULETEST000000';

    $this->withHeader('X-Correlation-Id', $correlationId)
        ->getJson('/_testing/business-rule-violation')
        ->assertUnprocessable()
        ->assertHeader('X-Correlation-Id', $correlationId)
        ->assertExactJson([
            'error' => [
                'code' => 'testing.rule_violation',
                'message' => 'Readable failure message.',
                'details' => ['limit' => 3],
            ],
            'meta' => [
                'correlation_id' => $correlationId,
            ],
        ]);
});
