<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * سبب مكتوب إلزامي لكل إقفال أو إعادة فتح.
 *
 * الأرشيف بلا أسباب أرشيفُ أرقام: بعد سنة لن يقول أحد لماذا أُقفل البرنامج.
 * الصلاحية تُفحَص على السجل نفسه في الـcontroller عبر Policy لا هنا، لأن
 * القرار يتوقف على مؤسسة السجل لا على المستخدم وحده.
 */
final class ArchiveClosureRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
