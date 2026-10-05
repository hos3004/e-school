<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قرار اعتماد الحصة — القرار نفسه والسبب إجباريان.
 *
 * السبب ليس حقلًا شكليًا: القرار يفتح قيدة مستحقات لا تُعدَّل بعد إنشائها،
 * فيُحفظ في `audit_log` و`session_status_history` مبرِّرًا لمن يراجع لاحقًا.
 */
final class SessionReviewRequest extends FormRequest
{
    /** @var list<string> */
    public const DECISIONS = ['complete', 'no_show', 'excused', 'cancelled_by_school'];

    /**
     * الصلاحية المطلوبة تتبع القرار المختار لا القراءة وحدها.
     *
     * `Gate::authorize` في الكنترولر يفرض الصلاحية الصحيحة على كل حال، لكن
     * ترك العقد المعلن هنا عند `session.view` يجعل المسار يبدو أضعف مما هو،
     * فيسهل أن يُبنى عليه لاحقًا فرع لا يمر بالسياسة.
     */
    public const ABILITIES = [
        'complete' => 'session.finalize',
        'no_show' => 'attendance.record',
        'excused' => 'session.cancel',
        'cancelled_by_school' => 'session.cancel',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || !$user->can('session.view')) {
            return false;
        }

        $ability = self::ABILITIES[$this->input('decision')] ?? null;

        return $ability === null || $user->can($ability);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(self::DECISIONS)],
            'expected_status' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'decision' => __('console_session_review.fields.decision'),
            'expected_status' => __('console_session_review.fields.expected_status'),
            'reason' => __('console_session_review.fields.reason'),
        ];
    }
}
