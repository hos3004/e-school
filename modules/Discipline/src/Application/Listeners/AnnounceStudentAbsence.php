<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Events\StudentAbsenceRecorded;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Guardians\Application\Queries\GuardianSummary;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * يحوّل كل غياب مُحتسَب إلى حدث إخطار يحمل معرّفات المستخدمين وسياق الرسالة.
 *
 * الإخطار في كل مرة لا عند العتبات فقط: سُلَّم التصعيد يُطبَّق عند 1 و2 و3،
 * لكن قرار المدرسة أن يصل الطالب تنبيهٌ عن كل غياب مع تذكير لبق بأن بلوغ
 * العتبة يعني تجميد القيد آليًا. لذلك مصدر هذا المستمع هو ViolationRecorded
 * الذي يقع مع كل مخالفة، لا DisciplineActionApplied الذي يقع عند العتبة.
 *
 * الغياب بعذر والحصة التي لم تُعقد لا تصلان هنا أصلًا: ViolationType يحدد
 * ما يُحتسب، وغير القابل للعدّ يُستبعد صراحةً قبل أي استعلام.
 */
final readonly class AnnounceStudentAbsence
{
    public function __construct(
        private Dispatcher $events,
        private StudentDirectoryQueries $students,
        private GuardianQuery $guardians,
        private SessionParticipantAdministrationQueries $participants,
    ) {}

    public function handle(ViolationRecorded $event): void
    {
        if (!$event->type->isCountable()) {
            return;
        }

        $student = $this->students->find($event->organizationId, $event->studentProfileId);

        if ($student === null || $student->userId === '') {
            return;
        }

        $participant = $this->participantFor($event);
        $threshold = $this->freezeThreshold();

        $this->events->dispatch(new StudentAbsenceRecorded(
            violationId: $event->violationId,
            organizationId: $event->organizationId,
            studentUserId: $student->userId,
            guardianUserIds: $this->guardianUserIds($event->studentProfileId),
            studentName: $this->studentName($event->organizationId, $event->studentProfileId),
            courseName: $participant?->sessionTitle ?? [],
            scheduledStart: $participant?->scheduledStart,
            violationType: $event->type->value,
            absenceCount: $event->countInWindow,
            freezeThreshold: $threshold,
            remainingBeforeFreeze: max(0, $threshold - $event->countInWindow),
            correlationId: $event->correlationId,
        ));
    }

    private function participantFor(ViolationRecorded $event): ?SessionParticipantAdministrationData
    {
        if ($event->sessionId === null) {
            return null;
        }

        foreach ($this->participants->forSession($event->organizationId, $event->sessionId) as $participant) {
            if ($participant->studentProfileId === $event->studentProfileId) {
                return $participant;
            }
        }

        return null;
    }

    private function studentName(string $organizationId, string $studentProfileId): string
    {
        $names = $this->students->namesForProfiles($organizationId, [$studentProfileId]);
        $name = $names[$studentProfileId] ?? '';

        return is_string($name) ? $name : '';
    }

    /** @return list<string> */
    private function guardianUserIds(string $studentProfileId): array
    {
        $ids = [];

        foreach ($this->guardians->guardiansForStudent($studentProfileId) as $guardian) {
            if ($guardian instanceof GuardianSummary && $guardian->userId !== '') {
                $ids[] = $guardian->userId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * أعلى عتبة تؤدي إلى تجميد القيد في سُلَّم config('discipline.ladder').
     * لا رقم ثابت هنا: لو غيّرت المدرسة السياسة تغيّر نص الرسالة معها.
     */
    private function freezeThreshold(): int
    {
        $threshold = 0;

        foreach ((array) config('discipline.ladder', []) as $step) {
            if (!is_array($step) || ($step['action'] ?? null) !== 'freeze_enrollment') {
                continue;
            }

            $threshold = max($threshold, (int) ($step['threshold'] ?? 0));
        }

        return $threshold;
    }
}
