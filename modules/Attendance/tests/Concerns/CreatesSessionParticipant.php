<?php

declare(strict_types=1);

namespace Modules\Attendance\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تهيئ أقل صف ممكن في سلسلة جداول الحصة — للاختبار فقط.
 *
 * Attendance لا يعرف جداول موديولات أخرى في كود التشغيل؛ هذا المساعد
 * يهيئ الصفوف الأجنبية اللازمة لقيد FK على session_participants وفق
 * هجراتها، ولا يُستخدم خارج الاختبارات إطلاقًا.
 */
trait CreatesSessionParticipant
{
    protected string $organizationId;

    protected function createSessionParticipant(): string
    {
        $now = now()->utc();

        $this->organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $this->organizationId,
            'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-school-'.strtolower($this->organizationId),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $this->organizationId,
            'code' => 'PRG-T-'.substr(strtolower($programId), 0, 8),
            'name' => json_encode(['ar' => 'برنامج اختبار', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى الأول', 'en' => 'Level 1'], JSON_UNESCAPED_UNICODE),
        ]);

        $teacherUserId = $this->insertUser('معلم تجريبي', 'teacher');
        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $this->organizationId,
            'user_id' => $teacherUserId,
            'staff_code' => 'STF-T-'.substr(strtolower($staffProfileId), 0, 8),
            'employment_type' => 'full_time',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $this->organizationId,
            'level_id' => $levelId,
            'code' => 'CRS-T-'.substr(strtolower($courseId), 0, 8),
            'name' => json_encode(['ar' => 'مادة اختبار', 'en' => 'Test Course'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $studentUserId = $this->insertUser('طالب تجريبي', 'student');
        $studentProfileId = (string) Str::ulid();
        DB::table('student_profiles')->insert([
            'id' => $studentProfileId,
            'organization_id' => $this->organizationId,
            'user_id' => $studentUserId,
            'student_code' => 'STU-T-'.substr(strtolower($studentProfileId), 0, 8),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $studentProfileId,
            'program_id' => $programId,
            'status' => 'active',
            'applied_at' => $now,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => 'regular',
            'status' => 'awaiting_review',
            'scheduled_start' => $now->copy()->subHour(),
            'scheduled_end' => $now,
            'title' => json_encode(['ar' => 'حصة اختبار', 'en' => 'Test Session'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $participantId = (string) Str::ulid();
        DB::table('session_participants')->insert([
            'id' => $participantId,
            'session_id' => $sessionId,
            'student_profile_id' => $studentProfileId,
            'enrollment_id' => $enrollmentId,
            'join_url_token' => Str::random(32),
            'invited_at' => $now,
            'attended_minutes' => 0,
            'created_at' => $now,
        ]);

        return $participantId;
    }

    private function insertUser(string $name, string $prefix): string
    {
        $userId = (string) Str::ulid();

        DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $this->organizationId,
            'name' => $name,
            'email' => $prefix.'-'.strtolower($userId).'@test.local',
            'password' => 'x',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $userId;
    }
}
