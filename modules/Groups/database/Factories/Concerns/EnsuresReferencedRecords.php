<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * يضمن وجود الصفوف الأب التي تشير إليها جداول المجموعات عبر مفاتيح خارجية
 * (مؤسسة، مستخدم، ملف طالب، ملف معلم) في بيئات الاختبار والعرض.
 *
 * هذه الصفوف تُنشأ على مستوى قاعدة البيانات مباشرة — دون استيراد نماذج
 * من موديولات أخرى، حفاظًا على حدود الموديولات.
 */
trait EnsuresReferencedRecords
{
    public static function ensureOrganization(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = self::newUlid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-'.strtolower(substr($id, -10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * مستخدم جديد لكل استدعاء — لأن user_id فريد في ملفات الطلاب والمعلمين.
     */
    public static function ensureUser(): string
    {
        $id = self::newUlid();
        $suffix = strtolower(substr($id, -10));

        DB::table('users')->insert([
            'id' => $id,
            'organization_id' => self::ensureOrganization(),
            'name' => 'مستخدم اختبار',
            'email' => "test-{$suffix}@example.local",
            'username' => "user_{$suffix}",
            'password' => app('hash')->make((string) str()->random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public static function ensureStudentProfile(): string
    {
        $id = self::newUlid();

        DB::table('student_profiles')->insert([
            'id' => $id,
            'organization_id' => self::ensureOrganization(),
            'user_id' => self::ensureUser(),
            'student_code' => 'STU-T-'.strtolower(substr($id, -8)),
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public static function ensureStaffProfile(): string
    {
        $id = self::newUlid();

        DB::table('staff_profiles')->insert([
            'id' => $id,
            'organization_id' => self::ensureOrganization(),
            'user_id' => self::ensureUser(),
            'staff_code' => 'STF-T-'.strtolower(substr($id, -8)),
            'employment_type' => 'full_time',
            'hired_at' => now()->subYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * برنامج حقيقي في جدول programs — يلزم لربط البرامج بالمجموعات.
     */
    public static function ensureProgram(): string
    {
        $id = self::newUlid();

        DB::table('programs')->insert([
            'id' => $id,
            'organization_id' => self::ensureOrganization(),
            'code' => 'PRG-T-'.strtolower(substr($id, -8)),
            'name' => json_encode(['ar' => 'برنامج اختبار', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * ULID صالح بطول 26 حرفًا.
     */
    public static function newUlid(): string
    {
        return (string) \Illuminate\Support\Str::ulid();
    }
}
