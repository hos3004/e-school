<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Notifications\Application\Services\NotificationCategorySettingsResolver;
use Modules\Notifications\Domain\Enums\Channel;
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
            'notificationChannels' => $canNotifications ? array_map(static fn (Channel $channel): array => [
                'value' => $channel->value, 'label' => $channel->label(),
                'enabled' => (bool) config('notifications.channels.'.$channel->value.'.enabled', false),
            ], Channel::cases()) : [],
            'settingsPermissions' => [
                'accounts' => $canAccounts, 'notifications' => $canNotifications,
                'calendars' => $canCalendar, 'holidays' => $canHoliday,
                'create_calendar' => $canCalendar && $actor->can('create', AcademicCalendar::class),
                'create_holiday' => $canHoliday && $actor->can('create', Holiday::class),
            ],
        ];
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
