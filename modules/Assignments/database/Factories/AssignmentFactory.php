<?php

declare(strict_types=1);

namespace Modules\Assignments\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Assignments\Domain\Models\Assignment;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Assignment>
 */
final class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignedAt = CarbonImmutable::instance($this->faker->dateTimeBetween('-7 days', 'now'));

        return [
            'organization_id' => Fixtures::organizationId(),
            'course_id' => self::ensureCourseId(),
            'group_id' => null,
            'staff_profile_id' => Fixtures::staffProfileId(),
            'title' => ['ar' => 'واجب '.$this->faker->words(3, true), 'en' => $this->faker->sentence(4)],
            'instructions' => ['ar' => 'أجب عن الأسئلة كاملة.', 'en' => 'Answer all questions.'],
            'attachments' => [],
            'assigned_at' => $assignedAt,
            'due_at' => $assignedAt->addDays(7),
            'max_score' => 100,
            'allows_late' => true,
            'late_penalty_percent' => 10,
        ];
    }

    /** نشاط انقضى موعده. */
    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'assigned_at' => CarbonImmutable::now('UTC')->subDays(14),
            'due_at' => CarbonImmutable::now('UTC')->subDay(),
        ]);
    }

    /** نشاط لا يقبل التسليم المتأخر. */
    public function strictDeadline(): static
    {
        return $this->state(fn (): array => [
            'allows_late' => false,
            'late_penalty_percent' => 0,
        ]);
    }

    /**
     * مقرر موجود فعلًا لمؤسسة الاختبار — يُنشأ عبر DB عند الغياب،
     * لأن المقررات ملك موديول آخر ولا يجوز استيراد نماذجها.
     */
    private static function ensureCourseId(): string
    {
        $existing = DB::table('courses')
            ->where('organization_id', Fixtures::organizationId())
            ->whereNull('deleted_at')
            ->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => Fixtures::organizationId(),
            'code' => 'PRG-'.strtoupper(substr($programId, -8)),
            'name' => json_encode(['ar' => 'برنامج تجريبي', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'LVL-'.strtoupper(substr($levelId, -8)),
            'name' => json_encode(['ar' => 'مستوى تجريبي', 'en' => 'Test Level'], JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => Fixtures::organizationId(),
            'level_id' => $levelId,
            'code' => 'CRS-'.strtoupper(substr($courseId, -8)),
            'name' => json_encode(['ar' => 'مقرر تجريبي', 'en' => 'Test Course'], JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $courseId;
    }
}
