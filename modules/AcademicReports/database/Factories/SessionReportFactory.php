<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<SessionReport>
 *
 * session_id مفتاح أجنبي لجدول الحصص — لا ULID عشوائي هنا؛ كل استدعاء
 * يُهيئ حصة جديدة بالحد الأدنى من السلسلة عبر DB مباشرة (نمط Fixtures).
 */
final class SessionReportFactory extends Factory
{
    protected $model = SessionReport::class;

    public function definition(): array
    {
        $endedAt = CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 5));

        $context = self::createSessionContext();

        return [
            'session_id' => $context['session_id'],
            'staff_profile_id' => $context['staff_profile_id'],
            'topics_covered' => 'المواضيع المغطاة في الحصة التجريبية',
            'homework_assigned' => 'واجب تجريبي للمراجعة',
            'general_notes' => $this->faker->sentence(),
            'supervisor_private_note' => null,
            'next_session_plan' => $this->faker->sentence(),
            'submitted_at' => $endedAt->addHours(2),
            'is_late' => false,
        ];
    }

    /**
     * حصة صالحة للقيود الأجنبية — سلسلة حد أدنى تُنشأ مباشرة عبر DB
     * لأن نماذج موديولات أخرى لا تُستورد عبر الحدود.
     *
     * @return array{session_id: string, staff_profile_id: string}
     */
    public static function createSessionContext(): array
    {
        $now = now()->utc();
        $organizationId = Fixtures::organizationId();

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => 'PRG-SR-'.substr(strtolower($programId), -8),
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

        $teacherUserId = Fixtures::userId();
        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $organizationId,
            'user_id' => $teacherUserId,
            'staff_code' => 'STF-SR-'.substr(strtolower($staffProfileId), -8),
            'employment_type' => 'per_session',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $organizationId,
            'level_id' => $levelId,
            'code' => 'CRS-SR-'.substr(strtolower($courseId), -8),
            'name' => json_encode(['ar' => 'مادة اختبار', 'en' => 'Test Course'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $organizationId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            // original_teacher_id NOT NULL منذ 2026_08_22_150000: لم تُستبدل
            // الحصة بعد فالأصلي هو الفعلي نفسه.
            'original_teacher_id' => $staffProfileId,
            'session_type' => 'regular',
            'status' => 'completed',
            'scheduled_start' => $now->copy()->subHour(),
            'scheduled_end' => $now,
            'title' => json_encode(['ar' => 'حصة اختبار', 'en' => 'Test Session'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'session_id' => $sessionId,
            'staff_profile_id' => $staffProfileId,
        ];
    }

    public function late(): static
    {
        return $this->state(fn (): array => ['is_late' => true]);
    }
}
