<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Concerns;

use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Shared\Support\BusinessRuleViolation;

/**
 * منطق انتقال حالة الاتصال المشترك بين الإجراءات — الانتقال يمر دائمًا
 * عبر canTransitionTo وإلا رُفض بخرق قاعدة عمل.
 */
trait TransitionsConnectionStatus
{
    private function assertCanTransition(IntegrationConnection $connection, ConnectionStatus $target): void
    {
        if (!$connection->status->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'integrations.invalid_status_transition',
                'integrations::errors.invalid_status_transition',
                [
                    'from' => $connection->status->value,
                    'to' => $target->value,
                ],
            );
        }
    }
}
