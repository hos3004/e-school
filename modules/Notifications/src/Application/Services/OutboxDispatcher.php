<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationQueued;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Throwable;

/**
 * محرّك قيد الإشعارات في صندوق الصادر.
 *
 * يقرر لكل مستلم: أي القنوات، بأي لغة، ومتى — ثم يكتب السطور فقط.
 * لا يوجد أي استدعاء لمزوّد إرسال هنا إطلاقًا؛ الإرسال مهمة الخلفية
 * SendQueuedNotification التي تلتقط السطور المستحقة.
 *
 * كل الأرقام والسياسات من config('notifications...') — لا رقم واحد في الكود.
 */
final readonly class OutboxDispatcher implements NotificationDispatcher
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function dispatch(
        string $category,
        array $recipientIds,
        array $payload,
        ?string $correlationId = null,
    ): int {
        /** @var array{channels: list<string>, critical?: bool}|null $settings */
        $settings = config('notifications.categories.'.$category);

        if ($settings === null) {
            throw BusinessRuleViolation::make(
                'notifications.category_unknown',
                'notifications::errors.category_unknown',
                ['category' => $category],
            );
        }

        $categoryChannels = array_values((array) ($settings['channels'] ?? []));
        $critical = (bool) ($settings['critical'] ?? false);
        $respectsQuietHours = (bool) ($settings['respects_quiet_hours'] ?? true);

        if ($recipientIds === []) {
            return 0;
        }

        $eventName = (string) ($payload['event_name'] ?? $category);
        $eventId = $payload['event_id'] ?? null;

        if (!is_string($eventId) || trim($eventId) === '') {
            throw BusinessRuleViolation::make(
                'notifications.event_id_required',
                'notifications::errors.event_id_required',
            );
        }

        $profiles = $this->recipientProfiles($recipientIds);
        $written = 0;

        foreach (array_values(array_unique($recipientIds)) as $recipientId) {
            $recipientId = (string) $recipientId;
            $profile = $profiles[$recipientId] ?? null;

            // معرّف غير موجود لا يمكن أن ينجح معه المفتاح الأجنبي. تجاهله بدل
            // إسقاط إشعارات بقية المستلمين في الدفعة نفسها.
            if ($profile === null) {
                continue;
            }

            foreach ($this->channelsFor($category, $categoryChannels, $critical, $recipientId) as $channelValue) {
                $channel = Channel::from($channelValue);

                if ($this->writeRow(
                    category: $category,
                    channel: $channel,
                    critical: $critical,
                    respectsQuietHours: $respectsQuietHours,
                    recipientId: $recipientId,
                    organizationId: $profile['organization_id'],
                    profile: $profile,
                    payload: $payload,
                    eventName: $eventName,
                    eventId: $eventId,
                    correlationId: $correlationId,
                )) {
                    $written++;
                }
            }
        }

        return $written;
    }

    /**
     * كتابة سطر واحد في الصندوق الصادر.
     *
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $payload
     */
    private function writeRow(
        string $category,
        Channel $channel,
        bool $critical,
        bool $respectsQuietHours,
        string $recipientId,
        string $organizationId,
        array $profile,
        array $payload,
        string $eventName,
        string $eventId,
        ?string $correlationId,
    ): bool {
        $idempotencyKey = hash('sha256', $eventId.$recipientId.$channel->value.$category);

        $scheduledFor = $this->scheduledFor(
            $critical,
            $respectsQuietHours,
            (string) ($profile['timezone'] ?? 'UTC'),
        );

        $create = function (OutboxStatus $rowStatus) use (
            $category,
            $channel,
            $recipientId,
            $organizationId,
            $profile,
            $payload,
            $eventName,
            $eventId,
            $correlationId,
            $idempotencyKey,
            $scheduledFor,
        ): NotificationOutbox {
            return NotificationOutbox::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $recipientId,
                'category' => $category,
                'channel' => $channel->value,
                'locale' => (string) $profile['locale'],
                'event_name' => $eventName,
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'subject' => $payload['subject'] ?? null,
                'body' => $payload['body'] ?? [],
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'scheduled_for' => $scheduledFor,
                'status' => $rowStatus,
                'attempts' => 0,
            ]);
        };

        [$outbox, $status] = $this->transaction->run(function () use ($idempotencyKey, $create): array {
            // قفل معاملاتي على المفتاح يمنع عاملين من تجاوز فحص النافذة معًا،
            // من دون UNIQUE دائم يمنع إعادة الحدث بعد انتهاء النافذة الزمنية.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$idempotencyKey]);

            $duplicate = NotificationOutbox::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('created_at', '>=', now()->subMinutes(
                    max(0, (int) config('notifications.delivery.idempotency_window_minutes')),
                ))
                ->exists();
            $status = $duplicate ? OutboxStatus::Suppressed : OutboxStatus::Queued;

            return [$create($status), $status];
        });

        if ($status === OutboxStatus::Queued) {
            $this->events->dispatch(new NotificationQueued(
                outboxId: $outbox->id,
                organizationId: $outbox->organization_id,
                userId: $outbox->user_id,
                category: $outbox->category,
                channel: $channel->value,
                locale: $outbox->locale,
                eventName: $outbox->event_name,
                eventId: $outbox->event_id,
                idempotencyKey: $outbox->idempotency_key,
                scheduledFor: CarbonImmutable::instance($outbox->scheduled_for),
                actorId: null,
                correlationId: $outbox->correlation_id,
            ));
        }

        return true;
    }

    /**
     * قنوات المستلم: تقاطع قنوات الفئة مع المفعّلة عالميًا ومع تفضيلاته،
     * وin_app دائمًا إن كانت مفعّلة عالميًا، والحرجة تتجاهل التفضيلات.
     *
     * @param list<string> $categoryChannels
     * @return list<string>
     */
    private function channelsFor(
        string $category,
        array $categoryChannels,
        bool $critical,
        string $recipientId,
    ): array {
        /** @var array<string, mixed> $channelConfig */
        $channelConfig = (array) config('notifications.channels', []);
        $explicitEnabled = $channelConfig['enabled'] ?? null;

        if (is_array($explicitEnabled)) {
            $enabledNames = array_values(array_filter(
                $explicitEnabled,
                static fn (mixed $name): bool => is_string($name) && $name !== '',
            ));
        } else {
            $enabledNames = [];

            foreach ($channelConfig as $name => $settings) {
                if (is_string($name) && is_array($settings) && (bool) ($settings['enabled'] ?? false)) {
                    $enabledNames[] = $name;
                }
            }
        }

        $resolved = array_values(array_intersect($categoryChannels, $enabledNames));

        if (!$critical && $resolved !== []) {
            $disabled = NotificationPreference::query()
                ->forUser($recipientId)
                ->where('category', $category)
                ->whereIn('channel', $resolved)
                ->where('enabled', false)
                ->pluck('channel')
                ->map(static fn (mixed $value): string => (string) $value)
                ->all();

            $resolved = array_values(array_diff($resolved, $disabled));
        }

        // in_app لا تخضع لتفضيل المستخدم، وتُضاف حتى لو لم تذكرها الفئة.
        if (in_array(Channel::InApp->value, $enabledNames, true)) {
            $resolved[] = Channel::InApp->value;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * وقت الإرسال: الآن للحرجة؛ غير الحرجة داخل ساعات الهدوء بتوقيت المستلم
     * تؤجل إلى أول نهاية قادمة لساعات الهدوء.
     */
    private function scheduledFor(
        bool $critical,
        bool $respectsQuietHours,
        string $timezone,
    ): CarbonImmutable {
        $nowUtc = CarbonImmutable::now('UTC');

        if ($critical || !$respectsQuietHours || !(bool) config('notifications.quiet_hours.enabled', false)) {
            return $nowUtc;
        }

        $startValue = config('notifications.quiet_hours.start');
        $endValue = config('notifications.quiet_hours.end');

        if (!is_string($startValue) || !is_string($endValue)) {
            return $nowUtc;
        }

        try {
            $tz = new CarbonTimeZone($timezone);
            $localNow = $nowUtc->setTimezone($tz);
            $start = CarbonImmutable::parse($startValue, $tz)->setDateFrom($localNow);
            $end = CarbonImmutable::parse($endValue, $tz)->setDateFrom($localNow);
        } catch (Throwable) {
            return $nowUtc;
        }

        if ($start->lessThanOrEqualTo($end)) {
            if (!$localNow->betweenIncluded($start, $end)) {
                return $nowUtc;
            }

            return $end->setTimezone('UTC');
        }

        if ($localNow->greaterThanOrEqualTo($start)) {
            return $end->addDay()->setTimezone('UTC');
        }

        if ($localNow->lessThan($end)) {
            return $end->setTimezone('UTC');
        }

        return $nowUtc;
    }

    /**
     * اللغة والمنطقة والمؤسسة تُقرأ دفعة واحدة من users دون استيراد نموذج Identity.
     *
     * @param list<string> $recipientIds
     * @return array<string, array{organization_id: string, locale: string, timezone: string}>
     */
    private function recipientProfiles(array $recipientIds): array
    {
        $fallbackLocale = (string) config(
            'notifications.localization.fallback_locale',
            config('app.fallback_locale'),
        );

        return DB::table('users')
            ->whereIn('id', array_values(array_unique($recipientIds)))
            ->get(['id', 'organization_id', 'locale', 'timezone'])
            ->mapWithKeys(static function (object $row) use ($fallbackLocale): array {
                $locale = is_string($row->locale ?? null) && $row->locale !== ''
                    ? $row->locale
                    : $fallbackLocale;
                $timezone = is_string($row->timezone ?? null) && $row->timezone !== ''
                    ? $row->timezone
                    : 'UTC';

                return [(string) $row->id => [
                    'organization_id' => (string) $row->organization_id,
                    'locale' => $locale,
                    'timezone' => $timezone,
                ]];
            })
            ->all();
    }
}
