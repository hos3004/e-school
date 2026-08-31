<?php

declare(strict_types=1);

namespace Modules\Recordings\Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تهيئ أقل صف ممكن في سلسلة جداول الحصة والفصل — للاختبار فقط.
 *
 * Recordings لا يعرف جداول موديولات أخرى في كود التشغيل؛ هذا المساعد
 * يهيئ الصفوف الأجنبية اللازمة لقيد FK على recordings وفق هجراتها،
 * ولا يُستخدم خارج الاختبارات إطلاقًا.
 */
trait CreatesRecordingContext
{
    protected string $organizationId;

    /**
     * @return array{organization_id: string, session_id: string, classroom_id: string}
     */
    protected function createSessionWithClassroom(): array
    {
        $now = now()->utc();

        $this->organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $this->organizationId,
            'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-school-'.strtolower(substr($this->organizationId, -8)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $this->organizationId,
            'code' => 'PRG-T-'.substr(strtolower($programId), -8),
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

        $userId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $this->organizationId,
            'name' => 'معلم تجريبي',
            'email' => 'teacher-'.strtolower(substr($userId, -8)).'@test.local',
            'password' => 'x',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'staff_code' => 'STF-T-'.substr(strtolower($staffProfileId), -8),
            'employment_type' => 'full_time',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $this->organizationId,
            'level_id' => $levelId,
            'code' => 'CRS-T-'.substr(strtolower($courseId), -8),
            'name' => json_encode(['ar' => 'مادة اختبار', 'en' => 'Test Course'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $start = CarbonImmutable::instance(now()->utc())->subHour();

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'original_teacher_id' => $staffProfileId,
            'session_type' => 'regular',
            'status' => 'completed',
            'scheduled_start' => $start,
            'scheduled_end' => $start->addHour(),
            'title' => json_encode(['ar' => 'حصة اختبار', 'en' => 'Test Session'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $classroomId = (string) Str::ulid();
        DB::table('classrooms')->insert([
            'id' => $classroomId,
            'session_id' => $sessionId,
            'provider' => 'demo-provider',
            'external_id' => 'ext-'.$sessionId,
            'moderator_secret' => Str::random(16),
            'attendee_secret' => Str::random(16),
            'created_remote_at' => $start->subMinutes(10),
            'started_at' => $start,
            'ended_at' => $start->addHour(),
            'max_concurrent_participants' => 30,
            'health_status' => 'healthy',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'organization_id' => $this->organizationId,
            'session_id' => $sessionId,
            'classroom_id' => $classroomId,
        ];
    }
}
