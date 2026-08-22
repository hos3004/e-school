<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Domain\Models\Question;

/**
 * @extends Factory<Question>
 */
final class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'type' => QuestionType::Mcq,
            'body' => [
                'ar' => 'ما الإجابة الصحيحة عن السؤال التجريبي؟',
                'en' => 'What is the correct answer to this sample question?',
            ],
            'options' => [
                ['key' => 'a', 'text' => ['ar' => 'الخيار الأول', 'en' => 'First option']],
                ['key' => 'b', 'text' => ['ar' => 'الخيار الثاني', 'en' => 'Second option']],
                ['key' => 'c', 'text' => ['ar' => 'الخيار الثالث', 'en' => 'Third option']],
            ],
            'correct_answer' => ['key' => 'a'],
            'score' => 10,
            'sort_order' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'options' => $type === QuestionType::TrueFalse
                ? [
                    ['key' => 'true', 'text' => ['ar' => 'صحيح', 'en' => 'True']],
                    ['key' => 'false', 'text' => ['ar' => 'خطأ', 'en' => 'False']],
                ]
                : null,
            'correct_answer' => $type === QuestionType::Essay ? null : ['key' => 'a'],
        ]);
    }
}
