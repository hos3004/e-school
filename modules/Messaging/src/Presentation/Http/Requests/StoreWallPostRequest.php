<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب نشر منشور على حائط الصف.
 */
final class StoreWallPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('class_wall.post') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'string', 'size:26'],
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.wall_post_body_max'),
            ],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['array'],
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('messaging::validation.body_required'),
            'group_id.required' => __('messaging::validation.group_required'),
        ];
    }
}
