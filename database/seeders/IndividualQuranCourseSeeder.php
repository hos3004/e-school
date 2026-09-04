<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\AssignTeacherQualificationsAction;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Models\StaffProfile;
use RuntimeException;

/** بذرة إنتاج محدودة وآمنة لإضافة مسار القرآن الفردي فقط. */
final class IndividualQuranCourseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('slug', 'telecourse')->first();
        if (!$organization instanceof Organization) {
            throw new RuntimeException('TeleCourse organization was not found.');
        }

        $source = Course::query()
            ->forOrganization((string) $organization->getKey())
            ->where('code', 'C-QURAN-101')
            ->with('level')
            ->first();
        if (!$source instanceof Course || !$source->level instanceof Level) {
            throw new RuntimeException('The existing Quran course was not found.');
        }

        $actor = User::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->orderBy('created_at')
            ->first();
        if (!$actor instanceof User) {
            throw new RuntimeException('An active TeleCourse operator was not found.');
        }

        $course = Course::query()
            ->withTrashed()
            ->forOrganization((string) $organization->getKey())
            ->where('code', 'C-QURAN-IND')
            ->first();
        if (!$course instanceof Course) {
            $course = app(CreateCourseAction::class)->execute([
                'organization_id' => (string) $organization->getKey(),
                'level_id' => (string) $source->level->getKey(),
                'code' => 'C-QURAN-IND',
                'name' => ['ar' => 'القرآن الفردي', 'en' => 'Individual Quran'],
                'description' => [
                    'ar' => 'حصص فردية لطالب واحد مع معلم مؤهل، بموعد من إتاحة المعلم ومدد 25 أو 35 أو 55 دقيقة.',
                    'en' => 'One-to-one Quran sessions booked from approved teacher availability for 25, 35, or 55 minutes.',
                ],
                'total_sessions' => 48,
                'session_mode' => SessionMode::Individual->value,
                'age_from' => 13,
                'age_to' => 60,
                'target_gender' => TargetGender::All->value,
                'default_duration_minutes' => 35,
                'sessions_per_week' => 2,
                'is_active' => true,
            ], (string) $actor->getKey(), 'إضافة مسار القرآن الفردي');
        }

        $this->qualifyExistingQuranTeachers($organization, $source, $course, $actor);
    }

    private function qualifyExistingQuranTeachers(
        Organization $organization,
        Course $source,
        Course $course,
        User $actor,
    ): void {
        $ids = app(TeacherQualificationQueries::class)
            ->qualifiedTeacherIdsForCourse((string) $source->getKey());
        $teachers = StaffProfile::query()
            ->forOrganization((string) $organization->getKey())
            ->whereKey($ids)
            ->get();

        foreach ($teachers as $teacher) {
            app(AssignTeacherQualificationsAction::class)->execute(
                $teacher,
                [(string) $course->getKey()],
                (string) $actor->getKey(),
                'تأهيل معلم القرآن للمسار الفردي',
            );
        }
    }
}
