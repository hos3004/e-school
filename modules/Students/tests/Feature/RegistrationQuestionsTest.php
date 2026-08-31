<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class RegistrationQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GeographySeeder::class);

        $this->form = RegistrationForm::query()->create([
            'organization_id' => Fixtures::organizationId(),
            'slug' => 'test-registration',
            'title' => ['ar' => 'نموذج الاختبار', 'en' => 'Test form'],
            'description' => ['ar' => 'وصف النموذج'],
            'is_active' => true,
        ]);
    }

    public function test_public_form_submission_stores_form_source_and_answer_snapshot(): void
    {
        $question = $this->createQuestion();

        $response = $this->post($this->formUrl(), [
            ...$this->validStudentData('questions@test.local'),
            'evaluation' => [$question->id => 'متوسط'],
        ]);

        $response->assertRedirect();

        $application = RegistrationApplication::query()
            ->where('email', 'questions@test.local')
            ->firstOrFail();
        $answers = $application->evaluation_answers;

        $this->assertSame($this->form->id, $application->registration_form_id);
        $this->assertCount(1, $answers);
        $this->assertSame($question->id, $answers[0]['question_id']);
        $this->assertSame(RegistrationQuestionType::Select->value, $answers[0]['type']);
        $this->assertSame('متوسط', $answers[0]['answer']);
    }

    public function test_missing_required_answer_is_rejected(): void
    {
        $question = $this->createQuestion();

        $response = $this->from($this->formUrl())->post(
            $this->formUrl(),
            $this->validStudentData('no-answer@test.local'),
        );

        $response->assertSessionHasErrors(["evaluation.{$question->id}"]);
        $this->assertDatabaseMissing('registration_applications', ['email' => 'no-answer@test.local']);
    }

    public function test_answer_outside_select_options_is_rejected(): void
    {
        $question = $this->createQuestion();

        $response = $this->post($this->formUrl(), [
            ...$this->validStudentData('invalid-answer@test.local'),
            'evaluation' => [$question->id => 'إجابة غير موجودة'],
        ]);

        $response->assertSessionHasErrors(["evaluation.{$question->id}"]);
    }

    public function test_question_from_another_form_is_rejected(): void
    {
        $otherForm = RegistrationForm::query()->create([
            'organization_id' => Fixtures::organizationId(),
            'slug' => 'other-form',
            'title' => ['ar' => 'نموذج آخر'],
            'is_active' => true,
        ]);
        $question = $this->createQuestion(['registration_form_id' => $otherForm->id]);

        $response = $this->post($this->formUrl(), [
            ...$this->validStudentData('foreign-question@test.local'),
            'evaluation' => [$question->id => 'متوسط'],
        ]);

        $response->assertSessionHasErrors(['evaluation']);
        $this->assertDatabaseMissing('registration_applications', ['email' => 'foreign-question@test.local']);
    }

    public function test_checkbox_answers_are_validated_and_stored_as_a_list(): void
    {
        $question = $this->createQuestion([
            'type' => RegistrationQuestionType::Checkbox,
            'options' => ['الصباح', 'المساء'],
            'is_filterable' => false,
        ]);

        $this->post($this->formUrl(), [
            ...$this->validStudentData('checkbox@test.local'),
            'evaluation' => [$question->id => ['الصباح', 'المساء']],
        ])->assertRedirect();

        $answer = RegistrationApplication::query()
            ->where('email', 'checkbox@test.local')
            ->firstOrFail()
            ->evaluation_answers[0]['answer'];

        $this->assertSame(['الصباح', 'المساء'], $answer);
    }

    /** @param array<string, mixed> $overrides */
    private function createQuestion(array $overrides = []): RegistrationQuestion
    {
        return RegistrationQuestion::query()->create([
            'organization_id' => Fixtures::organizationId(),
            'registration_form_id' => $this->form->id,
            'question' => ['ar' => 'ما مستواك في التحفيظ؟', 'en' => 'What is your memorization level?'],
            'type' => RegistrationQuestionType::Select,
            'options' => ['مبتدئ', 'متوسط', 'متقدم'],
            'is_required' => true,
            'is_active' => true,
            'is_filterable' => true,
            'sort_order' => 1,
            ...$overrides,
        ]);
    }

    /** @return array<string, string> */
    private function validStudentData(string $email): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');

        return [
            'full_name' => 'طالب اختبار',
            'email' => $email,
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $country->id,
            'region_id' => $geography->regionsOf($country->id)[0]->id,
        ];
    }

    private function formUrl(): string
    {
        return '/register/student/'.$this->form->slug;
    }
}
