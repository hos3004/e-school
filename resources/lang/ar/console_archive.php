<?php

declare(strict_types=1);

/*
| نصوص صفحة الأرشيف في اللوحة.
|
| الأرشيف هنا «أُقفل وخرج من الواجهة»، لا «حُذف». النصوص تحافظ على هذا الفرق
| لأن المستخدم يقرر بناءً عليها: من يظن أنه يحذف لن يضغط الزر أبدًا.
*/

return [
    'title' => 'الأرشيف',
    'description' => 'ما انتهى وخرج من الواجهة اليومية — بكامل بياناته وحصيلته. لا شيء هنا محذوف، وكل شيء يمكن إعادة فتحه.',

    'sections' => [
        'archived' => 'المؤرشَف',
        'candidates' => 'جاهز للأرشفة',
        'programs' => 'البرامج',
        'levels' => 'المستويات',
        'courses' => 'الكورسات',
        'groups' => 'المجموعات',
    ],

    'empty' => [
        'archived' => 'لا يوجد شيء مؤرشَف بعد.',
        'candidates' => 'لا يوجد ما يمكن أرشفته الآن.',
    ],

    'labels' => [
        'closed_at' => 'تاريخ الإقفال',
        'closed_by' => 'أقفله',
        'reason' => 'السبب',
        'summary' => 'بطاقة الحصيلة',
        'captured_at' => 'لقطة بتاريخ',
    ],

    'summary' => [
        'levels_total' => 'المستويات',
        'courses_total' => 'الكورسات',
        'courses_closed' => 'كورسات مؤرشَفة',
        'enrollments_total' => 'إجمالي القيود',
        'enrollments_completed' => 'قيود مكتملة',
        'enrollments_withdrawn' => 'قيود منسحبة',
        'students_distinct' => 'المستفيدون',
        'sessions_total' => 'إجمالي الحصص',
        'sessions_completed' => 'حصص منعقدة',
        'sessions_cancelled' => 'حصص ملغاة',
        'sessions_stale' => 'حصص مضى موعدها ولم تُغلق',
        'sessions_other' => 'حصص أخرى (غياب/عذر/تأجيل/مستبدَلة)',
        'courses_open' => 'كورسات لم تُؤرشَف',
        'teachers_distinct' => 'المعلمون',
        'first_session_at' => 'أول حصة',
        'last_session_at' => 'آخر حصة',
        'planned_sessions' => 'الحصص المخطَّطة',
        'members_total' => 'إجمالي الأعضاء',
        'teachers_total' => 'المعلمون المسنَدون',
        'programs_total' => 'البرامج المرتبطة',
        'capacity' => 'السعة',
        'status' => 'الحالة',
        'starts_on' => 'البداية',
        'ends_on' => 'النهاية',
    ],

    'blockers' => [
        'title' => 'لا يمكن الأرشفة الآن',
        'help' => 'الأرشفة لا تحذف شيئًا، لكنها تُخفي من الواجهة — فلا تُسمح ما دام تحته شغل قائم.',
        'courses_active' => 'كورسات نشطة: :count',
        'enrollments_live' => 'قيود لم تُغلق: :count',
        'sessions_open' => 'حصص لم تصل حالة نهائية: :count',
        'members_active' => 'طلاب ما زالوا منتسبين: :count',
    ],

    'actions' => [
        'preview' => 'معاينة الحصيلة',
        'close' => 'أرشِف',
        'reopen' => 'أعد الفتح',
        'cancel' => 'إلغاء',
        'loading' => 'جارٍ الحساب…',
    ],

    'dialog' => [
        'close_title' => 'أرشفة :name',
        'reopen_title' => 'إعادة فتح :name',
        'reason' => 'السبب',
        'reason_placeholder' => 'لماذا تُؤرشِف هذا الآن؟',
        'reason_help' => 'السبب يُحفظ مع الحصيلة في سجل التدقيق، ويبقى مقروءًا بعد إعادة الفتح.',
        'closable' => 'هذه الحصيلة ستُجمَّد كما هي عند الأرشفة.',
    ],

    'flash' => [
        'closed' => 'تمت الأرشفة. الحصيلة محفوظة، ويمكنك إعادة الفتح متى شئت.',
        'reopened' => 'أُعيد الفتح وعاد إلى الواجهة.',
    ],
];
