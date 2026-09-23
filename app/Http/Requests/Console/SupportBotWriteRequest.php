<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * كل كتابة على إعدادات البوت تحمل سببًا مكتوبًا.
 *
 * الفرض عند حافة الطلب لا داخل الإجراء، كما تنص قواعد المشروع: السبب جزء من
 * صحّة الطلب لا خطوة اختيارية بعده. وهو ما يجعل سجل التدقيق مفيدًا بعد شهور —
 * «من غيّر هذا ولماذا» لا «تغيّر».
 */
final class SupportBotWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الصلاحية الدقيقة تُفحَص في المتحكّم عبر Gate حسب العملية.
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string|int>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->string('reason'));
    }
}
