<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Modules\Students\Application\Queries\RegistrationApplicationFilterService;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * فلاتر شاشة طلبات التسجيل.
 *
 * كل اختبار هنا يحرس قاعدة واحدة، ويفشل فعلًا لو حُذفت تلك القاعدة — لا
 * اختبارات تمر لمجرد أن الكود يعمل.
 */
final class RegistrationApplicationFiltersTest extends TestCase
{
    use RefreshDatabase;

    private string $organizationId;

    /** @var list<RegionData> */
    private array $regions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);

        $this->organizationId = Fixtures::organizationId();

        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $this->regions = $geography->regionsOf($egypt->id);
    }

    public function test_region_filter_narrows_results_to_one_governorate(): void
    {
        $this->application(['region_id' => $this->regions[0]->id, 'full_name' => 'Cairo Student']);
        $this->application(['region_id' => $this->regions[1]->id, 'full_name' => 'Giza Student']);

        $matches = RegistrationApplication::query()
            ->forOrganization($this->organizationId)
            ->where('region_id', $this->regions[0]->id)
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Cairo Student'], $matches);
    }

    public function test_age_range_filter_translates_to_a_birth_date_window(): void
    {
        // التاريخ المرجعي ثابت حتى لا يتغير معنى الاختبار بمرور الأيام.
        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00', 'UTC'));

        $this->application(['date_of_birth' => '2016-08-25', 'full_name' => 'Exactly ten']);
        $this->application(['date_of_birth' => '2014-01-01', 'full_name' => 'Twelve years']);
        $this->application(['date_of_birth' => '2020-01-01', 'full_name' => 'Six years']);

        $names = $this->filters()
            ->applyAgeRange(
                RegistrationApplication::query()->forOrganization($this->organizationId),
                10,
                12,
                'UTC',
            )
            ->orderBy('full_name')
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Exactly ten', 'Twelve years'], $names);

        Carbon::setTestNow();
    }

    public function test_minimum_age_alone_excludes_younger_applicants(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00', 'UTC'));

        $this->application(['date_of_birth' => '2000-01-01', 'full_name' => 'Adult']);
        $this->application(['date_of_birth' => '2022-01-01', 'full_name' => 'Toddler']);

        $names = $this->filters()
            ->applyAgeRange(
                RegistrationApplication::query()->forOrganization($this->organizationId),
                18,
                null,
                'UTC',
            )
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Adult'], $names);

        Carbon::setTestNow();
    }

    public function test_language_filter_matches_the_linked_student_profile(): void
    {
        $arabic = $this->applicationWithProfile('ar', 'Arabic speaker');
        $this->applicationWithProfile('fr', 'French speaker');

        $names = $this->filters()
            ->applyLanguage(
                RegistrationApplication::query()->forOrganization($this->organizationId),
                ['ar'],
            )
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Arabic speaker'], $names);
        $this->assertNotNull($arabic->student_profile_id);
    }

    public function test_combining_region_language_and_age_filters_intersects_them(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00', 'UTC'));

        $this->applicationWithProfile('ar', 'Wanted', [
            'region_id' => $this->regions[0]->id,
            'date_of_birth' => '2012-01-01',
        ]);
        // نفس المنطقة واللغة لكن خارج نطاق العمر.
        $this->applicationWithProfile('ar', 'Too young', [
            'region_id' => $this->regions[0]->id,
            'date_of_birth' => '2023-01-01',
        ]);
        // نفس العمر والمنطقة لكن لغة أخرى.
        $this->applicationWithProfile('fr', 'Wrong language', [
            'region_id' => $this->regions[0]->id,
            'date_of_birth' => '2012-01-01',
        ]);
        // نفس كل شيء لكن محافظة أخرى.
        $this->applicationWithProfile('ar', 'Wrong region', [
            'region_id' => $this->regions[1]->id,
            'date_of_birth' => '2012-01-01',
        ]);

        $query = RegistrationApplication::query()
            ->forOrganization($this->organizationId)
            ->where('region_id', $this->regions[0]->id);
        $query = $this->filters()->applyLanguage($query, ['ar']);
        $query = $this->filters()->applyAgeRange($query, 10, 20, 'UTC');

        $this->assertSame(['Wanted'], $query->pluck('full_name')->all());

        Carbon::setTestNow();
    }

    public function test_filterable_select_question_narrows_by_its_answer(): void
    {
        $question = $this->question(RegistrationQuestionType::Select, true, ['مبتدئ', 'متوسط']);

        $this->application([
            'full_name' => 'Beginner',
            'evaluation_answers' => [[
                'question_id' => (string) $question->getKey(),
                'question' => $question->localizedQuestion(),
                'answer' => 'مبتدئ',
            ]],
        ]);
        $this->application([
            'full_name' => 'Intermediate',
            'evaluation_answers' => [[
                'question_id' => (string) $question->getKey(),
                'question' => $question->localizedQuestion(),
                'answer' => 'متوسط',
            ]],
        ]);

        $names = $this->filters()
            ->applySelectAnswer(
                RegistrationApplication::query()->forOrganization($this->organizationId),
                (string) $question->getKey(),
                ['مبتدئ'],
            )
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Beginner'], $names);
    }

    public function test_filterable_number_question_narrows_by_range(): void
    {
        $question = $this->question(RegistrationQuestionType::Number, true);

        foreach ([['Five', '5'], ['Twenty', '20'], ['NotANumber', 'كثير']] as [$name, $answer]) {
            $this->application([
                'full_name' => $name,
                'evaluation_answers' => [[
                    'question_id' => (string) $question->getKey(),
                    'question' => $question->localizedQuestion(),
                    'answer' => $answer,
                ]],
            ]);
        }

        $names = $this->filters()
            ->applyNumberAnswerRange(
                RegistrationApplication::query()->forOrganization($this->organizationId),
                (string) $question->getKey(),
                10.0,
                30.0,
            )
            ->pluck('full_name')
            ->all();

        // الإجابة غير الرقمية تُتجاهل بلا خطأ في الاستعلام.
        $this->assertSame(['Twenty'], $names);
    }

    public function test_a_question_not_marked_filterable_is_never_offered_as_a_filter(): void
    {
        $this->question(RegistrationQuestionType::Select, false, ['نعم', 'لا']);
        $allowed = $this->question(RegistrationQuestionType::Select, true, ['نعم', 'لا']);

        $ids = array_map(
            static fn ($question): string => $question->id,
            $this->filters()->filterableQuestions($this->organizationId),
        );

        $this->assertSame([(string) $allowed->getKey()], $ids);
    }

    public function test_free_text_questions_can_never_become_filterable(): void
    {
        // القيد على مستوى قاعدة البيانات هو الحارس الأخير، لا الواجهة وحدها.
        $this->expectException(QueryException::class);

        $this->question(RegistrationQuestionType::Textarea, true);
    }

    public function test_filterable_questions_of_another_organization_are_not_offered(): void
    {
        $this->question(RegistrationQuestionType::Select, true, ['نعم', 'لا']);

        $this->assertSame([], $this->filters()->filterableQuestions((string) Str::ulid()));
    }

    private function filters(): RegistrationApplicationFilterService
    {
        return app(RegistrationApplicationFilterService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function application(array $attributes = []): RegistrationApplication
    {
        return RegistrationApplication::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => Fixtures::userId(),
            'status' => RegistrationStatus::Submitted,
            'full_name' => 'Test Applicant',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $this->regions[0]->countryId ?? $this->countryId(),
            'region_id' => $this->regions[0]->id,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function applicationWithProfile(
        string $language,
        string $name,
        array $attributes = [],
    ): RegistrationApplication {
        $userId = Fixtures::userId();

        $profile = StudentProfile::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'student_code' => 'E'.mb_substr((string) Str::ulid(), -6),
            'preferred_language' => $language,
            'joined_at' => now()->toDateString(),
        ]);

        return $this->application([
            'user_id' => $userId,
            'full_name' => $name,
            // الحالة المسموح بها للتسكين تشترط وجود ملف طالب — قيد في القاعدة.
            'status' => RegistrationStatus::WaitingAssignment,
            'student_profile_id' => (string) $profile->getKey(),
            ...$attributes,
        ]);
    }

    /** @param list<string>|null $options */
    private function question(
        RegistrationQuestionType $type,
        bool $filterable,
        ?array $options = null,
    ): RegistrationQuestion {
        return RegistrationQuestion::query()->create([
            'organization_id' => $this->organizationId,
            'question' => ['ar' => 'سؤال اختبار', 'en' => 'Test question'],
            'type' => $type,
            'options' => $options,
            'is_required' => false,
            'is_active' => true,
            'is_filterable' => $filterable,
            'sort_order' => 0,
        ]);
    }

    private function countryId(): string
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        return $geography->findCountryByIso2('EG')->id;
    }
}
