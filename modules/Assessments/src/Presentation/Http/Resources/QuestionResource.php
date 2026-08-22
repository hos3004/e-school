<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessments\Domain\Models\Question;

/**
 * تمثيل السؤال في الـ API — الإجابة الصحيحة لا تُكشف للطلاب.
 *
 * @mixin Question
 */
final class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'auto_gradable' => $this->type->isAutoGradable(),
            ],
            'body' => $this->body,
            'options' => $this->options,
            // correct_answer يُخفى افتراضيًا ويُكشف فقط لمن يملك صلاحية التصحيح.
            'correct_answer' => $this->when(
                $request->user()?->can('assessments.attempt.grade'),
                $this->correct_answer,
            ),
            'score' => $this->score,
            'sort_order' => $this->sort_order,
        ];
    }
}
