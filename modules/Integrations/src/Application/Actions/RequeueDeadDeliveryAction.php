<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Modules\Integrations\Application\Concerns\TransitionsDeliveryStatus;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة إحياء إيصال ميت (Dead) — إعادة يدوية بقرار مسؤول التكاملات.
 */
final readonly class RequeueDeadDeliveryAction
{
    use TransitionsDeliveryStatus;

    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(IntegrationWebhookDelivery $delivery, ?string $actorId = null): IntegrationWebhookDelivery
    {
        if ($delivery->status !== DeliveryStatus::Dead) {
            throw BusinessRuleViolation::make(
                'integrations.only_dead_can_requeue',
                'integrations::errors.only_dead_can_requeue',
                ['status' => $delivery->status->value],
            );
        }

        $this->assertDeliveryCanTransition($delivery, DeliveryStatus::Retrying);

        $this->transaction->run(function () use ($delivery): void {
            $delivery->update([
                'status' => DeliveryStatus::Retrying,
                'next_retry_at' => now(),
                'failed_at' => null,
            ]);
        });

        return $delivery->refresh();
    }
}
