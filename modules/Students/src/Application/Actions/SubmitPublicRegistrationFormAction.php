<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Modules\Students\Domain\Contracts\RegistrationOfferingQueries;
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
        private RegistrationOfferingQueries $offerings,
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
            $form = RegistrationForm::query()->forOrganization($form->organization_id)->lockForUpdate()->findOrFail($form->id);
            if (!$form->is_active) {
                throw BusinessRuleViolation::make('registration.form_unavailable', 'students::errors.registration_form_unavailable');
            }
            if ($form->preferred_course_id !== null && !$this->offerings->isAvailable(
                $form->organization_id, (string) $form->preferred_program_id, $form->preferred_course_id,
            )) {
                throw BusinessRuleViolation::make('registration.offering_invalid', 'students::validation.registration_offering_invalid');
            }
            $evaluation = is_array($data['evaluation'] ?? null) ? $data['evaluation'] : [];
            unset($data['evaluation']);

            $application = $this->create->execute([
                ...$data,
                'registration_form_id' => (string) $form->getKey(),
                'preferred_program_id' => $form->preferred_program_id,
                'preferred_course_id' => $form->preferred_course_id,
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
