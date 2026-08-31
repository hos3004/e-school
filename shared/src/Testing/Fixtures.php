<?php

declare(strict_types=1);

namespace Shared\Testing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ثوابت تجريبية مشتركة بين مصانع الموديولات.
 *
 * المشكلة التي تحلّها: كل مصنع كان يولّد organization_id عشوائيًا،
 * فيسقط على قيد المفتاح الأجنبي. المؤسسة يملكها موديول Organization،
 * ولا يجوز لمصنع موديول آخر أن يستورد نموذجها — لذلك نكتب الصف مباشرة
 * عبر DB، وهو المسار الوحيد المقبول عبر الحدود في طبقة الاختبارات.
 */
final class Fixtures
{
    private static ?string $organizationId = null;

    /**
     * معرّف مؤسسة موجودة فعلًا — تُنشأ مرة واحدة لكل دورة اختبار.
     */
    public static function organizationId(): string
    {
        if (self::$organizationId !== null && self::exists(self::$organizationId)) {
            return self::$organizationId;
        }

        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return self::$organizationId = $existing;
        }

        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة الاختبار', 'en' => 'Test Organization'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-'.strtolower(substr($id, -10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::$organizationId = $id;
    }

    /**
     * معرّف مستخدم موجود فعلًا، ينتمي لمؤسسة الاختبار.
     */
    public static function userId(): string
    {
        $id = (string) Str::ulid();
        $suffix = strtolower(substr($id, -8));

        DB::table('users')->insert([
            'id' => $id,
            'organization_id' => self::organizationId(),
            'name' => 'Test User '.$suffix,
            'email' => 'user.'.$suffix.'@test.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * معرّف ملف طالب موجود فعلًا.
     */
    public static function studentProfileId(): string
    {
        $id = (string) Str::ulid();

        DB::table('student_profiles')->insert([
            'id' => $id,
            'organization_id' => self::organizationId(),
            'user_id' => self::userId(),
            'student_code' => 'S'.strtoupper(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * معرّف ملف موظف موجود فعلًا.
     */
    public static function staffProfileId(): string
    {
        $id = (string) Str::ulid();

        DB::table('staff_profiles')->insert([
            'id' => $id,
            'organization_id' => self::organizationId(),
            'user_id' => self::userId(),
            'staff_code' => 'T'.strtoupper(substr($id, -8)),
            'employment_type' => 'per_session',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * معرّف ملف طالب مرتبط بحساب مستخدم معطى — لفحوص الملكية.
     */
    public static function studentProfileForUser(string $userId): string
    {
        $id = (string) Str::ulid();

        DB::table('student_profiles')->insert([
            'id' => $id,
            'organization_id' => self::organizationId(),
            'user_id' => $userId,
            'student_code' => 'S'.strtoupper(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * معرّف كورس موجود فعلًا، مع البرنامج والمستوى اللذين يتطلبهما مفتاحه الأجنبي.
     */
    public static function courseId(): string
    {
        $organizationId = self::organizationId();

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => 'FX-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج الاختبار', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'FX-L1',
            'name' => json_encode(['ar' => 'المستوى الأول', 'en' => 'Level one'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $organizationId,
            'level_id' => $levelId,
            'code' => 'FX-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'كورس الاختبار', 'en' => 'Test Course'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $courseId;
    }

    /**
     * يعتمد ملف معلم على كورس — يجعل `isQualified()` يعيد true في الاختبارات.
     */
    public static function qualifyTeacher(string $staffProfileId, string $courseId): void
    {
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(),
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'qualified_at' => now(),
            'qualified_by' => self::userId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * معرّف ملف موظف نشط مرتبط بحساب المستخدم المعطى.
     */
    public static function staffProfileForUser(string $userId): string
    {
        $id = (string) Str::ulid();

        DB::table('staff_profiles')->insert([
            'id' => $id,
            'organization_id' => self::organizationId(),
            'user_id' => $userId,
            'staff_code' => 'T'.strtoupper(substr($id, -8)),
            'employment_type' => 'per_session',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * يُستدعى بين الاختبارات لأن RefreshDatabase يمسح الجداول.
     */
    public static function flush(): void
    {
        self::$organizationId = null;
    }

    private static function exists(string $id): bool
    {
        return DB::table('organizations')->where('id', $id)->exists();
    }
}
