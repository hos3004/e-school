<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب المعلم موعدًا أسبوعيًا جديدًا لقالب جدوله.
 *
 * السبب إلزامي لأن العملية حساسة وتدخل سجل التدقيق. صحة الخانات مقابل قواعد
 * المدرسة (المدة والمنطقة الزمنية والإتاحة) تبقى مسؤولية طبقة الموديول.
 */
final class RequestScheduleChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('schedule.change.request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slots' => ['required', 'array', 'min:1', 'max:7'],
            'slots.*.weekday' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time' => ['required', 'string', 'date_format:H:i'],
            'interval_weeks' => ['nullable', 'integer', 'between:1,12'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
