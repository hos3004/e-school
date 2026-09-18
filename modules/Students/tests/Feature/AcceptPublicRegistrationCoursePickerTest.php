<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Database\Seeders\AcademicsSeeder;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Application\Actions\AcceptPublicRegistrationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * يغطي طلب المالك: يسمح مركز مراجعة الطلبات للإدارة باختيار برنامج/كورس
 * مختلف عمّا حدده الطالب عند التسجيل، أثناء مراجعة القبول نفسها لا بعدها،
 * فيُحدَّث preferred_course_id وpreferred_program_id معًا قبل أي تسكين لاحق.
 */
final class AcceptPublicRegistrationCoursePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
        // معرّف Fixtures::organizationId() لازم يُنشأ الآن، وإلا AcademicsSeeder
        // بينشئ مؤسسة "demo" بمعرّف ثابت غير متوافق مع تحقق ulid ويُصبح هو الأول.
        Fixtures::organizationId();
        $this->seed(AccessControlSeeder::class);
        $this->seed(AcademicsSeeder::class);
        Gate::before(static fn (): bool => true);
        $this->actingAs(User::query()->findOrFail(Fixtures::userId()));
    }

    public function test_admin_can_switch_the_course_during_the_accept_decision(): void
    {
        $originalCourseId = $this->courseId('C001');
        $newCourseId = $this->courseId('C002');

        $application = $this->submittedApplicationForCourse($originalCourseId);

        $accepted = app(AcceptPublicRegistrationAction::class)->execute(
            $application->organization_id,
            $application->id,
            Fixtures::userId(),
            [
                'decision' => 'accept',
                'account_mode' => 'new',
                'course_id' => $newCourseId,
                'username' => 'student.course.override',
                'password' => 'Uncommon-Registration-Password-2026',
                'timezone' => 'Africa/Cairo',
            ],
        );

        $application = RegistrationApplication::query()->findOrFail($accepted);
        $this->assertSame($newCourseId, $application->preferred_course_id);
        $this->assertSame($this->programIdFor($newCourseId), $application->preferred_program_id);
    }

    public function test_an_unknown_course_id_is_rejected_without_touching_the_application(): void
    {
        $originalCourseId = $this->courseId('C001');
        $application = $this->submittedApplicationForCourse($originalCourseId);

        try {
            app(AcceptPublicRegistrationAction::class)->execute(
                $application->organization_id,
                $application->id,
                Fixtures::userId(),
                [
                    'decision' => 'accept',
                    'account_mode' => 'new',
                    'course_id' => '01arz3ndektsv4rrffq69g5fav',
                    'username' => 'student.course.invalid',
                    'password' => 'Uncommon-Registration-Password-2026',
                    'timezone' => 'Africa/Cairo',
                ],
            );
            $this->fail('expected a ValidationException for the unknown course_id');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('course_id', $exception->errors());
        }

        $this->assertSame($originalCourseId, RegistrationApplication::query()->findOrFail($application->id)->preferred_course_id);
    }

    private function submittedApplicationForCourse(string $courseId): RegistrationApplication
    {
        $orgId = Fixtures::organizationId();
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf($egypt->id);

        $application = RegistrationApplication::query()->create([
            'organization_id' => $orgId,
            'status' => RegistrationStatus::Draft,
            'full_name' => 'Course Picker Student',
            'date_of_birth' => '2011-03-10',
            'gender' => 'male',
            'country_id' => $egypt->id,
            'region_id' => $regions[0]->id,
            'email' => 'course.picker.'.$courseId.'@test.local',
            'preferred_course_id' => $courseId,
            'preferred_program_id' => $this->programIdFor($courseId),
        ]);

        return app(SubmitRegistrationApplicationAction::class)->execute($application);
    }

    private function courseId(string $code): string
    {
        return (string) DB::table('courses')->where('code', $code)->value('id');
    }

    private function programIdFor(string $courseId): string
    {
        return (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $courseId)
            ->value('levels.program_id');
    }
}
