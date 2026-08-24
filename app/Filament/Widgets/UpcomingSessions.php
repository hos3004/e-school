<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToOrganization;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * أقرب عشر حصص مجدولة لم تبدأ بعد.
 *
 * تُقرأ البيانات مباشرة من الجداول (نفس مبدأ PlatformOverview): هذه الويدجت
 * تعيش خارج الموديولات ولا يجوز لها استيراد نماذجها.
 *
 * الوقت يُخزَّن UTC ويُعرض بتوقيت مجموعة الحصة، مع سقوطٍ إلى توقيت التطبيق.
 */
final class UpcomingSessions extends Widget
{
    use ScopesToOrganization;

    protected string $view = 'filament.widgets.upcoming-sessions';

    protected int|string|array $columnSpan = 'full';

    private const LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $locale = (string) app()->getLocale();
        $fallbackLocale = $locale === 'ar' ? 'en' : 'ar';
        $appTimezone = (string) config('app.timezone', 'UTC');

        $rows = $this->scoped('sessions')
            ->leftJoin('groups', 'groups.id', '=', 'sessions.group_id')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'sessions.staff_profile_id')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->whereNull('sessions.deleted_at')
            ->whereIn('sessions.status', ['scheduled', 'confirmed'])
            ->where('sessions.scheduled_start', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('sessions.scheduled_start')
            ->limit(self::LIMIT)
            ->selectRaw(implode(', ', [
                'sessions.id',
                'sessions.scheduled_start',
                'groups.timezone as group_timezone',
                'coalesce(groups.name->>?, groups.name->>?) as group_name',
                'groups.code as group_code',
                'users.name as teacher_name',
            ]), [$locale, $fallbackLocale])
            ->get();

        return [
            'title' => __('dashboard.upcoming_sessions.title'),
            'subtitle' => __('dashboard.upcoming_sessions.subtitle'),
            'empty' => __('dashboard.upcoming_sessions.empty'),
            'columns' => [
                'start_at' => __('dashboard.upcoming_sessions.columns.start_at'),
                'group' => __('dashboard.upcoming_sessions.columns.group'),
                'teacher' => __('dashboard.upcoming_sessions.columns.teacher'),
                'actions' => __('dashboard.upcoming_sessions.columns.actions'),
            ],
            'rows' => $rows
                ->map(fn (object $row): array => $this->mapRow($row, $appTimezone))
                ->all(),
        ];
    }

    /**
     * @param object{id: string, scheduled_start: string, group_timezone: string|null, group_name: string|null, group_code: string|null, teacher_name: string|null} $row
     * @return array{start_at: string, group: string, teacher: string, href: string, view_label: string}
     */
    private function mapRow(object $row, string $appTimezone): array
    {
        $timezone = filled($row->group_timezone) ? (string) $row->group_timezone : $appTimezone;
        $startsAt = CarbonImmutable::parse($row->scheduled_start)
            ->setTimezone($timezone)
            ->locale((string) app()->getLocale());

        return [
            'start_at' => sprintf(
                '%s %s',
                $startsAt->translatedFormat('d M Y'),
                $startsAt->translatedFormat('H:i'),
            ),
            'group' => collect([(string) ($row->group_name ?? ''), (string) ($row->group_code ?? '')])
                ->filter()
                ->unique()
                ->implode(' · ') ?: __('dashboard.common.dash'),
            'teacher' => filled($row->teacher_name) ? (string) $row->teacher_name : __('dashboard.common.dash'),
            'href' => '/admin/sessions/'.$row->id,
            'view_label' => __('dashboard.upcoming_sessions.view_session'),
        ];
    }
}
