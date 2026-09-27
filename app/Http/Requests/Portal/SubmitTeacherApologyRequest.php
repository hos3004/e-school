<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * لا وجود لصفحة ويب توازيها — Modules\Sessions\Application\Actions\
 * SubmitTeacherApologyAction كانت مبنية وغير مربوطة بأي واجهة قبل هذا
 * التغيير. نفس صلاحية شاشة الحصة نفسها (attendance.record).
 */
final class SubmitTeacherApologyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
