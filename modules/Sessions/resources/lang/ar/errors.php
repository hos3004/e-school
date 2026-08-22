<?php

declare(strict_types=1);

return [
    'substitute_same_teacher' => 'المعلم البديل هو نفسه المعلم المسنَد للحصة.',
    'substitute_reason_required' => 'سبب الاستبدال مطلوب.',
    'substitute_not_qualified' => 'هذا المعلم غير مؤهل لتدريس هذه المادة. يلزم تجاوز إداري بسبب مكتوب.',
    'substitute_not_available' => 'هذا المعلم غير متاح في هذا الموعد: :conflicts حصة متعارضة. يلزم تجاوز إداري بسبب مكتوب.',
    'override_reason_required' => 'التجاوز الإداري يتطلب كتابة سببه.',
    'substitute_not_allowed_in_status' => 'لا يمكن إسناد بديل لحصة حالتها :status.',
    'attendance_incomplete' => 'لا يمكن إقفال الحصة قبل اعتماد حضور كل المشاركين.',
    'invalid_transition' => 'انتقال غير مسموح من :from إلى :to.',
    'apology_reason_required' => 'سبب الاعتذار مطلوب.',
    'apology_not_assigned_teacher' => 'لا يمكن الاعتذار عن حصة غير مسنَدة إليك.',
    'apology_already_pending' => 'لديك اعتذار معلَّق عن هذه الحصة بالفعل.',
    'apology_session_closed' => 'لا يمكن الاعتذار عن حصة حالتها :status.',
    'apology_rejection_reason_required' => 'رفض الاعتذار يتطلب كتابة سببه.',
    'apology_invalid_transition' => 'انتقال غير مسموح للاعتذار من :from إلى :to.',
    'apology_must_not_change_session' => 'خطأ داخلي: اعتماد الاعتذار غيّر حالة الحصة. الاعتذار لا يُلغي الحصة أبدًا.',
];
