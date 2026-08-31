<?php

declare(strict_types=1);

/**
 * القبول والتسجيل — قواعد تحويل زائر إلى طالب نشط.
 *
 * مرجع القرار: docs/client-answers.md §CLIENT UPDATE — 2026-08-22 (§أ · §ب · §ج).
 *
 * القاعدة الحاكمة: Sign Up ليس قبولًا. الحساب والطلب والملف والقيد وعضوية
 * المجموعة خمسة كيانات منفصلة، والتوزيع ممنوع قبل اعتماد الطلب.
 *
 * ممنوع منعًا باتًا كتابة اسم دولة أو محافظة أو بادئة مؤسسة داخل كود أي
 * موديول. الدول والمناطق بيانات مرجعية في قاعدة البيانات، والبادئة من
 * إعدادات المؤسسة.
 */
return [

    /*
     * التسجيل الذاتي من الموقع.
     *
     * الطلاب يصلون للمؤسسة من محركات البحث ويسجّلون بأنفسهم، لذلك المسار
     * العام مفتوح — لكنه ينتهي عند «طلب مقدَّم» لا عند «طالب نشط».
     */
    'self_registration' => [
        'enabled' => env('ADMISSION_SELF_REGISTRATION', true),

        // الطلب يبدأ مسودة ويُقدَّم صراحةً؛ المسودة لا تصل للإدارة.
        'starts_as_draft' => true,

        // الحقول التي لا يُقبل الطلب بدونها.
        'required_fields' => [
            'full_name',
            'date_of_birth',
            'gender',
            'country_id',
            'region_id',
            'contact',           // بريد أو هاتف — أحدهما على الأقل
        ],

        // أقل عمر يُقبل تسجيله ذاتيًا؛ ما دونه يحتاج ولي أمر.
        'min_self_registration_age' => (int) env('ADMISSION_MIN_SELF_AGE', 13),

        'rate_limit_per_minute' => (int) env('ADMISSION_REGISTRATION_RATE_LIMIT', 6),

        // يُستخدم فقط لمسار التسجيل القديم بلا slug؛ روابط الحملات تحمل slug النموذج.
        'default_form_slug' => env('ADMISSION_DEFAULT_REGISTRATION_FORM_SLUG'),

        // منع الطلبات المكررة لنفس الشخص.
        'duplicate_detection' => [
            'enabled' => true,
            'match_on' => ['email', 'phone'],
            'block_or_flag' => 'flag',   // flag = يُعرض للإدارة، block = يُرفض
        ],
    ],

    /*
     * دورة حياة طلب التسجيل.
     *
     * الانتقالات المسموحة معرَّفة في RegistrationStatus enum، وهذه القائمة
     * مرجع توثيقي لا مصدر منطق.
     */
    'application' => [
        'statuses' => [
            'draft',
            'submitted',
            'under_review',
            'accepted',
            'rejected',
            'waiting_assignment',
            'assigned',
        ],

        // الرفض بلا سبب مكتوب مرفوض على مستوى الـFormRequest.
        'rejection_requires_reason' => true,
        'acceptance_requires_reason' => true,

        // مهلة مراجعة الطلب قبل تنبيه الإدارة (بالساعات) — 0 يعني بلا تنبيه.
        'review_sla_hours' => (int) env('ADMISSION_REVIEW_SLA_HOURS', 48),

        // إنشاء ملف الطالب لا يحدث إلا عند القبول.
        'creates_student_profile_on' => 'accepted',

        // التوزيع على برنامج/مجموعة ممنوع قبل هذه الحالة.
        'assignment_allowed_from' => ['waiting_assignment', 'assigned'],
    ],

    /*
     * أهلية الالتحاق بالبرنامج.
     *
     * المؤسسة لا تقبل كل المتقدمين في البرامج المجانية. الشروط تُخزَّن على
     * البرنامج نفسه (program_eligibility)، وهذه الإعدادات تحكم كيف تُقيَّم.
     */
    'eligibility' => [
        // البرامج المجانية: مراجعة يدوية هي الافتراضي حاليًا.
        'free_program_default_manual_approval' => true,

        // البرامج المدفوعة: التسجيل التلقائي ممكن مستقبلًا — Payment خارج المرحلة.
        'paid_program_allows_auto_enroll' => false,

        /*
         * الشروط المدعومة. إضافة شرط جديد = سطر هنا + عمود/صف في
         * program_eligibility + فرع في المقيِّم. لا Rules Engine عام.
         */
        'supported_rules' => [
            'countries',       // قائمة رموز دول مسموحة — فارغ = بلا قيد
            'regions',         // قائمة معرّفات مناطق مسموحة — فارغ = بلا قيد
            'age_from',
            'age_to',
            'gender',          // male | female | all
            'manual_approval_required',
        ],

        /*
         * ماذا يحدث عند عدم استيفاء شرط.
         *
         * block = لا يُوزَّع الطالب على البرنامج إطلاقًا.
         * warn  = يُعرض تحذير للإدارة ويُسمح بالتجاوز بصلاحية وسبب مكتوب.
         */
        'on_violation' => [
            'countries' => 'block',
            'regions' => 'block',
            'age' => 'warn',
            'gender' => 'block',
        ],

        // تجاوز شرط الأهلية يحتاج صلاحية وسببًا مكتوبًا ويدخل سجل التدقيق.
        'override_permission' => 'enrollment.eligibility.override',
        'override_requires_reason' => true,
    ],

    /*
     * المطابقة بين الطالب والمعلم.
     *
     * هذه قواعد عامة يستطيع أي برنامج تفعيلها — ممنوع ربطها باسم برنامج
     * بعينه في الكود (لا شرط باسم «Quran» ولا غيره).
     */
    'matching' => [
        // يُقرأ من البرنامج: هل يشترط تطابق جنس الطالب والمعلم؟
        'gender_rule_values' => ['same', 'any'],
        'default_gender_rule' => 'any',

        // مقارنة إتاحة الطالب بإتاحة المعلم.
        'requires_availability_overlap' => true,

        // أقل تداخل مقبول بين الإتاحتين (بالدقائق) ليُعتبر الموعد صالحًا.
        'min_overlap_minutes' => (int) env('MATCHING_MIN_OVERLAP_MINUTES', 30),

        // الترتيب الآلي المتقدم للمعلمين — المرحلة 1.5.
        'automatic_ranking_enabled' => false,
    ],

    /*
     * اسم المستخدم.
     *
     * البادئة تأتي من organization_settings مفتاح `username_prefix`.
     * القيمة هنا احتياطية فقط لبيئة بلا إعدادات مؤسسة.
     */
    'username' => [
        'required_for_login' => true,
        'fallback_prefix' => env('ADMISSION_USERNAME_FALLBACK_PREFIX', 'student'),
        'organization_setting_key' => 'username_prefix',

        'separator' => '.',
        'min_length' => 4,
        'max_length' => 32,

        // عدد الاقتراحات المعروضة عند التسجيل أو عند إنشاء الإدارة للحساب.
        'suggestions_count' => 3,

        /*
         * أنماط التوليد — تُجرَّب بالترتيب حتى يُوجد اسم متاح.
         * {prefix} من الإعدادات · {first} الاسم الأول · {last} اسم العائلة
         * {initial} أول حرف · {n} رقم متزايد
         */
        'patterns' => [
            '{prefix}{sep}{first}',
            '{prefix}{sep}{first}{n}',
            '{prefix}{sep}{initial}{sep}{last}',
            '{prefix}{sep}{first}{sep}{last}',
        ],

        // الإدارة تستطيع تعديل الاسم المقترح قبل الحفظ.
        'admin_can_edit' => true,

        // أسماء محجوزة لا تُقترح ولا تُقبل.
        'reserved' => ['admin', 'root', 'support', 'system', 'test'],
    ],

    /*
     * ربط الحساب واستعادته.
     */
    'account' => [
        'student_role' => env('ADMISSION_STUDENT_ROLE', 'student'),

        // أحدهما على الأقل مطلوب للاستعادة.
        'allow_email_link' => true,
        'allow_phone_link' => true,
        'require_at_least_one_contact' => true,

        // الاستعادة تتم عبر القناة المتاحة فعلًا على الحساب.
        'recovery_channels' => ['email', 'whatsapp'],
        'recovery_token_ttl_minutes' => 60,
        'generated_password_length' => (int) env('ADMISSION_GENERATED_PASSWORD_LENGTH', 24),
    ],
];
