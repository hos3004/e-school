<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Lang;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Notifications\Application\Services\NotificationCategorySettingsResolver;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;

final class ConsoleSettingsData
{
    /** @return array<string, mixed> */
    public function forOrganization(string $organizationId, Authenticatable $actor): array
    {
        $canAccounts = $actor->can('organizations.manage_settings');
        $canNotifications = $actor->can('settings.manage');
        $canGreenApi = $canNotifications && $actor->can('integrations.connection.update');
        $canCalendar = $actor->can('academic_calendars.view_any');
        $canHoliday = $actor->can('holidays.view_any');
        $activeCalendarIds = $canCalendar ? AcademicCalendar::query()->forOrganization($organizationId)->active()->orderBy('id')->pluck('id')->all() : [];

        return [
            'sessionPay' => $canAccounts ? SessionPayController::data($organizationId) : null,
            'accounts' => $canAccounts ? $this->accounts($organizationId) : null,
            'calendars' => $canCalendar ? AcademicCalendar::query()->forOrganization($organizationId)
                ->orderByDesc('starts_on')->get()->map(fn (AcademicCalendar $calendar): array => [
                    'id' => $calendar->id, 'name' => $calendar->name['ar'] ?? '',
                    'starts_on' => $calendar->starts_on->toDateString(), 'ends_on' => $calendar->ends_on->toDateString(),
                    'is_active' => $calendar->is_active, 'version' => self::calendarFingerprint($calendar, $activeCalendarIds),
                    'can_activate' => $actor->can('activate', $calendar), 'can_close' => $actor->can('close', $calendar),
                ])->all() : [],
            'holidays' => $canHoliday ? Holiday::query()->forOrganization($organizationId)
                ->orderByDesc('starts_on')->get()->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id, 'name' => $holiday->name['ar'] ?? '',
                    'starts_on' => $holiday->starts_on->toDateString(), 'ends_on' => $holiday->ends_on->toDateString(),
                    'academic_calendar_id' => $holiday->academic_calendar_id, 'blocks_scheduling' => $holiday->blocks_scheduling,
                    'version' => self::fingerprint($holiday->getAttributes()), 'can_remove' => $actor->can('delete', $holiday),
                ])->all() : [],
            'notificationCategories' => $canNotifications ? $this->notificationCategories($organizationId) : [],
            'whatsappTemplates' => $canNotifications ? $this->whatsappTemplates($organizationId) : [],
            'greenApi' => $canGreenApi ? app(GreenApiConnections::class)->view($organizationId) : null,
            'notificationChannels' => $canNotifications ? array_map(static fn (Channel $channel): array => [
                'value' => $channel->value, 'label' => $channel->label(),
                'enabled' => $channel === Channel::Whatsapp
                    ? app(GreenApiConnections::class)->isChannelEnabled($organizationId)
                    : (bool) config('notifications.channels.'.$channel->value.'.enabled', false),
            ], Channel::cases()) : [],
            'settingsPermissions' => [
                'accounts' => $canAccounts, 'notifications' => $canNotifications,
                'green_api' => $canGreenApi,
                'calendars' => $canCalendar, 'holidays' => $canHoliday,
                'create_calendar' => $canCalendar && $actor->can('create', AcademicCalendar::class),
                'create_holiday' => $canHoliday && $actor->can('create', Holiday::class),
            ],
        ];
    }

    /**
     * قوالب واتساب القابلة للتحرير بلغة المدرسة: الأصل العام ونسخة المؤسسة إن وُجدت.
     *
     * المتغيرات المعروضة هي متغيرات القالب الأصلي، لأن الحدث لا يوفّر غيرها.
     *
     * @return list<array<string, mixed>>
     */
    public function whatsappTemplates(string $organizationId): array
    {
        $locale = (string) config('notifications.localization.fallback_locale', 'ar');
        $scope = static fn () => NotificationTemplate::query()
            ->where('channel', Channel::Whatsapp->value)
            ->where('locale', $locale);
        $custom = $scope()->forOrganization($organizationId)->get()->keyBy('event_key');

        return $scope()->whereNull('organization_id')->active()->orderBy('event_key')->get()
            ->map(static function (NotificationTemplate $template) use ($custom): array {
                $labelKey = 'notifications::events.'.$template->event_key;
                /** @var NotificationTemplate|null $own */
                $own = $custom->get($template->event_key);

                return [
                    'event_key' => $template->event_key,
                    'locale' => $template->locale,
                    'label' => Lang::has($labelKey) ? (string) __($labelKey) : (string) $template->subject,
                    'parameters' => array_values($template->parameters ?? []),
                    'original' => ['subject' => $template->subject, 'body' => $template->body],
                    'custom' => $own === null ? null : [
                        'id' => (string) $own->id, 'subject' => $own->subject, 'body' => $own->body,
                    ],
                ];
            })->values()->all();
    }

    /** @return array<string, mixed> */
    public function accounts(string $organizationId): array
    {
        $stored = app(OrganizationSettingQueries::class)->value($organizationId, (string) config('admission.username.organization_setting_key'));
        $prefix = is_string($stored) ? $stored : '';

        return [
            'username_prefix' => $prefix,
            'effective_prefix' => $prefix !== '' ? $prefix : (string) config('admission.username.fallback_prefix'),
            'version' => self::fingerprint(['username_prefix' => $stored]),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function notificationCategories(string $organizationId): array
    {
        $resolver = app(NotificationCategorySettingsResolver::class);
        $rows = [];
        foreach (array_keys((array) config('notifications.categories')) as $category) {
            $data = [
                'category' => (string) $category,
                'channels' => $resolver->channels($organizationId, (string) $category),
                'is_critical' => $resolver->isCritical($organizationId, (string) $category),
                'respects_quiet_hours' => $resolver->respectsQuietHours($organizationId, (string) $category),
            ];
            $rows[] = [...$data, 'label' => __('notifications::categories.'.$category), 'version' => self::fingerprint($data)];
        }

        return $rows;
    }

    /** @param list<string>|null $activeCalendarIds */
    public static function calendarFingerprint(AcademicCalendar $calendar, ?array $activeCalendarIds = null): string
    {
        $activeCalendarIds ??= AcademicCalendar::query()->forOrganization($calendar->organization_id)->active()->orderBy('id')->pluck('id')->all();

        return self::fingerprint(['calendar' => $calendar->getAttributes(), 'active_calendar_ids' => $activeCalendarIds]);
    }

    /** @param array<string, mixed> $data */
    public static function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
