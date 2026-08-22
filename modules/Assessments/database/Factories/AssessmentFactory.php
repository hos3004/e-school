<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\Assessment;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Assessment>
 */
final class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        $availableFrom = CarbonImmutable::now('UTC')
            ->subDays($this->faker->numberBetween(0, 3))
            ->setTime(8, 0, 0);
        $availableTo = $availableFrom->addDays($this->faker->numberBetween(7, 30));

        return [
            'organization_id' => Fixtures::organizationId(),
            'course_id' => null,
            'type' => AssessmentType::Quiz,
            'title' => [
                'ar' => 'اختبار تجريبي: '.$this->faker->word(),
                'en' => 'Sample assessment: '.$this->faker->word(),
            ],
            'instructions' => [
                'ar' => 'أجب عن جميع الأسئلة في الوقت المحدد.',
                'en' => 'Answer all questions within the allotted time.',
            ],
            'total_score' => 100,
            'passing_score' => 50,
            'duration_minutes' => 60,
            'max_attempts' => 2,
            'available_from' => $availableFrom,
            'available_to' => $availableTo,
            'created_by' => Fixtures::userId(),
        ];
    }

    public function ofType(AssessmentType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function forCourse(string $courseId): static
    {
        return $this->state(fn (): array => ['course_id' => $courseId]);
    }

    public function availableNow(): static
    {
        return $this->state(fn (): array => [
            'available_from' => CarbonImmutable::now('UTC')->subHour(),
            'available_to' => CarbonImmutable::now('UTC')->addDays(7),
        ]);
    }
}
