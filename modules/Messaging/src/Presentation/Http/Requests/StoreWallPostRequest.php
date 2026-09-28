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
        $maxKilobytes = (int) config('messaging.wall.attachments.max_size_kilobytes');
        /** @var list<string> $allowedMimes */
        $allowedMimes = config('messaging.wall.attachments.allowed_mime_types', []);

        return [
            'group_id' => ['required', 'string', 'size:26'],
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.wall_post_body_max'),
            ],
            // ممنوع صراحة: attachments حرّ الشكل بلا مُنتِج شرعي عدا الرفع
            // الفعلي عبر image أدناه. السماح بقيمة عميل هنا كان يفتح منفذ
            // حقن disk/path تعسفيَّين يخدمهما ShowWallAttachmentController.
            'attachments' => ['prohibited'],
            'image' => [
                'sometimes',
                'file',
                'max:'.$maxKilobytes,
                'mimetypes:'.implode(',', $allowedMimes),
            ],
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
