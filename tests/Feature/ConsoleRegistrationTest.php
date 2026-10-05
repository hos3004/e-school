<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConsoleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Course $course;

    private bool $canManage = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true, 'admission.self_registration.enabled' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $this->seed([GeographySeeder::class, AccessControlSeeder::class]);
        Event::fake([UserRegistered::class, RegistrationSubmitted::class, RegistrationAccepted::class]);
        $this->actor = User::query()->findOrFail(Fixtures::userId());
        $this->course = Course::query()->findOrFail(Fixtures::courseId());
        Gate::define('admin.panel.access', fn (): bool => true);
        Gate::define('student.create', fn (User $user): bool => $this->canManage && $user->id === $this->actor->id);
        $this->actingAs($this->actor);
    }

    public function test_publishes_real_course_form_and_preserves_answer_snapshots_and_old_translations(): void
    {
        $strict = Model::preventsSilentlyDiscardingAttributes();
        Model::preventSilentlyDiscardingAttributes();
        try {
            $this->post('/manage/registration/forms', $this->formData())->assertSessionHasNoErrors()->assertRedirect();
            $form = RegistrationForm::query()->where('slug', 'console-course-registration')->firstOrFail();
            $questions = $form->questions;
            $this->assertCount(2, $questions);
            $this->assertSame($this->course->id, $form->preferred_course_id);
            $this->assertDatabaseHas('audit_log', ['action' => 'students.registration_form_created', 'auditable_id' => $form->id]);
            $this->get('/register/student/'.$form->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Auth/RegisterStudent')->has('questions', 2)
                ->where('submitUrl', route('register.student.form.store', ['formSlug' => $form->slug])));
            $this->post('/register/student/'.$form->slug, [
                ...$this->applicantData(), 'evaluation' => [$questions[0]->id => 'المساء', $questions[1]->id => '8'],
                'preferred_course_id' => (string) Fixtures::courseId(), 'organization_id' => 'untrusted', 'user_id' => $this->actor->id,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $application = RegistrationApplication::query()->where('email', 'registration-student@test.local')->firstOrFail();
            $snapshot = $application->evaluation_answers;
            $this->assertSame($this->actor->organization_id, $application->organization_id);
            $this->assertSame($form->id, $application->registration_form_id);
            $this->assertSame($this->course->id, $application->preferred_course_id);
            $this->assertNull($application->user_id);
            $this->assertSame('المساء', $snapshot[0]['answer']);

            $form->update(['title' => ['ar' => 'قديم', 'en' => 'Legacy title'], 'description' => ['ar' => 'قديم', 'fr' => 'Description historique']]);
            $questions[0]->update(['question' => ['ar' => 'قديم', 'en' => 'Legacy question']]);
            $payload = $this->formData();
            $payload['title'] = 'النموذج المحدث';
            $payload['questions'] = [[...$payload['questions'][0], 'id' => $questions[0]->id, 'question' => 'الوقت المحدث']];
            $this->patch('/manage/registration/forms/'.$form->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('Legacy title', $form->fresh()->title['en']);
            $this->assertSame('Description historique', $form->fresh()->description['fr']);
            $this->assertSame('Legacy question', $questions[0]->fresh()->question['en']);
            $this->assertSoftDeleted('registration_questions', ['id' => $questions[1]->id]);
            $this->assertSame($snapshot, $application->fresh()->evaluation_answers);
            $this->get('/manage/registration/forms/create?template='.$form->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Console/RegistrationEditor')->where('form.id', null)->where('form.is_active', false)->where('form.questions.0.id', null));
            $this->get('/manage/registration?stage=requests&course='.$this->course->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Console/Registration')->has('applications.data', 1)->where('applications.data.0.id', $application->id));
        } finally {
            Model::preventSilentlyDiscardingAttributes($strict);
        }
    }

    public function test_rejects_foreign_destinations_questions_and_form_access_atomically(): void
    {
        $other = Organization::factory()->create();
        $program = Program::factory()->create(['organization_id' => $other->id]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['organization_id' => $other->id, 'level_id' => $level->id]);
        $this->post('/manage/registration/forms', [...$this->formData(), 'preferred_program_id' => $program->id, 'preferred_course_id' => $course->id])
            ->assertSessionHasErrors('preferred_course_id');
        $this->assertDatabaseCount('registration_forms', 0);
        $this->post('/manage/registration/forms', $this->formData())->assertSessionHasNoErrors();
        $form = RegistrationForm::query()->firstOrFail();
        $foreign = RegistrationForm::query()->create(['organization_id' => $other->id, 'slug' => 'foreign-form', 'title' => ['ar' => 'خاص'], 'is_active' => false]);
        $question = RegistrationQuestion::query()->create(['organization_id' => $other->id, 'registration_form_id' => $foreign->id, 'question' => ['ar' => 'خاص'], 'type' => 'text', 'is_active' => true]);
        $this->get('/manage/registration/forms/'.$foreign->id.'/edit')->assertNotFound();
        $this->get('/manage/registration/forms/create?template='.$foreign->id)->assertNotFound();
        $payload = $this->formData();
        $payload['title'] = 'لا تحفظ هذا التغيير';
        $payload['questions'][0]['id'] = $question->id;
        $this->patch('/manage/registration/forms/'.$form->id, $payload)->assertSessionHasErrors('questions.0.id');
        $this->assertSame('تسجيل المحادثة', $form->fresh()->title['ar']);
        $this->get('/manage/registration?course='.$course->id)->assertNotFound();
        $this->post('/manage/registration/forms', [...$this->formData(), 'organization_id' => $other->id])->assertSessionHasErrors('organization_id');
    }

    public function test_rejects_invalid_question_options_and_stopped_public_form_without_removing_history(): void
    {
        $data = $this->formData();
        $data['questions'][0]['options'] = ['نفس الاختيار', 'نفس الاختيار'];
        $this->post('/manage/registration/forms', $data)->assertSessionHasErrors('questions.0.options');
        $data = $this->formData();
        $data['questions'][0]['type'] = 'textarea';
        $this->post('/manage/registration/forms', $data)->assertSessionHasErrors('questions.0.is_filterable');
        $this->post('/manage/registration/forms', $this->formData())->assertSessionHasNoErrors();
        $form = RegistrationForm::query()->firstOrFail();
        $payload = [...$this->formData(), 'is_active' => false, 'questions' => []];
        $this->patch('/manage/registration/forms/'.$form->id, $payload)->assertSessionHasNoErrors();
        $this->get('/register/student/'.$form->slug)->assertNotFound();
        $this->assertDatabaseHas('registration_forms', ['id' => $form->id, 'is_active' => false, 'deleted_at' => null]);
    }

    public function test_public_acceptance_creates_account_and_student_on_original_request_once(): void
    {
        $application = $this->submittedApplication();
        $data = $this->acceptanceData();
        $this->post('/manage/registration/applications/'.$application->id.'/decision', $data)->assertSessionHasNoErrors()->assertRedirect();
        $application->refresh();
        $this->assertSame('waiting_assignment', $application->status->value);
        $this->assertNotNull($application->user_id);
        $this->assertNotNull($application->student_profile_id);
        $this->assertSame(1, RegistrationApplication::query()->count());
        $this->assertDatabaseHas('student_profiles', ['id' => $application->student_profile_id, 'user_id' => $application->user_id, 'organization_id' => $this->actor->organization_id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'students.registration_account_linked', 'auditable_id' => $application->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'academic_status.registration_accepted', 'auditable_id' => $application->id]);
        $this->post('/manage/registration/applications/'.$application->id.'/decision', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, StudentProfile::query()->count());
        $this->get('/manage/registration?stage=accepted')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('applications.data', 1)->where('applications.data.0.id', $application->id));
    }

    public function test_account_linking_requires_org_contact_match_and_never_replaces_a_student_profile(): void
    {
        $application = $this->submittedApplication();
        $other = Organization::factory()->create();
        $foreign = User::factory()->create(['organization_id' => $other->id, 'email' => 'foreign-existing@test.local']);
        $existing = ['decision' => 'accept', 'account_mode' => 'existing', 'existing_user_id' => $foreign->id, 'timezone' => 'Africa/Cairo'];
        $this->post('/manage/registration/applications/'.$application->id.'/decision', $existing)->assertNotFound();
        $wrong = User::query()->findOrFail(Fixtures::userId());
        $this->post('/manage/registration/applications/'.$application->id.'/decision', [...$existing, 'existing_user_id' => $wrong->id])->assertSessionHasErrors('form');
        $this->assertNull($application->fresh()->user_id);
        $matching = User::factory()->create(['organization_id' => $this->actor->organization_id, 'email' => $application->email]);
        $this->post('/manage/registration/applications/'.$application->id.'/decision', [...$existing, 'existing_user_id' => $matching->id])->assertSessionHasNoErrors();
        $this->assertSame($matching->id, $application->fresh()->user_id);
        $form = RegistrationForm::query()->firstOrFail();
        $this->post('/register/student/'.$form->slug, $this->applicantData())->assertSessionHasNoErrors();
        $duplicate = RegistrationApplication::query()->whereKeyNot($application->id)->firstOrFail();
        $this->post('/manage/registration/applications/'.$duplicate->id.'/decision', [
            ...$existing, 'existing_user_id' => $matching->id,
        ])->assertSessionHasErrors('identity_confirmed');
        $this->post('/manage/registration/applications/'.$duplicate->id.'/decision', [
            ...$existing, 'existing_user_id' => $matching->id, 'identity_confirmed' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame($application->fresh()->student_profile_id, $duplicate->fresh()->student_profile_id);
        $this->assertSame($matching->id, $duplicate->fresh()->user_id);
        $this->assertSame('waiting_assignment', $duplicate->fresh()->status->value);
        $this->assertSame(1, StudentProfile::query()->count());
    }

    public function test_filters_real_requests_by_source_select_and_number_answers(): void
    {
        $this->post('/manage/registration/forms', $this->formData())->assertSessionHasNoErrors();
        $form = RegistrationForm::query()->firstOrFail();
        $questions = $form->questions;
        $this->post('/register/student/'.$form->slug, [
            ...$this->applicantData(), 'evaluation' => [$questions[0]->id => 'المساء', $questions[1]->id => '8'],
        ])->assertSessionHasNoErrors();
        $evening = RegistrationApplication::query()->firstOrFail();
        $this->post('/register/student/'.$form->slug, [
            ...$this->applicantData(), 'email' => 'morning@test.local', 'evaluation' => [$questions[0]->id => 'الصباح', $questions[1]->id => '2'],
        ])->assertSessionHasNoErrors();
        $this->get('/manage/registration?'.http_build_query([
            'stage' => 'requests', 'form' => $form->id, 'question' => $questions[0]->id, 'answer' => 'المساء',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('applications.data', 1)->where('applications.data.0.id', $evening->id));
        $this->get('/manage/registration?'.http_build_query([
            'stage' => 'requests', 'question' => $questions[1]->id, 'answer_from' => 5, 'answer_to' => 10,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('applications.data', 1)->where('applications.data.0.id', $evening->id));
    }

    public function test_review_rejection_permissions_and_dynamic_filters_use_the_existing_workflow(): void
    {
        $application = $this->submittedApplication();
        $this->post('/manage/registration/applications/'.$application->id.'/decision', ['decision' => 'review'])->assertSessionHasNoErrors();
        $this->assertSame('under_review', $application->fresh()->status->value);
        $this->assertDatabaseHas('audit_log', ['action' => 'academic_status.registration_reviewed', 'auditable_id' => $application->id]);
        $this->post('/manage/registration/applications/'.$application->id.'/decision', ['decision' => 'reject'])->assertSessionHasErrors('rejection_category');
        $this->post('/manage/registration/applications/'.$application->id.'/decision', ['decision' => 'reject', 'rejection_category' => 'schedule'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $application->fresh()->status->value);
        $this->assertDatabaseHas('audit_log', ['action' => 'academic_status.registration_rejected', 'auditable_id' => $application->id]);
        $this->assertNotEmpty($application->fresh()->decision_reason);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->canManage = false;
        $this->get('/manage/registration')->assertForbidden();
        $this->post('/manage/registration/forms', $this->formData())->assertForbidden();
        $this->get('/manage/registration/applications/'.$application->id)->assertForbidden();
        $this->post('/manage/registration/applications/'.$application->id.'/decision', $this->acceptanceData())->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'title' => 'تسجيل المحادثة', 'description' => 'سجل بياناتك للدراسة', 'slug' => 'console-course-registration', 'is_active' => true,
            'preferred_program_id' => Level::query()->findOrFail($this->course->level_id)->program_id, 'preferred_course_id' => $this->course->id,
            'questions' => [
                ['id' => null, 'question' => 'الوقت المناسب', 'type' => 'select', 'options' => ['الصباح', 'المساء'], 'is_required' => true, 'is_active' => true, 'is_filterable' => true],
                ['id' => null, 'question' => 'عدد الأشهر', 'type' => 'number', 'options' => [], 'is_required' => false, 'is_active' => true, 'is_filterable' => true],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function applicantData(): array
    {
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');

        return [
            'full_name' => 'طالب التسجيل', 'email' => 'registration-student@test.local', 'phone' => null,
            'date_of_birth' => '2010-01-01', 'gender' => 'male', 'country_id' => $country->id, 'region_id' => $geography->regionsOf($country->id)[0]->id,
        ];
    }

    private function submittedApplication(): RegistrationApplication
    {
        $this->post('/manage/registration/forms', [...$this->formData(), 'questions' => []])->assertSessionHasNoErrors();
        $form = RegistrationForm::query()->firstOrFail();
        $this->post('/register/student/'.$form->slug, $this->applicantData())->assertSessionHasNoErrors();

        return RegistrationApplication::query()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function acceptanceData(): array
    {
        return [
            'decision' => 'accept', 'account_mode' => 'new', 'username' => 'accepted.student',
            'password' => 'SafeTestPassword2026!', 'password_confirmation' => 'SafeTestPassword2026!', 'timezone' => 'Africa/Cairo',
        ];
    }
}
