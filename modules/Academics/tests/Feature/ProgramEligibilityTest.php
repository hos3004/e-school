<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Academics\Application\Services\EligibilityEvaluator;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Tests\TestCase;

final class ProgramEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_countries_list_means_all_countries_eligible(): void
    {
        $program = Program::create([
            'organization_id' => (string) Str::ulid(),
            'code' => 'PROG-ALL',
            'name' => ['ar' => 'عام للجميع'],
            'program_type' => ProgramType::Ongoing,
        ]);

        ProgramEligibility::create([
            'program_id' => $program->id,
            'countries' => [],
            'regions' => [],
            'manual_approval_required' => false,
        ]);

        /** @var EligibilityEvaluator $evaluator */
        $evaluator = app(EligibilityEvaluator::class);

        $facts = new ApplicantFacts(
            countryId: (string) Str::ulid(),
            regionId: (string) Str::ulid(),
        );

        $result = $evaluator->evaluate($program->id, $facts);

        $this->assertTrue($result->eligible);
        $this->assertEmpty($result->violations);
    }

    public function test_unlisted_country_triggers_violation(): void
    {
        $program = Program::create([
            'organization_id' => (string) Str::ulid(),
            'code' => 'PROG-RESTRICTED',
            'name' => ['ar' => 'محدد بالدولة'],
            'program_type' => ProgramType::Ongoing,
        ]);

        $allowedCountryId = (string) Str::ulid();

        ProgramEligibility::create([
            'program_id' => $program->id,
            'countries' => [$allowedCountryId],
            'regions' => [],
        ]);

        /** @var EligibilityEvaluator $evaluator */
        $evaluator = app(EligibilityEvaluator::class);

        $facts = new ApplicantFacts(
            countryId: (string) Str::ulid(), // different country ID
        );

        $result = $evaluator->evaluate($program->id, $facts);

        $this->assertFalse($result->eligible);
        $this->assertContains('eligibility.country_not_allowed', $result->violations);
    }

    public function test_age_out_of_range_triggers_violation(): void
    {
        $program = Program::create([
            'organization_id' => (string) Str::ulid(),
            'code' => 'PROG-AGE',
            'name' => ['ar' => 'محدد بالعمر'],
            'program_type' => ProgramType::Ongoing,
        ]);

        ProgramEligibility::create([
            'program_id' => $program->id,
            'age_from' => 10,
            'age_to' => 15,
        ]);

        /** @var EligibilityEvaluator $evaluator */
        $evaluator = app(EligibilityEvaluator::class);

        // 20 years old
        $facts = new ApplicantFacts(
            dateOfBirth: CarbonImmutable::now()->subYears(20),
        );

        $result = $evaluator->evaluate($program->id, $facts);

        $this->assertContains('eligibility.age_out_of_range', $result->violations);
    }

    public function test_teacher_gender_rule_retrieved_via_contract(): void
    {
        $program = Program::create([
            'organization_id' => (string) Str::ulid(),
            'code' => 'PROG-GENDER',
            'name' => ['ar' => 'محدد بالجنس'],
            'program_type' => ProgramType::Ongoing,
        ]);

        ProgramEligibility::create([
            'program_id' => $program->id,
            'teacher_gender_rule' => 'same',
        ]);

        /** @var ProgramRulesQueries $queries */
        $queries = app(ProgramRulesQueries::class);

        $this->assertSame('same', $queries->teacherGenderRule($program->id));
    }
}
