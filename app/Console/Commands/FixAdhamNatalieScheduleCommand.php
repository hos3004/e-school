<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\DeactivateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;

/**
 * تصحيح لمرة واحدة: نقل جدول اتحط غلط على مجموعة عائشة إبراهيم بسبب خلل
 * كان في نموذج جدولة المجموعات، وكان المفروض يتحط على مجموعة أدهم ونتالي
 * أيمن. صفر حصص من الجدول القديم اتنفذت أو دخلت قيود مالية، فالإلغاء
 * والإنشاء الجديد آمنين. يُحذف هذا الأمر بعد التنفيذ.
 */
final class FixAdhamNatalieScheduleCommand extends Command
{
    protected $signature = "eschool:fix-adham-natalie-schedule-20260922";

    protected $description = "ينقل جدول المجموعة المحطوط غلط على مجموعة عائشة إبراهيم إلى مجموعة أدهم ونتالي أيمن";

    private const WRONG_SCHEDULE_ID = "01m32xxwkhj703736amk7c3tca";

    private const SOURCE_GROUP_ID = "01m2tqbv9bnvb8kb9crfx74hy5";

    private const TARGET_GROUP_ID = "01m32xg31srwzrftm1h32m7fyf";

    private const ACTOR_ID = "01m1jctyse9ymhys2hzgjy7z89";

    public function handle(CreateScheduleAction $create, DeactivateScheduleAction $deactivate): int
    {
        $wrong = Schedule::query()->findOrFail(self::WRONG_SCHEDULE_ID);

        if ($wrong->group_id === self::TARGET_GROUP_ID) {
            $this->info("already correct, nothing to do");

            return self::SUCCESS;
        }

        if ((string) $wrong->group_id !== self::SOURCE_GROUP_ID) {
            $this->error("wrong schedule is not where expected, aborting: group_id=".$wrong->group_id);

            return self::FAILURE;
        }

        $organizationId = (string) $wrong->organization_id;
        $rule = WeeklyRecurrence::fromRRule($wrong->rrule);

        $payload = [
            "target_type" => "group",
            "group_id" => self::TARGET_GROUP_ID,
            "student_profile_id" => null,
            "course_id" => (string) $wrong->course_id,
            "staff_profile_id" => (string) $wrong->staff_profile_id,
            "weekdays" => $rule->weekdays,
            "weekly_slots" => [],
            "start_time" => substr((string) $wrong->start_time, 0, 5),
            "duration_minutes" => $wrong->duration_minutes,
            "interval_weeks" => $rule->intervalWeeks,
            "timezone" => $wrong->timezone,
            "starts_on" => $wrong->starts_on->toDateString(),
            "ends_on" => $wrong->ends_on?->toDateString(),
        ];

        DB::transaction(function () use ($create, $deactivate, $wrong, $payload, $organizationId): void {
            $deactivate->execute(
                $wrong,
                self::ACTOR_ID,
                "تصحيح: كان مقصود لمجموعة أدهم ونتالي أيمن، اتحط غلط هنا بسبب خلل تقني في نموذج جدولة المجموعات (أُصلح بعد ذلك)",
            );

            $created = $create->execute(
                $organizationId,
                $payload,
                self::ACTOR_ID,
                "تصحيح: نقل من جدول أُنشئ غلط على مجموعة عائشة إبراهيم إلى المجموعة الصحيحة أدهم ونتالي أيمن",
            );

            $this->info("created corrected schedule: ".$created->id);
        });

        return self::SUCCESS;
    }
}
