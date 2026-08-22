<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Concerns;

use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Support\BusinessRuleViolation;

/**
 * منطق انتقال حالة إيصال Webhook — الانتقال يمر دائمًا عبر canTransitionTo.
 */
trait TransitionsDeliveryStatus
{
    private function assertDeliveryCanTransition(IntegrationWebhookDelivery $delivery, DeliveryStatus $target): void
    {
        if (!$delivery->status->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'integrations.invalid_delivery_transition',
                'integrations::errors.invalid_delivery_transition',
                [
                    'from' => $delivery->status->value,
                    'to' => $target->value,
                ],
            );
        }
    }
}
