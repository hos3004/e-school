<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\DeactivateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;

/**
 * تصحيح لمرة واحدة: فصل موعد المعلمة الكرداوي. أدهم ونتالي يتحول من
 * سبت+أحد 9:00 إلى سبت 11:00 فقط، وعائشة إبراهيم تاخد سبت 9:00 + أحد
 * 13:00 (وقت مختلف لكل يوم). بناءً على تأكيد صاحب المدرسة. يُحذف هذا
 * الأمر بعد التنفيذ.
 */
final class SplitKirdawyScheduleCommand extends Command
{
    protected $signature = "eschool:split-kirdawy-schedule-20260922";

    protected $description = "يفصل مواعيد المعلمة الكرداوي بين مجموعة أدهم ونتالي ومجموعة عائشة إبراهيم";

    private const ADHAM_NATALIE_SCHEDULE_ID = "01m330hf81yy15dc05w726mt6k";

    private const ADHAM_NATALIE_GROUP_ID = "01m32xg31srwzrftm1h32m7fyf";

    private const AISHA_GROUP_ID = "01m2tqbv9bnvb8kb9crfx74hy5";

    private const ACTOR_ID = "01m1jctyse9ymhys2hzgjy7z89";

    public function handle(CreateScheduleAction $create, DeactivateScheduleAction $deactivate): int
    {
        $current = Schedule::query()->findOrFail(self::ADHAM_NATALIE_SCHEDULE_ID);

        if (!$current->is_active) {
            $this->error("schedule already inactive, aborting to avoid double-processing");

            return self::FAILURE;
        }

        $organizationId = (string) $current->organization_id;
        $courseId = (string) $current->course_id;
        $staffProfileId = (string) $current->staff_profile_id;
        $durationMinutes = $current->duration_minutes;
        $timezone = $current->timezone;
        $startsOn = $current->starts_on->toDateString();

        DB::transaction(function () use (
            $create, $deactivate, $current, $organizationId, $courseId, $staffProfileId, $durationMinutes, $timezone, $startsOn
        ): void {
            $deactivate->execute(
                $current,
                self::ACTOR_ID,
                "تصحيح: فصل موعد المعلمة بين المجموعتين، بناءً على تأكيد صاحب المدرسة أن أدهم ونتالي سبت 11 فقط وعائشة إبراهيم سبت 9 وأحد 1 ظهرا",
            );

            $adhamNatalie = $create->execute(
                $organizationId,
                [
                    "target_type" => "group",
                    "group_id" => self::ADHAM_NATALIE_GROUP_ID,
                    "student_profile_id" => null,
                    "course_id" => $courseId,
                    "staff_profile_id" => $staffProfileId,
                    "weekdays" => [6],
                    "weekly_slots" => [],
                    "start_time" => "11:00",
                    "duration_minutes" => $durationMinutes,
                    "interval_weeks" => 1,
                    "timezone" => $timezone,
                    "starts_on" => $startsOn,
                    "ends_on" => null,
                ],
                self::ACTOR_ID,
                "تصحيح: مجموعة أدهم ونتالي أيمن سبت 11 صباحًا فقط، بدل سبت وأحد 9",
            );
            $this->info("adham/natalie schedule: ".$adhamNatalie->id);

            $aisha = $create->execute(
                $organizationId,
                [
                    "target_type" => "group",
                    "group_id" => self::AISHA_GROUP_ID,
                    "student_profile_id" => null,
                    "course_id" => $courseId,
                    "staff_profile_id" => $staffProfileId,
                    "weekdays" => [],
                    "weekly_slots" => [
                        ["weekday" => 6, "start_time" => "09:00"],
                        ["weekday" => 0, "start_time" => "13:00"],
                    ],
                    "start_time" => "09:00",
                    "duration_minutes" => $durationMinutes,
                    "interval_weeks" => 1,
                    "timezone" => $timezone,
                    "starts_on" => $startsOn,
                    "ends_on" => null,
                ],
                self::ACTOR_ID,
                "تصحيح: مجموعة عائشة إبراهيم سبت 9 صباحًا وأحد 1 ظهرًا",
            );
            $this->info("aisha schedule: ".$aisha->id);
        });

        return self::SUCCESS;
    }
}
