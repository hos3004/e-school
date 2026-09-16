<?php

declare(strict_types=1);

namespace Modules\Reporting\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Shared\Testing\Fixtures;

/**
 * سلسلة أدنى صالحة لقيود القاعدة عبر الحدود — منظمة/برنامج/مستوى/كورس/حصة —
 * لاختبارات موديول Reporting التي تجمّع تقارير حصص حسب البرنامج والتاريخ.
 *
 * تُنشأ مباشرة عبر DB مثل SessionReportFactory::createSessionContext لأن
 * موديول Reporting لا يستورد نماذج الموديولات الأخرى حتى في الاختبارات.
 */
final class DigestSessionContext
{
    /**
     * @return array{organization_id: string, program_id: string, course_id: string, staff_profile_id: string, session_id: string, group_id: ?string}
     */
    public static function create(
        ?CarbonImmutable $scheduledStart = null,
        string $status = 'completed',
        ?string $organizationId = null,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $groupId = null,
    ): array {
        $now = ($scheduledStart ?? CarbonImmutable::now('UTC'))->utc();
        $organizationId ??= Fixtures::organizationId();

        if ($courseId === null) {
            if ($programId === null) {
                $programId = (string) Str::ulid();
                DB::table('programs')->insert([
                    'id' => $programId,
                    'organization_id' => $organizationId,
                    'code' => 'PRG-DG-'.substr(strtolower($programId), -8),
                    'name' => json_encode(['ar' => 'برنامج الاختبار', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
                    'default_session_minutes' => 60,
                    'currency' => 'EGP',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $levelId = (string) Str::ulid();
            DB::table('levels')->insert([
                'id' => $levelId,
                'program_id' => $programId,
                'code' => 'L-'.substr(strtolower($levelId), -6),
                'name' => json_encode(['ar' => 'مستوى', 'en' => 'Level'], JSON_UNESCAPED_UNICODE),
            ]);

            $courseId = (string) Str::ulid();
            DB::table('courses')->insert([
                'id' => $courseId,
                'organization_id' => $organizationId,
                'level_id' => $levelId,
                'code' => 'CRS-DG-'.substr(strtolower($courseId), -8),
                'name' => json_encode(['ar' => 'كورس', 'en' => 'Course'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $programId ??= (string) DB::table('courses')
                ->join('levels', 'levels.id', '=', 'courses.level_id')
                ->where('courses.id', $courseId)
                ->value('levels.program_id');
        }

        $teacherUserId = Fixtures::userId();
        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $organizationId,
            'user_id' => $teacherUserId,
            'staff_code' => 'STF-DG-'.substr(strtolower($staffProfileId), -8),
            'employment_type' => 'per_session',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $organizationId,
            'group_id' => $groupId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'original_teacher_id' => $staffProfileId,
            'session_type' => 'regular',
            'status' => $status,
            'scheduled_start' => $now->copy()->subHour(),
            'scheduled_end' => $now,
            'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'organization_id' => $organizationId,
            'program_id' => (string) $programId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'session_id' => $sessionId,
            'group_id' => $groupId,
        ];
    }

    public static function submitReport(string $sessionId, string $staffProfileId, string $studentProfileId, ?CarbonImmutable $submittedAt = null): string
    {
        $reportId = (string) Str::ulid();

        DB::table('session_reports')->insert([
            'id' => $reportId,
            'session_id' => $sessionId,
            'staff_profile_id' => $staffProfileId,
            'topics_covered' => 'مواضيع الحصة',
            'homework_assigned' => 'الواجب',
            'general_notes' => 'ملاحظات',
            'supervisor_private_note' => null,
            'next_session_plan' => null,
            'submitted_at' => ($submittedAt ?? CarbonImmutable::now('UTC'))->toDateTimeString(),
            'is_late' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('session_report_students')->insert([
            'id' => (string) Str::ulid(),
            'session_report_id' => $reportId,
            'student_profile_id' => $studentProfileId,
            'participation' => 4,
            'performance' => 4,
            'commitment' => 4,
            'strengths' => null,
            'weaknesses' => null,
            'note' => null,
        ]);

        return $reportId;
    }
}
