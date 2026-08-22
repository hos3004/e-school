<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessments\Application\Actions\GradeAttemptAction;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Shared\Support\BusinessRuleViolation;

/**
 * بيانات تجريبية لموديول الاختبارات.
 *
 * يبني اختبار تحديد مستوى كاملًا بأسئلة، واختبارين قصيرين، ومحاولة
 * مُصحَّحة واحدة. يعتمد على مصانع الموديول لضمان صحة المفاتيح الأجنبية،
 * ولا ينشئ أي بيانات لموديول آخر.
 */
final class AssessmentsSeeder extends Seeder
{
    public function run(): void
    {
        $placement = Assessment::factory()
            ->ofType(AssessmentType::Placement)
            ->availableNow()
            ->create([
                'total_score' => 100,
                'passing_score' => 60,
                'max_attempts' => 1,
                'duration_minutes' => 90,
            ]);

        Question::factory()->count(6)->create([
            'assessment_id' => $placement->id,
        ])->each(function (Question $question, int $index): void {
            $question->forceFill(['sort_order' => $index + 1])->save();
        });

        $quizzes = Assessment::factory()->count(2)->create();

        foreach ($quizzes as $quiz) {
            Question::factory()->count(4)->create([
                'assessment_id' => $quiz->id,
            ]);
        }

        // محاولة مكتملة ومُصحَّحة على أول اختبار قصير — تمر عبر الإجراء
        // لتطبيق نفس قواعد التصحيح المستخدمة في الإنتاج.
        $gradedAttempt = AssessmentAttempt::factory()
            ->submitted()
            ->create([
                'assessment_id' => $quizzes->first()->id,
            ]);

        try {
            app(GradeAttemptAction::class)->execute($gradedAttempt, 70);
        } catch (BusinessRuleViolation) {
            // المحاولة صُحّحت مسبقًا في تشغيل سابق — لا شيء مطلوب.
        }
    }
}
