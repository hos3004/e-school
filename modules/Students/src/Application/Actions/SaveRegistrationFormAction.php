<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Students\Domain\Contracts\RegistrationOfferingQueries;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Shared\Support\Transaction;

/** Writes the existing form/question schema atomically, with historical answers left intact. */
final readonly class SaveRegistrationFormAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
        private RegistrationOfferingQueries $offerings,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(string $organizationId, ?string $id, array $data, string $actorId, string $reason): string
    {
        return $this->transaction->run(function () use ($organizationId, $id, $data, $actorId, $reason): string {
            $form = $id === null ? new RegistrationForm : RegistrationForm::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($id);
            Gate::authorize($id === null ? 'create' : 'update', $id === null ? RegistrationForm::class : $form);
            $form->load('questions');
            $before = $id === null ? null : $this->snapshot($form);
            $programId = $data['preferred_program_id'] ?? null;
            $courseId = $data['preferred_course_id'] ?? null;
            $checkOffering = $id === null || (bool) $data['is_active']
                || $programId !== $form->preferred_program_id || $courseId !== $form->preferred_course_id;
            if (($programId === null) !== ($courseId === null)
                || ($programId !== null && $checkOffering && !$this->offerings->isAvailable($organizationId, (string) $programId, (string) $courseId))) {
                throw ValidationException::withMessages(['preferred_course_id' => __('students::validation.registration_offering_invalid')]);
            }
            $identity = Arr::only($data, ['slug', 'is_active', 'preferred_program_id', 'preferred_course_id']);
            $identity['title'] = array_replace($form->title ?? [], ['ar' => (string) $data['title']]);
            $identity['description'] = array_replace($form->description ?? [], ['ar' => $data['description'] ?? '']);
            $form->fill($identity);
            $form->organization_id = $organizationId;
            $form->save();

            $existing = $form->questions->keyBy('id');
            $kept = [];
            foreach ($data['questions'] ?? [] as $position => $row) {
                $questionId = $row['id'] ?? null;
                if ($questionId !== null && !$existing->has($questionId)) {
                    throw ValidationException::withMessages(['questions.'.$position.'.id' => __('console_registration.question_not_owned')]);
                }
                $question = $questionId === null ? new RegistrationQuestion : $existing->get($questionId);
                Gate::authorize($questionId === null ? 'create' : 'update', $questionId === null ? RegistrationQuestion::class : $question);
                $type = RegistrationQuestionType::from($row['type']);
                $question->fill([
                    'question' => array_replace($question->question ?? [], ['ar' => (string) $row['question']]),
                    'type' => $type,
                    'options' => $type->hasOptions() ? array_values($row['options']) : null,
                    'is_required' => (bool) $row['is_required'],
                    'is_active' => (bool) $row['is_active'],
                    'is_filterable' => $type->canBeFiltered() && (bool) $row['is_filterable'],
                    'sort_order' => $position,
                ]);
                $question->organization_id = $organizationId;
                $question->registration_form_id = (string) $form->id;
                $question->save();
                $kept[] = $question->id;
            }
            foreach ($existing as $question) {
                if (!in_array($question->id, $kept, true)) {
                    Gate::authorize('delete', $question);
                    $question->delete();
                }
            }
            $form->load('questions');
            $this->audit->record(
                organizationId: $organizationId, actorId: $actorId, actorType: 'user',
                action: $id === null ? 'students.registration_form_created' : 'students.registration_form_updated',
                auditableType: 'registration_form', auditableId: (string) $form->id,
                oldValues: $before, newValues: $this->snapshot($form), reason: $reason,
            );

            return (string) $form->id;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(RegistrationForm $form): array
    {
        return [
            ...$form->only(['slug', 'title', 'description', 'is_active', 'preferred_program_id', 'preferred_course_id']),
            'questions' => $form->questions->map->only(['id', 'question', 'type', 'options', 'is_required', 'is_active', 'is_filterable', 'sort_order'])->all(),
        ];
    }
}
