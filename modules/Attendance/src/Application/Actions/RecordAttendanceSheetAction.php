<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رصد كشف حضور الحصة كاملًا في عملية واحدة، كما يفعله المعلم في البوابة.
 *
 * الأفعال المفردة القائمة تخدم حالة واحدة لكل نداء: `RecordAttendanceAction`
 * تشتق الحالة من الدقائق، و`OverrideAttendanceAction` تبدّل حالة مرصودة بسبب،
 * و`ConfirmAttendanceAction` تعتمدها. لكن المعلم لا يرصد طالبًا واحدًا: يفتح
 * الكشف، يختار حالة كل طالب، ويحفظ مرة واحدة. هذا الفعل يجمع الثلاثة على تلك
 * الوحدة الطبيعية.
 *
 * القواعد كما تفرضها الأفعال المفردة، لا مخفَّفة:
 *   · تغيير حالة مرصودة يوجب سببًا مكتوبًا (`OverrideAttendanceAction`).
 *   · إبقاء الحالة كما هي = اعتماد لا تجاوز، فلا يُطلب سبب.
 *   · الطالب بلا رصد سابق يُرصد بدقائق تطابق الحالة المختارة، فإن اختلف ما
 *     اشتُقّ عمّا اختاره المعلم صُحّح بتجاوز مسبَّب — حتى لا تُكتب حالة في
 *     القاعدة لم تمرّ بمنطق الاشتقاق.
 */
final readonly class RecordAttendanceSheetAction
{
    public function __construct(
        private Transaction $transaction,
        private SessionParticipantAdministrationQueries $participants,
        private RecordAttendanceAction $record,
        private OverrideAttendanceAction $override,
        private ConfirmAttendanceAction $confirm,
    ) {}

    /**
     * @param array<string, string> $statuses معرّف ملف الطالب => الحالة المختارة
     * @return array{recorded: int, overridden: int, confirmed: int}
     */
    public function execute(
        string $organizationId,
        string $sessionId,
        array $statuses,
        string $actorId,
        ?string $reason = null,
    ): array {
        $participants = $this->participants->forSession($organizationId, $sessionId);

        if ($participants === []) {
            throw BusinessRuleViolation::make(
                'attendance.sheet.no_participants',
                'attendance::errors.sheet_no_participants',
            );
        }

        /** @var array<string, SessionParticipantAdministrationData> $byStudent */
        $byStudent = [];
        foreach ($participants as $participant) {
            $byStudent[$participant->studentProfileId] = $participant;
        }

        $unknown = array_diff(array_keys($statuses), array_keys($byStudent));

        if ($unknown !== []) {
            throw BusinessRuleViolation::make(
                'attendance.sheet.participant_outside_session',
                'attendance::errors.sheet_participant_outside_session',
            );
        }

        return $this->transaction->run(function () use ($statuses, $byStudent, $actorId, $reason, $organizationId): array {
            $tally = ['recorded' => 0, 'overridden' => 0, 'confirmed' => 0];

            foreach ($statuses as $studentProfileId => $rawStatus) {
                $participant = $byStudent[$studentProfileId];
                $target = AttendanceStatus::from($rawStatus);

                $attendance = Attendance::query()
                    ->where('session_participant_id', $participant->id)
                    ->first();

                if (!$attendance instanceof Attendance) {
                    $attendance = $this->create($participant, $target, $actorId, $organizationId);
                    $tally['recorded']++;
                }

                if ($attendance->derived_status === $target && $attendance->status === $target) {
                    $this->confirm->execute($attendance, $actorId, null, $organizationId);
                    $tally['confirmed']++;

                    continue;
                }

                $this->override->execute(
                    $attendance,
                    $target,
                    (string) $reason,
                    $actorId,
                    $organizationId,
                );
                $tally['overridden']++;
            }

            return $tally;
        });
    }

    private function create(
        SessionParticipantAdministrationData $participant,
        AttendanceStatus $target,
        string $actorId,
        string $organizationId,
    ): Attendance {
        // diffInMinutes تُعيد float في Carbon 3 — بلا التحويل يسقط النداء.
        $sessionMinutes = max(1, (int) round(CarbonImmutable::parse($participant->scheduledStart)
            ->diffInMinutes(CarbonImmutable::parse($participant->scheduledEnd))));

        [$attended, $joinedAfter, $leftBefore] = $this->minutesFor($target, $sessionMinutes);

        return $this->record->execute(
            sessionParticipantId: $participant->id,
            attendedMinutes: $attended,
            sessionMinutes: $sessionMinutes,
            joinedAfterMinutes: $joinedAfter,
            leftBeforeMinutes: $leftBefore,
            organizationId: $organizationId,
            actorId: $actorId,
            reason: (string) __('attendance::messages.sheet_recorded'),
        );
    }

    /**
     * دقائق تُنتج الحالة المختارة عبر `deriveFromMinutes` لا حولها.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function minutesFor(AttendanceStatus $target, int $sessionMinutes): array
    {
        $thresholds = (array) config('academic.attendance.thresholds');
        $late = (int) ($thresholds['late_after_minutes'] ?? 10);
        $leftEarly = (int) ($thresholds['left_early_before_minutes'] ?? 10);
        $partial = (int) ($thresholds['partial_min_percent'] ?? 40);

        return match ($target) {
            AttendanceStatus::Present => [$sessionMinutes, 0, 0],
            AttendanceStatus::Late => [max(1, $sessionMinutes - $late), $late, 0],
            AttendanceStatus::LeftEarly => [max(1, $sessionMinutes - $leftEarly), 0, $leftEarly],
            AttendanceStatus::Partial => [max(1, (int) round($sessionMinutes * ($partial + 5) / 100)), 0, 0],
            default => [0, 0, 0],
        };
    }
}
