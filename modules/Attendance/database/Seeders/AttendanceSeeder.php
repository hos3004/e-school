<?php

declare(strict_types=1);

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Shared\Support\BusinessRuleViolation;

/**
 * بيانات تجريبية لقيود الحضور.
 *
 * الحضور يُرصد على مشاركات حصص يملكها موديول Sessions — هذا البذر
 * لا ينشئ بيانات موديول آخر؛ يعمل على المشاركات الموجودة فقط، وإن لم
 * توجد يكتفي بالتحذير. لكل مشاركة نرصد حضورًا واحدًا (قيد فريد).
 */
final class AttendanceSeeder extends Seeder
{
    /** أقصى عدد قيود تجريبية — ليس رقم سياسة عمل، مجرد سقف عرض للبيانات التجريبية. */
    private const DEMO_LIMIT = 8;

    public function run(): void
    {
        $participantIds = DB::table('session_participants')
            ->orderBy('created_at')
            ->limit(self::DEMO_LIMIT)
            ->pluck('id');

        if ($participantIds->isEmpty()) {
            $this->command?->warn(__('attendance::messages.seeder_no_participants'));

            return;
        }

        $action = app(RecordAttendanceAction::class);

        foreach ($participantIds as $index => $participantId) {
            try {
                // مدد تجريبية متنوعة: حاضر، متأخر، جزئي، غائب — بالتناوب.
                $action->execute(
                    sessionParticipantId: (string) $participantId,
                    attendedMinutes: [50, 40, 20, 0][$index % 4],
                    sessionMinutes: 60,
                    joinedAfterMinutes: [0, 12, 2, 0][$index % 4],
                    leftBeforeMinutes: [0, 0, 30, 0][$index % 4],
                );
            } catch (BusinessRuleViolation) {
                // المشاركة لها قيد حضور سابق — نتخطاها حفاظًا على الفرادة.
                continue;
            }
        }
    }
}
