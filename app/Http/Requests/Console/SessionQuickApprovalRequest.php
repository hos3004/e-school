<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * اعتماد الحصة بضغطة واحدة — بلا سبب مكتوب.
 *
 * السبب إجباري في القرار التفصيلي لأن القرار هناك قد يكون أي شيء ولا يعرف
 * النظام أيّه اختير ولا لماذا. أما هنا فالقرار واحد ثابت («اعتماد») ولا يُقبل
 * إلا حين يكون دليله مرصودًا في البيانات، فيُولَّد السبب من الدليل نفسه في
 * الكنترولر ويُحفظ في سجل التدقيق كاملًا. الحقل الوحيد الباقي هو الحالة التي
 * رآها المستخدم، حتى لا يُبنى القرار على شاشة قديمة.
 */
final class SessionQuickApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('session.view') && $user->can('session.finalize');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_status' => ['required', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'expected_status' => __('console_session_review.fields.expected_status'),
        ];
    }
}
