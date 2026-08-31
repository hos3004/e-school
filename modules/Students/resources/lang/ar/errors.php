<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Students.
| تُستهلك عبر __('students::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [

    'already_registered' => 'هذا المستخدم مسجَّل بالفعل كطالب.',
    'code_taken' => 'رقم الطالب «:student_code» مستخدم من قبل — اختر رقمًا آخر.',
    'archived_read_only' => 'لا يمكن تعديل ملف طالب مؤرشف؛ استرجعه أولًا.',
    'already_archived' => 'الطالب مؤرشف بالفعل.',
    'archive_reason_required' => 'سبب الأرشفة إلزامي وفق سياسة التدقيق.',
    'not_archived' => 'لا يمكن استرجاع طالب غير مؤرشف.',
    'not_found' => 'ملف الطالب المطلوب غير موجود.',
    'registration_invalid_transition' => 'لا يمكن نقل طلب التسجيل من «:from» إلى «:to».',
    'registration_form_unavailable' => 'نموذج التسجيل غير منشور أو لم يعد يقبل طلبات جديدة.',
    'registration_contact_required' => 'يجب إدخال البريد الإلكتروني أو رقم الهاتف.',
    'registration_required_field_missing' => 'الحقل «:field» مطلوب قبل تقديم الطلب.',
    'registration_duplicate_blocked' => 'يوجد طلب تسجيل سابق مطابق لهذه البيانات.',
    'registration_user_account_required' => 'يجب ربط الطلب بحساب مستخدم قبل القبول.',
    'registration_student_profile_exists' => 'يوجد بالفعل ملف طالب مرتبط بهذا الحساب.',
    'registration_rejection_reason_required' => 'سبب رفض طلب التسجيل إلزامي.',
    'registration_acceptance_reason_required' => 'سبب قبول طلب التسجيل إلزامي.',
    'direct_profile_creation_disabled' => 'لا يمكن إنشاء ملف طالب مباشرة؛ يجب قبول طلب التسجيل أولًا.',
    'registration_not_cleared_for_assignment' => 'طلب التسجيل غير مقبول للتسكين بعد.',
    'existing_account_not_found' => 'الحساب المحدد غير موجود داخل المؤسسة.',
    'organization_mismatch' => 'الملف المطلوب لا ينتمي إلى مؤسستك.',
    'update_reason_required' => 'تعديل بيانات ملف الطالب يتطلب سببًا مكتوبًا.',

    'bulk_placement_group_target_required' => 'يجب اختيار مجموعة موجودة أو إدخال اسم مجموعة جديدة.',
    'bulk_placement_no_eligible_students' => 'لا يوجد بين الطلاب المحددين من يصلح للتسكين في هذه المجموعة.',
];
