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
