<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Contracts\ProviderDeliveryStatusRecorder;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

final readonly class GreenApiDeliveryStatusRecorder implements ProviderDeliveryStatusRecorder
{
    public function record(string $organizationId, string $externalMessageId, string $status, string $description): void
    {
        if ($externalMessageId === '' || !in_array($status, [
            'sent', 'delivered', 'read', 'failed', 'noAccount', 'notInGroup', 'suspended', 'yellowCard',
        ], true)) {
            return;
        }

        DB::transaction(function () use ($organizationId, $externalMessageId, $status, $description): void {
            $outbox = NotificationOutbox::query()
                ->forOrganization($organizationId)
                ->where('channel', 'whatsapp')
                ->where('external_message_id', $externalMessageId)
                ->lockForUpdate()
                ->first();

            if ($outbox === null || $outbox->status !== OutboxStatus::Sent) {
                return;
            }

            $rank = ['accepted' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
            $current = (string) ($outbox->provider_status ?? '');
            if (isset($rank[$status]) && ($rank[$current] ?? -1) >= $rank[$status]) {
                return;
            }
            if (in_array($current, ['failed', 'noAccount', 'notInGroup', 'suspended', 'yellowCard'], true)
                && isset($rank[$status])) {
                return;
            }

            $outbox->forceFill([
                'provider_status' => $status,
                'failure_reason' => in_array($status, ['failed', 'noAccount', 'notInGroup', 'suspended', 'yellowCard'], true)
                    ? ($description !== '' ? $description : $status)
                    : null,
            ])->save();
        });
    }
}
