<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب إنشاء محادثة.
 */
final class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('message.send') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxParticipants = (int) config('messaging.limits.max_participants');

        return [
            'type' => ['required', 'string', Rule::in(['direct', 'group', 'class'])],
            'subject' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.conversation_subject_max'),
            ],
            'participant_user_ids' => [
                'required',
                'array',
                'min:1',
                'max:'.max(1, $maxParticipants),
            ],
            'participant_user_ids.*' => ['required', 'string', 'size:26', 'distinct'],
            'is_moderated' => ['sometimes', 'boolean'],
            'related_type' => ['nullable', 'string', 'max:64'],
            'related_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('messaging::validation.type_required'),
            'subject.required' => __('messaging::validation.subject_required'),
            'participant_user_ids.required' => __('messaging::validation.participants_required'),
        ];
    }
}
