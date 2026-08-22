<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationQueued;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * قيد رسالة في صندوق الإرسال — نقطة الدخول الوحيدة لأي إشعار.
 *
 * الترتيب داخل execute إلزامي: حراس ← معاملة ← نشر الأحداث بعد النجاح.
 *
 * القواعد:
 *  - القناة يجب أن تكون مفعّلة في config('notifications.channels').
 *  - in_app والفئات الحرجة تتجاهل إيقاف المستلم؛ غير الحرجة تحترمه.
 *  - idempotency_key يضمن ألا تُقيَّد نفس الرسالة مرتين؛ التكرار يعيد القيدة الأصلية.
 */
final readonly class QueueNotificationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $body
     * @param array<string, mixed> $payload
     */
    public function execute(
        string $organizationId,
        string $userId,
        string $category,
        Channel $channel,
        string $eventName,
        string $eventId,
        array $subject,
        array $body,
        array $payload = [],
        ?CarbonImmutable $scheduledFor = null,
        ?string $locale = null,
        ?string $correlationId = null,
        ?string $actorId = null,
    ): ?NotificationOutbox {
        $enabledChannels = $this->enabledChannels();

        if (!in_array($channel->value, $enabledChannels, true)) {
            throw BusinessRuleViolation::make(
                'notifications.channel_disabled',
                'notifications::errors.channel_disabled',
                ['channel' => $channel->label()],
            );
        }

        $critical = (bool) config('notifications.categories.'.$category.'.critical', false);
        $optedOut = !$critical
            && $channel !== Channel::InApp
            && NotificationPreference::query()
                ->forUser($userId)
                ->forCategoryChannel($category, $channel->value)
                ->where('enabled', false)
                ->exists();

        if ($optedOut) {
            return null;
        }

        $scheduledFor ??= CarbonImmutable::now('UTC');
        $locale ??= (string) config('notifications.locale.fallback', config('app.fallback_locale'));
        $idempotencyKey = $this->buildIdempotencyKey($eventId, $userId, $category, $channel);

        /** @var NotificationOutbox $outbox */
        $outbox = $this->transaction->run(function () use (
            $organizationId,
            $userId,
            $category,
            $channel,
            $eventName,
            $eventId,
            $subject,
            $body,
            $payload,
            $scheduledFor,
            $locale,
            $idempotencyKey,
            $correlationId,
        ): NotificationOutbox {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$idempotencyKey]);

            $duplicate = NotificationOutbox::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('created_at', '>=', now()->subMinutes(
                    max(0, (int) config('notifications.delivery.idempotency_window_minutes')),
                ))
                ->exists();

            return NotificationOutbox::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'category' => $category,
                'channel' => $channel->value,
                'locale' => $locale,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'correlation_id' => $correlationId ?? (string) Str::ulid(),
                'subject' => $subject,
                'body' => $body,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'scheduled_for' => $scheduledFor,
                'status' => $duplicate ? OutboxStatus::Suppressed : OutboxStatus::Queued,
                'attempts' => 0,
            ]);
        });

        if ($outbox !== null && $outbox->status === OutboxStatus::Queued) {
            $this->events->dispatch(new NotificationQueued(
                outboxId: $outbox->id,
                organizationId: $outbox->organization_id,
                userId: $outbox->user_id,
                category: $outbox->category,
                channel: $outbox->channel,
                locale: $outbox->locale,
                eventName: $outbox->event_name,
                eventId: $outbox->event_id,
                idempotencyKey: $outbox->idempotency_key,
                scheduledFor: CarbonImmutable::instance($outbox->scheduled_for),
                actorId: $actorId,
                correlationId: $outbox->correlation_id,
            ));
        }

        return $outbox;
    }

    /**
     * مفتاح عدم التكرار الموحّد: الحدث + المستلم + القناة + الفئة.
     */
    private function buildIdempotencyKey(
        string $eventId,
        string $userId,
        string $category,
        Channel $channel,
    ): string {
        return hash('sha256', $eventId.$userId.$channel->value.$category);
    }

    /**
     * @return list<string>
     */
    private function enabledChannels(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = (array) config('notifications.channels', []);
        $explicit = $configured['enabled'] ?? null;

        if (is_array($explicit)) {
            return array_values(array_filter(
                $explicit,
                static fn (mixed $channel): bool => is_string($channel) && $channel !== '',
            ));
        }

        $enabled = [];

        foreach ($configured as $channel => $settings) {
            if (is_string($channel) && is_array($settings) && (bool) ($settings['enabled'] ?? false)) {
                $enabled[] = $channel;
            }
        }

        return $enabled;
    }
}
