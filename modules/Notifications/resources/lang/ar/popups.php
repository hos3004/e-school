<?php

declare(strict_types=1);

return [

    'status' => [
        'draft' => 'مسودة',
        'published' => 'منشورة',
        'paused' => 'متوقفة مؤقتًا',
        'archived' => 'مؤرشفة',
    ],

    'effective_status' => [
        'scheduled' => 'مجدولة',
        'active' => 'نشطة الآن',
        'expired' => 'منتهية',
    ],

    'type' => [
        'urgent_announcement' => 'تنويه عاجل',
        'program_promotion' => 'إعلان برنامج أو كورس',
        'reminder' => 'تذكير',
        'administrative' => 'إشعار إداري',
        'general' => 'إعلان عام',
    ],

    'audience' => [
        'student' => 'الطلاب',
        'guardian' => 'أولياء الأمور',
        'teacher' => 'المعلمون',
        'supervisor' => 'المشرفون',
        'administrator' => 'الإداريون',
        'all_authenticated' => 'جميع المستخدمين المسجلين',
    ],

    'placement' => [
        'after_login' => 'بعد تسجيل الدخول',
        'dashboard' => 'لوحة المستخدم',
        'specific_page' => 'صفحة محددة',
        'all_authenticated_pages' => 'أول صفحة مؤهلة',
    ],

    'frequency' => [
        'once' => 'مرة واحدة',
        'once_per_login' => 'مرة بعد كل دخول',
        'once_per_day' => 'مرة يوميًا',
        'until_acknowledged' => 'حتى الإقرار',
        'every_eligible_visit' => 'كل زيارة (استخدام محدود)',
    ],

    'frequency_help' => [
        'once' => 'تظهر مرة واحدة فقط لكل مستخدم',
        'once_per_login' => 'تظهر مرة بعد كل تسجيل دخول',
        'once_per_day' => 'مرة واحدة يوميًا بتوقيت UTC',
        'until_acknowledged' => 'تستمر حتى يضغط المستخدم زر الإقرار',
        'every_eligible_visit' => 'في كل زيارة — استخدمها بحذر شديد',
    ],

    'pages' => [
        'student_dashboard' => 'لوحة الطالب',
        'student_schedule' => 'جدول الطالب',
        'guardian_dashboard' => 'لوحة ولي الأمر',
        'teacher_dashboard' => 'لوحة المعلم',
        'admin_dashboard' => 'لوحة الإدارة',
    ],

    'tabs' => [
        'content' => 'المحتوى',
        'audience' => 'الجمهور',
        'display' => 'الظهور',
        'scheduling' => 'الجدولة',
        'review' => 'المراجعة والمعاينة',
    ],

    'fields' => [
        'internal_name' => 'الاسم الداخلي للإدارة',
        'type' => 'نوع الرسالة',
        'title_ar' => 'العنوان (عربي)',
        'title_en' => 'العنوان (إنجليزي)',
        'title_fr' => 'العنوان (فرنسي)',
        'body_ar' => 'النص (عربي)',
        'body_en' => 'النص (إنجليزي)',
        'body_fr' => 'النص (فرنسي)',
        'arabic_content' => 'المحتوى العربي (إلزامي)',
        'optional_translations' => 'ترجمات اختيارية',
        'plain_text_help' => 'نص عادي فقط — لا HTML ولا أكواد. يُعرض مهرّبًا دائمًا.',
        'cta_section' => 'زر الإجراء (اختياري)',
        'action_type' => 'نوع الإجراء',
        'internal_page' => 'صفحة داخلية معتمدة',
        'external_url' => 'رابط خارجي (HTTPS فقط)',
        'external_url_help' => 'يُفتح في تبويب جديد بأمان. يُرفض أي رابط غير HTTPS.',
        'action_label_ar' => 'نص الزر (عربي)',
        'audiences' => 'الجمهور المستهدف',
        'audiences_help' => 'اختر جمهورًا واحدًا على الأقل. «الجميع» يشمل كل المستخدمين المصادقين.',
        'placement' => 'موضع الظهور',
        'page_key' => 'الصفحة المستهدفة',
        'frequency' => 'قاعدة التكرار',
        'is_dismissible' => 'يمكن للمستخدم إغلاقها',
        'requires_acknowledgement' => 'تتطلب إقرارًا صريحًا',
        'acknowledgement_label' => 'نص زر الإقرار',
        'priority' => 'الأولوية (الأعلى يظهر أولًا)',
        'starts_at' => 'بداية العرض (UTC)',
        'ends_at' => 'نهاية العرض (UTC) — اختيارية',
        'reason' => 'سبب التعديل',
        'reason_help' => 'سبب واضح يسجَّل في سجل التدقيق.',
    ],

    'options' => [
        'no_action' => 'بلا زر',
    ],

    'actions' => [
        'create' => 'حملة جديدة',
        'view' => 'عرض',
        'edit' => 'تعديل',
        'publish' => 'نشر',
        'pause' => 'إيقاف مؤقت',
        'resume' => 'استئناف',
        'duplicate' => 'نسخ كمسودة',
        'archive' => 'أرشفة',
    ],

    'confirm' => [
        'publish_description' => 'ستصبح الحملة مرئية للجمهور المحدد فور بدء نافذة العرض، وفق قاعدة التكرار والأولوية المحددة.',
        'archive_description' => 'الأرشفة نهائية: الحملة لن تظهر مجددًا ولا يمكن تعديلها. البديل الآمن للتعديل الجذري هو النسخ كمسودة.',
    ],

    'messages' => [
        'status_changed' => 'تم تحديث حالة الحملة.',
        'duplicated' => 'أُنشئت نسخة مسودة جديدة من الحملة.',
    ],

    'errors' => [
        'reason_required' => 'سبب التعديل مطلوب ويُسجَّل في سجل التدقيق.',
        'invalid_transition' => 'انتقال حالة غير مسموح للحملة.',
        'arabic_content_required' => 'العنوان والنص بالعربية إلزاميان قبل النشر.',
        'audience_required' => 'يجب اختيار جمهور واحد على الأقل.',
        'unsafe_exit' => 'لا يجوز أن تكون الحملة غير قابلة للإغلاق وغير قابلة للإقرار معًا — هذا يحبس المستخدم.',
        'invalid_page_key' => 'الصفحة المحددة غير موجودة في السجل المعتمد.',
        'invalid_window' => 'نهاية العرض يجب أن تكون بعد بدايته.',
        'locked_while_published' => 'لا يمكن تعديل محتوى حملة منشورة — أوقفها مؤقتًا أولًا أو انسخها كمسودة.',
        'not_available' => 'هذه الحملة غير متاحة الآن.',
        'not_dismissible' => 'هذه الحملة لا تقبل الإغلاق.',
        'no_acknowledgement' => 'هذه الحملة لا تتطلب إقرارًا.',
        'no_action' => 'هذه الحملة بلا زر إجراء.',
        'invalid_interaction' => 'تفاعل غير معروف.',
    ],

    'filters' => [
        'active_now' => 'نشطة الآن',
    ],

    'view' => [
        'overview' => 'نظرة عامة',
        'analytics' => 'الإحصاءات',
        'audit_note' => 'المنشأ والتدقيق',
        'created_by' => 'أنشأها',
        'updated_by' => 'آخر تعديل بواسطة',
        'published_by' => 'نشرها',
        'published_at' => 'وقت النشر',
        'created_at' => 'وقت الإنشاء',
        'updated_at' => 'وقت آخر تحديث',
    ],

    'analytics' => [
        'seen_users' => 'مستخدمون شاهدوا',
        'impressions' => 'مرات الظهور',
        'acknowledgements' => 'مرات الإقرار',
        'dismissals' => 'مرات الإغلاق',
        'clicks' => 'النقرات على الزر',
        'ctr' => 'نسبة النقر (CTR)',
    ],

    'preview' => [
        'action' => 'معاينة',
        'banner' => 'معاينة — هذه ليست نافذة حقيقية ولا تسجَّل لها إحصاءات',
        'no_tracking_note' => 'المعاينة لا تسجل مشاهدات أو نقرات أو إقرارات.',
        'unsafe_exit_warning' => 'تحذير: هذه الحملة ستُرفض عند النشر لأنها تحبس المستخدم (بلا إغلاق وبلا إقرار).',
    ],

    'frontend' => [
        'acknowledge_default' => 'فهمت',
        'dismiss' => 'إغلاق',
    ],

    'duplicate_suffix' => 'نسخة',
];
