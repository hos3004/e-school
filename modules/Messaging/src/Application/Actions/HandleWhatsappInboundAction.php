<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\WhatsappMessageHandled;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعامل موظف مع رسالة واتساب واردة — لا معالجة مرتين.
 */
final readonly class HandleWhatsappInboundAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(WhatsappInbound $inbound, string $handlerUserId): WhatsappInbound
    {
        if ($inbound->handled_at !== null) {
            throw BusinessRuleViolation::make(
                'messaging.whatsapp_already_handled',
                'messaging::errors.whatsapp_already_handled',
            );
        }

        $handled = $this->transaction->run(function () use ($inbound, $handlerUserId): WhatsappInbound {
            $inbound->handled_by = $handlerUserId;
            $inbound->handled_at = CarbonImmutable::now('UTC');
            $inbound->save();

            return $inbound;
        });

        $this->events->dispatch(new WhatsappMessageHandled(
            inboundId: (string) $handled->id,
            organizationId: (string) $handled->organization_id,
            handledByUserId: $handlerUserId,
        ));

        return $handled;
    }
}
