<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Domain\Contracts\ProgramSessionReportDigestQueries;
use Modules\Reporting\Domain\ValueObjects\ProgramSessionReportDigestData;
use Modules\Reporting\Infrastructure\Mail\MonthlyProgramDigestMail;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * يُشغَّل تلقائيًا آخر يوم من كل شهر، ويدويًا لأي نطاق تاريخ مخصَّص
 * (يفوّض الأمرين لهذه الخدمة — القاعدة واحدة في المكانين).
 *
 * لا يمر عبر Outbox إشعارات الطلاب: هذا بريد إداري تشغيلي لمستلم واحد
 * مضبوط سلفًا، لا إشعار شخصي يخضع لتفضيلات مستلم أو ساعات هدوء.
 */
final readonly class MonthlyProgramDigestSender
{
    public function __construct(
        private ProgramSessionReportDigestQueries $digest,
        private ProgramDigestRecipientSettings $recipients,
        private AcademicCatalogQueries $catalog,
        private StudentDirectoryQueries $students,
    ) {}

    /** @return int عدد الرسائل المُرسَلة (برنامج واحد له بيانات = رسالة واحدة) */
    public function send(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
        string $periodFromLabel,
        string $periodToLabel,
        string $locale,
    ): int {
        $email = $this->recipients->resolveEmail($organizationId);
        if ($email === null) {
            return 0;
        }

        $rows = $this->digest->forOrganizationInRange($organizationId, $fromUtc, $untilUtcExclusive);
        if ($rows === []) {
            return 0;
        }

        $byProgram = [];
        foreach ($rows as $row) {
            $byProgram[$row->programId][] = $row;
        }

        $programIds = array_keys($byProgram);
        $programs = $this->catalog->programsByIds($organizationId, $programIds);

        $sent = 0;
        foreach ($byProgram as $programId => $programRows) {
            $program = $programs[$programId] ?? null;
            if ($program === null) {
                continue;
            }

            $studentIds = array_values(array_unique(array_map(
                static fn (ProgramSessionReportDigestData $row): string => $row->studentProfileId,
                $programRows,
            )));
            $names = $this->students->namesForProfiles($organizationId, $studentIds);

            $entriesByStudentName = [];
            foreach ($programRows as $row) {
                $studentName = $names[$row->studentProfileId] ?? $row->studentProfileId;
                $entriesByStudentName[$studentName][] = [
                    'submitted_at' => $row->submittedAt,
                    'topics_covered' => $row->topicsCovered,
                    'homework_assigned' => $row->homeworkAssigned,
                    'participation' => $row->participation,
                    'performance' => $row->performance,
                    'commitment' => $row->commitment,
                    'note' => $row->note,
                ];
            }

            Mail::to($email)->send(new MonthlyProgramDigestMail(
                programName: $program->name,
                periodFromLabel: $periodFromLabel,
                periodToLabel: $periodToLabel,
                entriesByStudentName: $entriesByStudentName,
                mailLocale: $locale,
            ));
            $sent++;
        }

        return $sent;
    }
}
