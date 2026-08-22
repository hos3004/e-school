<?php

declare(strict_types=1);

namespace Modules\Students\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * بيانات تجريبية لملفات الطلاب.
 *
 * ينشئ مستخدمين تجريبيين مصغّرين إن لم يوجدوا، ثم يسجّل لكل منهم
 * ملف طالب برقم فريد داخل المؤسسة التجريبية.
 */
final class StudentsSeeder extends Seeder
{
    private const DEMO_ORGANIZATION_ID = '01JDEMOORGANIZATION0000000';

    /**
     * أسماء عربية للعرض في البيئة التجريبية.
     *
     * @var list<string>
     */
    private const DEMO_NAMES = [
        'أحمد محمود',
        'سارة خالد',
        'يوسف عبد الله',
        'ليلى حسن',
        'عمر فاروق',
    ];

    public function run(): void
    {
        $organizationId = $this->ensureOrganization();

        foreach (self::DEMO_NAMES as $index => $name) {
            $userId = $this->ensureDemoUser($index, $name);

            StudentProfile::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'organization_id' => $organizationId,
                    'student_code' => sprintf('STU-%04d', $index + 1),
                    'date_of_birth' => now()->subYears(12 + $index)->toDateString(),
                    'gender' => $index % 2 === 0 ? StudentGender::Male : StudentGender::Female,
                    'nationality' => 'EG',
                    'country' => 'EG',
                    'city' => __('students::messages.demo_city'),
                    'preferred_language' => 'ar',
                    'joined_at' => now()->subMonths($index + 1)->toDateString(),
                ],
            );
        }
    }

    /**
     * المؤسسة يملكها موديول Organization — هذا البذر يستهلك الموجودة
     * ولا ينشئ واحدة، حفاظًا على حدود الموديولات.
     */
    private function ensureOrganization(): string
    {
        $existing = DB::table('organizations')->orderBy('created_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        DB::table('organizations')->insert([
            'id' => self::DEMO_ORGANIZATION_ID,
            'name' => json_encode(['ar' => __('students::messages.demo_school_name'), 'en' => 'Demo School'], JSON_UNESCAPED_UNICODE),
            'slug' => 'demo-school',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::DEMO_ORGANIZATION_ID;
    }

    private function ensureDemoUser(int $index, string $name): string
    {
        $existing = DB::table('users')
            ->where('email', $this->demoEmail($index))
            ->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $userId = (string) Str::ulid();

        DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $this->ensureOrganization(),
            'name' => $name,
            'email' => $this->demoEmail($index),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    private function demoEmail(int $index): string
    {
        return sprintf('student%d@demo.local', $index + 1);
    }
}
