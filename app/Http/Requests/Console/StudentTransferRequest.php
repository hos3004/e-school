<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

/**
 * نقل طالب من انتسابه الحالي إلى مجموعة أخرى. صلاحية الانتساب المصدر
 * (تابع لهذا الطالب وما زال قائمًا) تُفحص في الـcontroller عبر خدمة القراءة.
 */
final class StudentTransferRequest extends StudentPlacementRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'membership_id' => ['required', 'ulid']];
    }
}
