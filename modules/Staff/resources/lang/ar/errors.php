<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Staff.
| تُستهلك عبر __('staff::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'existing_account_not_found' => 'الحساب المحدد غير موجود داخل المؤسسة أو لم يعد متاحًا للربط.',
    'qualification_invalid_course' => 'أحد الكورسات المحددة غير نشط أو لا ينتمي إلى المؤسسة.',
    'amount_negative' => 'المبلغ لا يقبل قيمة سالبة.',
    'availability_approved_not_removable' => 'الإتاحة المعتمدة داخلة في الجدولة، ولا تُحذف إلا بقرار الإشراف.',
    'availability_decision_invalid' => 'قرار الإتاحة المحدد غير صالح.',
    'availability_invalid_approval_transition' => 'لا يمكن اتخاذ قرار جديد على الإتاحة من حالتها الحالية.',
    'availability_time_invalid' => 'وقت بداية الإتاحة يجب أن يسبق وقت نهايتها.',
    'availability_timezone_invalid' => 'المنطقة الزمنية المختارة غير معروفة.',
    'availability_weekday_invalid' => 'يوم الأسبوع يجب أن يكون بين الأحد والسبت.',
    'contract_base_not_allowed' => 'أساس الاحتساب المختار لا يناسب نوع هذا العقد.',
    'contract_base_required' => 'أساس الاحتساب مطلوب لهذا النوع من العقود.',
    'contract_overlaps' => 'يوجد عقد ساري يتقاطع مع هذه الفترة.',
    'contract_period_invalid' => 'تاريخ نهاية الفترة يجب ألا يسبق تاريخ بدايتها.',
    'leave_overlaps_approved' => 'توجد إجازة معتمدة تتقاطع مع هذه الفترة.',
    'leave_period_invalid' => 'تاريخ نهاية الإجازة يجب ألا يسبق تاريخ بدايتها.',
    'leave_transition_forbidden' => 'لا يمكن نقل الإجازة إلى هذه الحالة من حالتها الحالية.',
    'profile_already_exists' => 'لهذا الحساب ملف موظف قائم بالفعل.',
    'profile_already_terminated' => 'خدمة هذا الموظف منتهية بالفعل.',
    'rate_overlaps' => 'يوجد سعر ساري يتقاطع مع هذه الفترة لنفس النطاق.',
    'rate_scope_course_required' => 'تحديد الدورة مطلوب عندما يكون نطاق السعر دورة.',
    'rate_scope_program_required' => 'تحديد البرنامج مطلوب عندما يكون نطاق السعر برنامجًا.',
    'update_reason_required' => 'تعديل بيانات ملف المعلم يتطلب سببًا مكتوبًا.',
    'organization_mismatch' => 'الملف المطلوب لا ينتمي إلى مؤسستك.',
    'archived_read_only' => 'لا يمكن تعديل ملف معلم مؤرشف؛ استرجعه أولًا.',
    'revocation_reason_required' => 'إلغاء الاعتماد يتطلب سببًا مكتوبًا.',
    'qualification_already_revoked' => 'اعتماد هذا الكورس ملغى بالفعل.',
    'qualification_active_assignment' => 'لا يمكن إلغاء الاعتماد بينما يوجد إسناد نشط على الكورس؛ أنّه الإسناد أولًا.',
    'availability_overlaps' => 'توجد فترة إتاحة تتقاطع مع هذه الفترة في نفس اليوم.',
];
