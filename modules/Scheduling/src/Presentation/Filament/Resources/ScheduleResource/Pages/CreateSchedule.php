<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    /**
     * تعبئة المجموعة مسبقًا حين نأتي من صفحتها.
     *
     * كان المنسّق يترك المجموعة، ويفتح الجداول، ويعيد اختيار المجموعة نفسها من
     * قائمة طويلة — انقطاعٌ في التسلسل بلا سبب. المعرّف يصل في `?group=` من زر
     * «جدولة حصص» في صفحة المجموعة، ويبقى قابلًا للتغيير هنا.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $group = request()->query('group');

        if (is_string($group) && $group !== '') {
            $data['target_type'] = 'group';
            $data['group_id'] = $group;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return app(CreateScheduleAction::class)->execute(
            $organizationId,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        $schedule = $this->getRecord();
        if (!$schedule instanceof Schedule) {
            return parent::getCreatedNotification();
        }

        $organizationId = (string) $schedule->organization_id;
        $timezone = (string) $schedule->timezone;
        $queries = app(SchedulingAdministrationQueryService::class);
        $sessions = $queries->scheduleHub($organizationId, $schedule)['sessions'];
        $target = (string) $schedule->session_type === 'individual'
            ? $queries->studentLabel($organizationId, $schedule->student_profile_id)
            : $queries->groupLabel($organizationId, $schedule->group_id);
        $rule = WeeklyRecurrence::fromRRule((string) $schedule->rrule);
        $overview = app(TeacherAvailabilityPlanner::class)->overview(
            organizationId: $organizationId,
            staffProfileId: (string) $schedule->staff_profile_id,
            weekdays: $rule->weekdays,
            intervalWeeks: $rule->intervalWeeks,
            durationMinutes: (int) $schedule->duration_minutes,
            timezone: $timezone,
            startsOn: $schedule->starts_on->toDateString(),
            endsOn: $schedule->ends_on?->toDateString(),
            selectedStartTime: (string) $schedule->start_time,
            requireDeclaredAvailability: false,
        );

        return Notification::make()
            ->success()
            ->title(__('scheduling::filament.schedule.created.title', ['count' => count($sessions)]))
            ->body(__('scheduling::filament.schedule.created.body', [
                'target' => $target,
                'course' => $queries->courseLabel($organizationId, (string) $schedule->course_id),
                'teacher' => $queries->teacherLabel($organizationId, (string) $schedule->staff_profile_id),
                'duration' => (int) $schedule->duration_minutes,
                'sessions' => $this->sessionList($sessions, $timezone),
                'available' => $this->availableList($overview['available_start_times']),
            ]))
            ->persistent();
    }

    /** @param list<array<string, mixed>> $sessions */
    private function sessionList(array $sessions, string $timezone): string
    {
        $limit = (int) config('scheduling.booking_slots.preview_limit');
        $items = array_map(
            static fn (array $session): string => CarbonImmutable::parse((string) $session['scheduled_start'])
                ->setTimezone($timezone)
                ->translatedFormat('D d M · H:i'),
            array_slice($sessions, 0, $limit),
        );

        return $items === [] ? __('scheduling::filament.common.not_available') : implode('، ', $items);
    }

    /** @param list<string> $times */
    private function availableList(array $times): string
    {
        $items = array_slice($times, 0, (int) config('scheduling.booking_slots.preview_limit'));

        return $items === [] ? __('scheduling::filament.common.not_available') : implode('، ', $items);
    }
}
