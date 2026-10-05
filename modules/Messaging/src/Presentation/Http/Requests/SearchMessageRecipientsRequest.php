<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchMessageRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('message.send') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // اختياري الآن: بلا نص، يعرض المتحكم قائمة المستلمين المتاحين
            // مباشرة (لمن يملك نطاقًا مقيَّدًا) بدل إجبار الكتابة أولًا.
            'q' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }
}
