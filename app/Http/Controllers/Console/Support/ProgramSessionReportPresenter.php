<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Modules\Reporting\Domain\ValueObjects\ProgramSessionReportDigestData;

/**
 * تحويل صفوف تجميع تقارير الحصص المسطَّحة إلى شكل مناسب للعرض: حسب البرنامج
 * ثم الطالب. يعيش هنا لا داخل المتحكم لأن شاشتين تستعملانه (لوحة اليوم/الأرشيف
 * وملف تعريف البرنامج) وتكراره كان سيفرّق سلوك التجميع بينهما بمرور الوقت.
 */
final class ProgramSessionReportPresenter
{
    /**
     * @param list<ProgramSessionReportDigestData> $rows
     * @param array<string, \Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData> $programsById
     * @param array<string, string> $studentNamesById
     * @return list<array{programId: string, programName: array<string, string>, studentCount: int, reportCount: int, students: list<array{studentId: string, studentName: string, entries: list<array<string, mixed>>}>}>
     */
    public function byProgramThenStudent(array $rows, array $programsById, array $studentNamesById): array
    {
        $byProgram = [];
        foreach ($rows as $row) {
            $byProgram[$row->programId][] = $row;
        }

        $result = [];
        foreach ($byProgram as $programId => $programRows) {
            $program = $programsById[$programId] ?? null;
            $students = $this->byStudent($programRows, $studentNamesById);

            $result[] = [
                'programId' => (string) $programId,
                'programName' => $program?->name ?? ['ar' => (string) $programId, 'en' => (string) $programId],
                'studentCount' => count($students),
                'reportCount' => count($programRows),
                'students' => $students,
            ];
        }

        return $result;
    }

    /**
     * @param list<ProgramSessionReportDigestData> $rows
     * @param array<string, string> $studentNamesById
     * @return list<array{studentId: string, studentName: string, entries: list<array<string, mixed>>}>
     */
    public function byStudent(array $rows, array $studentNamesById): array
    {
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[$row->studentProfileId][] = $row;
        }

        $result = [];
        foreach ($byStudent as $studentId => $studentRows) {
            $result[] = [
                'studentId' => (string) $studentId,
                'studentName' => $studentNamesById[$studentId] ?? (string) $studentId,
                'entries' => array_map(static fn (ProgramSessionReportDigestData $row): array => [
                    'sessionId' => $row->sessionId,
                    'submittedAt' => $row->submittedAt,
                    'topicsCovered' => $row->topicsCovered,
                    'homeworkAssigned' => $row->homeworkAssigned,
                    'generalNotes' => $row->generalNotes,
                    'participation' => $row->participation,
                    'performance' => $row->performance,
                    'commitment' => $row->commitment,
                    'strengths' => $row->strengths,
                    'weaknesses' => $row->weaknesses,
                    'note' => $row->note,
                ], $studentRows),
            ];
        }

        return $result;
    }
}
