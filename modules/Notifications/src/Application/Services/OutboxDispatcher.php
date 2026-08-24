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
        private TemplateRenderer $templates,
        private NotificationCategorySettingsResolver $categorySettings,
    ) {}

    public function dispatch(
        string $category,
        array $recipientIds,
        array $payload,
        ?string $correlationId = null,
    ): int {
        // الفئة يجب أن تكون معرّفة في config؛ إعداد المؤسسة يعدّل توجيهها لا وجودها.
        if (config('notifications.categories.'.$category) === null) {
            throw BusinessRuleViolation::make(
                'notifications.category_unknown',
                'notifications::errors.category_unknown',
                ['category' => $category],
            );
        }

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

            // توجيه الفئة يُحسم بتوقيت المؤسسة صاحبة المستلم: override اللوحة إن
            // وُجد وإلا افتراضي config، فمؤسستان قد تختلفان في نفس الفئة.
            $organizationId = (string) $profile['organization_id'];
            $categoryChannels = $this->categorySettings->channels($organizationId, $category);
            $critical = $this->categorySettings->isCritical($organizationId, $category);
            $respectsQuietHours = $this->categorySettings->respectsQuietHours($organizationId, $category);

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

        $templatePayload = $this->localizedTemplatePayload(
            $payload,
            (string) ($profile['timezone'] ?? 'UTC'),
            (string) $profile['locale'],
        );
        $rendered = $this->templates->renderIfAvailable(
            eventKey: $eventName,
            channel: $channel->value,
            locale: (string) $profile['locale'],
            organizationId: $organizationId,
            payload: $templatePayload,
        );
        $rowLocale = $rendered['locale'] ?? (string) $profile['locale'];
        $rowPayload = $this->gatewayPayload($payload, $templatePayload, $profile, $channel, $rendered);
        $subject = $rendered === null
            ? ($payload['subject'] ?? null)
            : ($rendered['subject'] === null ? null : [$rowLocale => $rendered['subject']]);
        $body = $rendered === null
            ? ($payload['body'] ?? [])
            : [$rowLocale => $rendered['body']];

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
            $rowPayload,
            $rowLocale,
            $subject,
            $body,
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
                'locale' => $rowLocale,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'subject' => $subject,
                'body' => $body,
                'payload' => $rowPayload,
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
     * @return array<string, array{
     *     organization_id: string,
     *     locale: string,
     *     timezone: string,
     *     email: string|null,
     *     phone: string|null,
     *     phone_country: string|null
     * }>
     */
    private function recipientProfiles(array $recipientIds): array
    {
        $fallbackLocale = (string) config(
            'notifications.localization.fallback_locale',
            config('app.fallback_locale'),
        );

        return DB::table('users')
            ->whereIn('id', array_values(array_unique($recipientIds)))
            ->get(['id', 'organization_id', 'locale', 'timezone', 'email', 'phone', 'phone_country'])
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
                    'email' => is_string($row->email ?? null) ? $row->email : null,
                    'phone' => is_string($row->phone ?? null) ? $row->phone : null,
                    'phone_country' => is_string($row->phone_country ?? null) ? $row->phone_country : null,
                ]];
            })
            ->all();
    }

    /**
     * يمرر للبوابة عنوان القناة فقط، مع بيانات قالب Meta اللازمة، من دون
     * تحميل البوابة بأي معرفة عن نماذج Identity أو Outbox.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $templatePayload
     * @param array<string, mixed> $profile
     * @param array{
     *     subject: string|null,
     *     body: string,
     *     locale: string,
     *     provider_template_name: string|null,
     *     template_parameters: list<string>
     * }|null $rendered
     * @return array<string, mixed>
     */
    private function gatewayPayload(
        array $payload,
        array $templatePayload,
        array $profile,
        Channel $channel,
        ?array $rendered,
    ): array {
        $payload['template_data'] = $templatePayload;

        if ($channel === Channel::Email) {
            $payload['email'] = $profile['email'] ?? null;
        }

        if ($channel === Channel::Whatsapp) {
            $payload['phone'] = $profile['phone'] ?? null;
            $payload['phone_country'] = $profile['phone_country'] ?? null;
        }

        if ($rendered !== null) {
            $payload['template_locale'] = $rendered['locale'];
            $payload['provider_template_name'] = $rendered['provider_template_name'];
            $payload['template_parameters'] = $rendered['template_parameters'];
        }

        return $payload;
    }

    /**
     * ينسّق حقول الوقت المعلنة في config بتوقيت المستلم قبل تركيب القالب،
     * بينما تبقى القيم الأصلية في الحدث نفسه مخزنة UTC.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function localizedTemplatePayload(array $payload, string $timezone, string $locale): array
    {
        $format = (string) config('notifications.localization.datetime_format', 'Y-m-d H:i T');

        foreach ((array) config('notifications.localization.datetime_parameters', []) as $parameter) {
            if (!is_string($parameter)) {
                continue;
            }

            $value = data_get($payload, $parameter);

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                data_set(
                    $payload,
                    $parameter,
                    CarbonImmutable::parse($value, 'UTC')
                        ->setTimezone(new CarbonTimeZone($timezone))
                        ->locale($locale)
                        ->translatedFormat($format),
                );
            } catch (Throwable) {
                // قيمة غير زمنية تبقى كما نشرها الحدث بدل إسقاط الإشعار كله.
            }
        }

        $payload['recipient_timezone'] = $timezone;

        return $payload;
    }
}
