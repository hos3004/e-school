<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعليم إشعار داخل التطبيق كمقروء مع التحقق داخل الإجراء نفسه من الملكية.
 *
 * فحص الـPolicy في طبقة HTTP لا يغني عن هذا القيد؛ فقد يُستدعى الإجراء
 * لاحقًا من واجهة أخرى، ولذلك تبقى حدود المستخدم والمؤسسة جزءًا من الكتابة.
 */
final readonly class MarkNotificationAsReadAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(
        NotificationOutbox $outbox,
        string $userId,
        string $organizationId,
    ): NotificationOutbox {
        return $this->transaction->run(function () use ($outbox, $userId, $organizationId): NotificationOutbox {
            /** @var NotificationOutbox $current */
            $current = NotificationOutbox::query()->lockForUpdate()->findOrFail($outbox->getKey());

            if (
                $current->user_id !== $userId
                || $current->organization_id !== $organizationId
                || $current->channel !== Channel::InApp->value
                || $current->status !== OutboxStatus::Sent
            ) {
                throw BusinessRuleViolation::make(
                    'notifications.not_readable',
                    'notifications::errors.not_readable',
                );
            }

            if ($current->read_at !== null) {
                return $current;
            }

            $readAt = CarbonImmutable::now('UTC');

            $current->forceFill([
                'read_at' => $readAt,
                'updated_at' => $readAt,
            ])->save();

            return $current;
        });
    }
}
