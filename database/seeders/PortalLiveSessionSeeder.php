<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * Fixture محلي معزول لتجربة صفحة الحصة من بوابتي المعلم والطالب.
 *
 * لا يُشغّل خارج local/testing، ويعتمد على مجموعة/كورس موجودين بدلاً من
 * اختراع بيانات أكاديمية عابرة للموديولات. الحسابان مخصصان للاختبار فقط.
 */
final class PortalLiveSessionSeeder extends Seeder
{
    public const TEACHER_EMAIL = 'portal.live.teacher@demo.local';

    public const STUDENT_EMAIL = 'portal.live.student@demo.local';

    public const SESSION_MARKER = 'demo:portal-live-session-v1';

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('PortalLiveSessionSeeder: تم التخطي خارج بيئة local/testing.');

            return;
        }

        $context = $this->teachingContext();

        if ($context === null) {
            $this->command?->warn('PortalLiveSessionSeeder: لا توجد مجموعة وكورس وبرنامج صالحون.');

            return;
        }

        $teacherUserId = $this->ensureUser(
            (string) $context->organization_id,
            self::TEACHER_EMAIL,
            'معلم الحصة التجريبية',
        );
        $studentUserId = $this->ensureUser(
            (string) $context->organization_id,
            self::STUDENT_EMAIL,
            'طالب الحصة التجريبية',
        );

        $teacherProfileId = $this->ensureTeacherProfile(
            (string) $context->organization_id,
            $teacherUserId,
        );
        $studentProfileId = $this->ensureStudentProfile(
            (string) $context->organization_id,
            $studentUserId,
        );

        $this->ensureTeacherContract((string) $context->organization_id, $teacherProfileId);
        $this->ensureTeacherQualification(
            $teacherProfileId,
            (string) $context->course_id,
            $teacherUserId,
        );
        $this->ensureGroupTeacher(
            (string) $context->group_id,
            $teacherProfileId,
            (string) $context->course_id,
        );
        $this->ensureGroupMembership((string) $context->group_id, $studentProfileId);

        $enrollmentId = $this->ensureEnrollment(
            (string) $context->organization_id,
            $studentProfileId,
            (string) $context->program_id,
        );

        $this->assignRole($teacherUserId, 'teacher');
        $this->assignRole($studentUserId, 'student');

        $session = $this->ensureLiveSession(
            (string) $context->organization_id,
            (string) $context->group_id,
            (string) $context->course_id,
            $teacherProfileId,
            $teacherUserId,
        );

        SessionParticipant::query()->firstOrCreate(
            [
                'session_id' => (string) $session->getKey(),
                'student_profile_id' => $studentProfileId,
            ],
            [
                'enrollment_id' => $enrollmentId,
                'join_url_token' => Str::random(48),
                'invited_at' => now()->utc(),
            ],
        );

        $this->command?->info(sprintf(
            'حسابا بوابتي الحصة: %s و%s · الحصة: %s',
            self::TEACHER_EMAIL,
            self::STUDENT_EMAIL,
            (string) $session->getKey(),
        ));
    }

    private function teachingContext(): ?object
    {
        return DB::table('groups')
            ->join('group_programs', 'group_programs.group_id', '=', 'groups.id')
            ->join('group_teachers', 'group_teachers.group_id', '=', 'groups.id')
            ->join('courses', 'courses.id', '=', 'group_teachers.course_id')
            ->whereNotNull('group_teachers.course_id')
            ->whereNull('groups.deleted_at')
            ->whereNull('courses.deleted_at')
            ->orderBy('groups.code')
            ->first([
                'groups.organization_id',
                'groups.id as group_id',
                'group_programs.program_id',
                'group_teachers.course_id',
            ]);
    }

    private function ensureUser(string $organizationId, string $email, string $name): string
    {
        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);

        $user->fill([
            'organization_id' => $organizationId,
            'name' => $name,
            'username' => Str::before($email, '@'),
            'password' => Hash::make('password'),
            'locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'email_verified_at' => now()->utc(),
            'status' => 'active',
        ]);
        $user->deleted_at = null;
        $user->save();

        return (string) $user->getKey();
    }

    private function ensureTeacherProfile(string $organizationId, string $userId): string
    {
        $profileId = DB::table('staff_profiles')->where('user_id', $userId)->value('id');
        $values = [
            'organization_id' => $organizationId,
            'staff_code' => 'DEMO-LIVE-TEACHER',
            'employment_type' => 'part_time',
            'gender' => 'male',
            'hired_at' => now()->subMonth()->toDateString(),
            'specializations' => json_encode(['online-learning'], JSON_THROW_ON_ERROR),
            'updated_at' => now()->utc(),
            'deleted_at' => null,
        ];

        if (!is_string($profileId) || $profileId === '') {
            $profileId = (string) Str::ulid();
            DB::table('staff_profiles')->insert($values + [
                'id' => $profileId,
                'user_id' => $userId,
                'created_at' => now()->utc(),
            ]);
        } else {
            DB::table('staff_profiles')->where('id', $profileId)->update($values);
        }

        return $profileId;
    }

    private function ensureStudentProfile(string $organizationId, string $userId): string
    {
        $profileId = DB::table('student_profiles')->where('user_id', $userId)->value('id');
        $values = [
            'organization_id' => $organizationId,
            'student_code' => 'DEMO-LIVE-STUDENT',
            'nationality' => 'EG',
            'country' => 'EG',
            'city' => 'القاهرة',
            'preferred_language' => 'ar',
            'joined_at' => now()->subMonth()->toDateString(),
            'updated_at' => now()->utc(),
            'deleted_at' => null,
        ];

        if (!is_string($profileId) || $profileId === '') {
            $profileId = (string) Str::ulid();
            DB::table('student_profiles')->insert($values + [
                'id' => $profileId,
                'user_id' => $userId,
                'created_at' => now()->utc(),
            ]);
        } else {
            DB::table('student_profiles')->where('id', $profileId)->update($values);
        }

        return $profileId;
    }

    private function ensureTeacherContract(string $organizationId, string $teacherProfileId): void
    {
        if (DB::table('teacher_contracts')->where('staff_profile_id', $teacherProfileId)->exists()) {
            return;
        }

        DB::table('teacher_contracts')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'staff_profile_id' => $teacherProfileId,
            'basis' => 'salary',
            'effective_from' => now()->subMonth()->toDateString(),
            'base_amount' => 0,
            'currency' => 'EGP',
            'created_at' => now()->utc(),
        ]);
    }

    private function ensureTeacherQualification(string $teacherProfileId, string $courseId, string $qualifiedBy): void
    {
        $qualification = DB::table('teacher_courses')
            ->where('staff_profile_id', $teacherProfileId)
            ->where('course_id', $courseId);

        if ($qualification->exists()) {
            $qualification->update([
                'qualified_at' => now()->utc(),
                'qualified_by' => $qualifiedBy,
                'updated_at' => now()->utc(),
            ]);

            return;
        }

        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(),
            'staff_profile_id' => $teacherProfileId,
            'course_id' => $courseId,
            'qualified_at' => now()->utc(),
            'qualified_by' => $qualifiedBy,
            'updated_at' => now()->utc(),
            'created_at' => now()->utc(),
        ]);
    }

    private function ensureGroupTeacher(string $groupId, string $teacherProfileId, string $courseId): void
    {
        $assignment = DB::table('group_teachers')
            ->where('group_id', $groupId)
            ->where('staff_profile_id', $teacherProfileId)
            ->where('course_id', $courseId);

        if ($assignment->exists()) {
            $assignment->update([
                'role' => 'lead',
                'assigned_to' => null,
            ]);

            return;
        }

        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'staff_profile_id' => $teacherProfileId,
            'course_id' => $courseId,
            'role' => 'lead',
            'assigned_from' => now()->subMonth()->toDateString(),
            'assigned_to' => null,
            'created_at' => now()->utc(),
        ]);
    }

    private function ensureGroupMembership(string $groupId, string $studentProfileId): void
    {
        $membership = DB::table('group_memberships')
            ->where('group_id', $groupId)
            ->where('student_profile_id', $studentProfileId)
            ->whereNull('left_at')
            ->first(['id']);

        if ($membership !== null) {
            DB::table('group_memberships')->where('id', $membership->id)->update(['status' => 'active']);

            return;
        }

        DB::table('group_memberships')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'joined_at' => now()->subMonth()->utc(),
            'status' => 'active',
            'created_at' => now()->utc(),
        ]);
    }

    private function ensureEnrollment(string $organizationId, string $studentProfileId, string $programId): string
    {
        $enrollmentId = DB::table('enrollments')
            ->where('student_profile_id', $studentProfileId)
            ->where('program_id', $programId)
            ->whereNull('deleted_at')
            ->value('id');

        $values = [
            'organization_id' => $organizationId,
            'status' => 'active',
            'applied_at' => now()->subMonth()->utc(),
            'activated_at' => now()->subMonth()->utc(),
            'updated_at' => now()->utc(),
        ];

        if (!is_string($enrollmentId) || $enrollmentId === '') {
            $enrollmentId = (string) Str::ulid();
            DB::table('enrollments')->insert($values + [
                'id' => $enrollmentId,
                'student_profile_id' => $studentProfileId,
                'program_id' => $programId,
                'created_at' => now()->utc(),
            ]);
        } else {
            DB::table('enrollments')->where('id', $enrollmentId)->update($values);
        }

        return $enrollmentId;
    }

    private function assignRole(string $userId, string $roleName): void
    {
        $roleId = DB::table('roles')
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->orderByRaw('organization_id nulls first')
            ->value('id');

        if (!is_string($roleId) || $roleId === '') {
            throw new \RuntimeException("PortalLiveSessionSeeder requires the {$roleName} role.");
        }

        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    private function ensureLiveSession(
        string $organizationId,
        string $groupId,
        string $courseId,
        string $teacherProfileId,
        string $changedBy,
    ): Session {
        $session = Session::query()->firstOrNew([
            'organization_id' => $organizationId,
            'notes' => self::SESSION_MARKER,
        ]);
        $isNew = !$session->exists;

        $session->fill([
            'group_id' => $groupId,
            'course_id' => $courseId,
            'staff_profile_id' => $teacherProfileId,
            'session_type' => 'group',
            'status' => SessionStatus::InProgress,
            'scheduled_start' => now()->subMinutes(10)->utc(),
            'scheduled_end' => now()->addMinutes(50)->utc(),
            'title' => ['ar' => 'حصة مباشرة تجريبية', 'en' => 'Demo live session'],
            'notes' => self::SESSION_MARKER,
        ]);
        $session->save();

        $hasStatusHistory = DB::table('session_status_history')
            ->where('session_id', (string) $session->getKey())
            ->exists();

        if ($isNew || !$hasStatusHistory) {
            DB::table('session_status_history')->insert([
                'id' => (string) Str::ulid(),
                'session_id' => (string) $session->getKey(),
                'from_status' => null,
                'to_status' => SessionStatus::Scheduled,
                'reason' => 'Fixture created for local portal testing.',
                'changed_by' => $changedBy,
                'changed_at' => now()->subMinutes(10)->utc(),
                'metadata' => null,
            ]);
            DB::table('session_status_history')->insert([
                'id' => (string) Str::ulid(),
                'session_id' => (string) $session->getKey(),
                'from_status' => SessionStatus::Scheduled,
                'to_status' => SessionStatus::InProgress,
                'reason' => 'Fixture is intentionally live during local testing.',
                'changed_by' => $changedBy,
                'changed_at' => now()->utc(),
                'metadata' => null,
            ]);
        }

        return $session;
    }
}
