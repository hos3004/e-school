<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Services\Console\SessionDecisionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قرار على حصة من داخل ملف حسابات المعلم.
 *
 * نفس عقد شاشة الاعتماد: قرار مقفل على قائمة، وحالة متوقَّعة تمنع البناء على
 * شاشة قديمة، وسبب مكتوب إجباري لأن القرار يفتح قيدة لا تُعدَّل بعد إنشائها.
 * الزيادة الوحيدة هنا `amount`: أجر يدوي لهذه الحصة وحدها حين يختلف عمّا
 * يحسبه محلّل الأسعار — اتفاق سابق أو ظرف خاص. تركه فارغًا يعني «احسبه أنت».
 */
final class TeacherDuesSessionRequest extends FormRequest
{
    /** الصلاحية المطلوبة تتبع القرار المختار لا القراءة وحدها. */
    public const ABILITIES = [
        'complete' => 'session.finalize',
        'no_show' => 'attendance.record',
        'excused' => 'session.cancel',
        'cancelled_by_school' => 'session.cancel',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || !$user->can('session.view') || !$user->can('payroll.view')) {
            return false;
        }

        $ability = self::ABILITIES[$this->input('decision')] ?? null;

        return $ability === null || $user->can($ability);
    }

    protected function prepareForValidation(): void
    {
        $category = $this->input('reason_category');
        $note = $this->input('note');
        $this->merge(['reason' => is_string($category)
            ? trim((string) __('console_dues.session_reasons.'.$category).(is_string($note) && trim($note) !== '' ? ': '.trim($note) : ''))
            : '']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(SessionDecisionService::DECISIONS)],
            'expected_status' => ['required', 'string', 'max:40'],
            'reason_category' => ['required', Rule::in(['off_platform', 'on_platform', 'not_held', 'student_absent', 'other'])],
            'note' => [Rule::requiredIf($this->input('reason_category') === 'other'), 'nullable', 'string', 'max:1500'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            /*
             * الأجر اليدوي لا معنى له مع قرار غير الاعتماد. رفضه صراحةً أوضح
             * من إسقاطه بصمت: الفرق بين «لم يُقبل مبلغك» و«قُبل ولم يُستعمل»
             * فرق مالي يجب ألا يُكتشف لاحقًا من الدفتر.
             */
            'amount' => ['prohibited_unless:decision,complete', 'nullable', 'string',
                'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'decision' => __('console_dues.session_decision'),
            'expected_status' => __('console_dues.session_expected_status'),
            'reason_category' => __('console_dues.decision_category'),
            'note' => __('console_dues.note'),
            'amount' => __('console_dues.session_amount'),
        ];
    }
}
