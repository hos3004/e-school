<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportBot;

use Illuminate\Foundation\Http\FormRequest;

/**
 * التحقق من رسالة المستخدم قبل أي عمل.
 *
 * الحدّ الأقصى هنا نسخة من إعداد البوت عمدًا: رسالة ضخمة يجب أن تُرفض عند حافة
 * الطلب قبل أن تُفتح جلسة أو يُستدعى مزوّد، لا بعد أن تكون قد كلّفت شيئًا.
 */
final class AskSupportBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الوصول الحقيقي يقرره AccessResolver: المفتاح العام والفئة واستثناء
        // الحساب. هنا نكتفي بوجود مستخدم موثَّق داخل مؤسسة.
        return $this->user() !== null
            && (string) data_get($this->user(), 'organization_id') !== '';
    }

    /**
     * @return array<string, list<string|int>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:'.$this->maxCharacters()],
        ];
    }

    public function message(): string
    {
        return trim((string) $this->string('message'));
    }

    private function maxCharacters(): int
    {
        $configured = (int) config('support_bot.conversation.max_message_characters', 2000);

        return $configured > 0 ? $configured : 2000;
    }
}
