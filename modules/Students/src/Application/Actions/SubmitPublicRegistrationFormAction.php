<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** يحوّل إجابة نموذج عام منشور إلى طلب مقدّم، دون إنشاء طالب قبل الاعتماد. */
final readonly class SubmitPublicRegistrationFormAction
{
    public function __construct(
        private CreateRegistrationApplicationAction $create,
        private SubmitRegistrationApplicationAction $submit,
        private Transaction $transaction,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(RegistrationForm $form, array $data): RegistrationApplication
    {
        if (!$form->is_active || $form->trashed()) {
            throw BusinessRuleViolation::make(
                'registration.form_unavailable',
                'students::errors.registration_form_unavailable',
            );
        }

        return $this->transaction->run(function () use ($form, $data): RegistrationApplication {
            $evaluation = is_array($data['evaluation'] ?? null) ? $data['evaluation'] : [];
            unset($data['evaluation']);

            $application = $this->create->execute([
                ...$data,
                'registration_form_id' => (string) $form->getKey(),
                'evaluation_answers' => $this->answerSnapshot($form, $evaluation),
            ], $form->organization_id, null);

            return $this->submit->execute($application);
        });
    }

    /**
     * @param array<string, mixed> $answers
     * @return list<array{question_id: string, question: string, type: string, answer: string|list<string>}>
     */
    private function answerSnapshot(RegistrationForm $form, array $answers): array
    {
        $snapshot = [];

        $form->loadMissing(['questions' => static fn ($query) => $query->active()]);

        foreach ($form->questions as $question) {
            /** @var RegistrationQuestion $question */
            $answer = $answers[$question->id] ?? null;

            if ($answer === null || $answer === '' || $answer === []) {
                continue;
            }

            $normalized = $question->type === RegistrationQuestionType::Checkbox
                ? array_values(array_filter((array) $answer, 'is_string'))
                : (is_scalar($answer) ? (string) $answer : '');

            if ($normalized === '' || $normalized === []) {
                continue;
            }

            $snapshot[] = [
                'question_id' => (string) $question->getKey(),
                'question' => $question->localizedQuestion(),
                'type' => $question->type->value,
                'answer' => $normalized,
            ];
        }

        return $snapshot;
    }
}
