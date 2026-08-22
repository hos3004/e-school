<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Modules\Assessments\Domain\Models\Question;

/**
 * سياسة الأسئلة — الملكية تُستنبط من اختبار السؤال (نفس المؤسسة).
 */
final class QuestionPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('assessments.question.view_any');
    }

    public function view($user, Question $question): bool
    {
        return $user->can('assessments.question.view')
            && $question->assessment?->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('assessments.question.create');
    }

    public function update($user, Question $question): bool
    {
        return $user->can('assessments.question.update')
            && $question->assessment?->organization_id === $user->organization_id;
    }

    public function delete($user, Question $question): bool
    {
        return $user->can('assessments.question.delete')
            && $question->assessment?->organization_id === $user->organization_id;
    }
}
