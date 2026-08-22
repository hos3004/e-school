<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * طلب إضافة تعليق على منشور.
 */
final class StoreWallCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('messaging.class_wall_comment.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.wall_comment_body_max'),
            ],
        ];
    }

    public function wallPost(): ClassWallPost
    {
        /** @var ClassWallPost $post */
        $post = $this->route('post');

        return $post;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('messaging::validation.body_required'),
        ];
    }
}
