<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\WhatsappMessageReceived;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل رسالة واتساب واردة — يمنع المعالجة المزدوجة عبر message_id الفريد.
 */
final readonly class RecordWhatsappInboundAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        string $organizationId,
        string $fromPhone,
        string $messageId,
        string $body,
        ?CarbonImmutable $receivedAt = null,
        ?array $media = null,
        ?string $matchedUserId = null,
    ): WhatsappInbound {
        $exists = WhatsappInbound::query()
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'messaging.whatsapp_duplicate_message',
                'messaging::errors.whatsapp_duplicate_message',
            );
        }

        $inbound = $this->transaction->run(function () use (
            $organizationId,
            $fromPhone,
            $messageId,
            $body,
            $receivedAt,
            $media,
            $matchedUserId,
        ): WhatsappInbound {
            $record = new WhatsappInbound([
                'organization_id' => $organizationId,
                'from_phone' => $fromPhone,
                'message_id' => $messageId,
                'body' => $body,
                'media' => $media,
                'received_at' => $receivedAt ?? CarbonImmutable::now('UTC'),
                'matched_user_id' => $matchedUserId,
                'handled_by' => null,
                'handled_at' => null,
                'created_at' => now(),
            ]);
            $record->save();

            return $record;
        });

        $this->events->dispatch(new WhatsappMessageReceived(
            inboundId: (string) $inbound->id,
            organizationId: $organizationId,
            fromPhone: $fromPhone,
            matchedUserId: $matchedUserId,
        ));

        return $inbound;
    }
}
