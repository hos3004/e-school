<?php

declare(strict_types=1);

return [
    'schedule.created' => [
        'subject' => 'تم اعتماد الجدول الدراسي',
        'body' => 'تم اعتماد جدول {{target_name}} في كورس {{course_name}} ({{course_code}}) مع المعلم {{teacher_name}}. مدة الحصة {{duration_minutes}} دقيقة، وعدد الحصص {{session_count}}. مواعيد الحصص: {{schedule_times}}',
        'parameters' => ['target_name', 'course_name', 'course_code', 'teacher_name', 'duration_minutes', 'session_count', 'schedule_times'],
    ],
    'registration.submitted' => [
        'subject' => 'تم استلام طلب التسجيل',
        'body' => 'تم استلام طلب التسجيل، وسيصلك إشعار عند اكتمال المراجعة.',
    ],
    'registration.approved' => [
        'subject' => 'تم اعتماد التسجيل',
        'body' => 'تم اعتماد طلب التسجيل بنجاح. يمكنك الآن متابعة خطوات البدء.',
    ],
    'registration.rejected' => [
        'subject' => 'تحديث طلب التسجيل',
        'body' => 'تعذّر اعتماد طلب التسجيل. راجع تفاصيل الطلب أو تواصل مع الإدارة.',
    ],
    'teacher.availability.approved' => [
        'subject' => 'تم اعتماد الإتاحة',
        'body' => 'اعتمدت الإدارة أوقات الإتاحة التي قدّمتها.',
    ],
    'student.assigned_to_teacher' => [
        'subject' => 'تم تعيين المعلم',
        'body' => 'تم ربط الطالب بالمعلم، وستظهر الحصص القادمة في الجدول.',
    ],
    'student.assigned_to_group' => [
        'subject' => 'تم الانضمام إلى المجموعة',
        'body' => 'تمت إضافة الطالب إلى المجموعة التعليمية بنجاح.',
    ],
    'session.scheduled' => [
        'subject' => 'تمت جدولة حصة',
        'body' => 'تمت جدولة الحصة لتبدأ في {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.rescheduled' => [
        'subject' => 'تغيّر موعد الحصة',
        'body' => 'تم تحديد الموعد البديل للحصة في {{makeup_start}}.',
        'parameters' => ['makeup_start'],
    ],
    'teacher.apology.submitted' => [
        'subject' => 'تم استلام اعتذار المعلم',
        'body' => 'تم تسجيل اعتذار المعلم واعتماده، وبدأ البحث عن بديل.',
    ],
    'student.apology.submitted' => [
        'subject' => 'تم تسجيل اعتذار الطالب',
        'body' => 'تم تسجيل اعتذار الطالب عن الحصة وإخطار الأطراف المعنية.',
    ],
    'postponement.requested' => [
        'subject' => 'طلب تأجيل حصة',
        'body' => 'تم تسجيل طلب تأجيل الحصة إلى {{proposed_start}} وإخطار الأطراف المعنية.',
        'parameters' => ['proposed_start'],
    ],
    'postponement.alternative_proposed' => [
        'subject' => 'اقترح المعلم موعدًا بديلًا',
        'body' => 'اقترح المعلم موعدًا بديلًا للحصة في {{teacher_proposed_start}}.',
        'parameters' => ['teacher_proposed_start'],
    ],
    'postponement.scheduled' => [
        'subject' => 'تم تأجيل الحصة',
        'body' => 'تم اعتماد الموعد البديل للحصة في {{agreed_start}}.',
        'parameters' => ['agreed_start'],
    ],
    'postponement.rejected' => [
        'subject' => 'رُفض طلب التأجيل',
        'body' => 'رُفض طلب تأجيل الحصة. راجع السبب المسجل في المنصة.',
    ],
    'teacher.apology.approved' => [
        'subject' => 'تم اعتماد الاعتذار',
        'body' => 'تم اعتماد اعتذار المعلم تلقائيًا وبدأ البحث عن بديل.',
    ],
    'teacher.apology.rejected' => [
        'subject' => 'لم يُعتمد الاعتذار',
        'body' => 'لم يعتمد المشرف طلب الاعتذار. راجع تفاصيل القرار في النظام.',
    ],
    'session.substitute.required' => [
        'subject' => 'الحصة تحتاج إلى معلم بديل',
        'body' => 'بدأ البحث عن معلم بديل للحصة، ويحتاج الطلب إلى متابعة المشرف.',
    ],
    'session.substitute.assigned' => [
        'subject' => 'تم تعيين معلم بديل',
        'body' => 'تم تعيين معلم بديل للحصة المقررة في {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.substitute.candidates_updated' => [
        'subject' => 'تحديث مرشحي المعلم البديل',
        'body' => 'وجد البحث التلقائي {{candidate_count}} مرشحًا متاحًا ومؤهلًا للحصة.',
        'parameters' => ['candidate_count'],
    ],
    'session.substitute.changed' => [
        'subject' => 'تغيّر المعلم البديل',
        'body' => 'تم تحديث المعلم البديل للحصة المقررة في {{scheduled_start}}.',
        'parameters' => ['scheduled_start'],
    ],
    'session.approaching' => [
        'subject' => 'موعد الحصة يقترب',
        'body' => 'تذكير: تبدأ حصة {{course_name}} في {{scheduled_start}}، ومدتها {{duration_minutes}} دقيقة.',
        'parameters' => ['course_name', 'scheduled_start', 'duration_minutes'],
    ],
    'classroom.guest_invited' => [
        'subject' => 'دعوة ضيف إلى الفصل',
        'body' => 'تم إنشاء دعوة آمنة ومحدودة لحضور الفصل.',
    ],
    'teacher.apology.second_warning' => [
        'subject' => 'تنبيه ثانٍ بشأن الاعتذارات',
        'body' => 'وصل سجل الاعتذارات إلى مستوى التنبيه الثاني خلال النافذة الحالية.',
    ],
    'teacher.apology.third_escalation' => [
        'subject' => 'تصعيد سجل الاعتذارات',
        'body' => 'وصل سجل الاعتذارات إلى مستوى التصعيد الثالث ويحتاج إلى مراجعة الإدارة.',
    ],
    'session.report.due' => [
        'subject' => 'اقترب موعد تقرير الحصة',
        'body' => 'يرجى إكمال تقرير الحصة قبل انتهاء المهلة المحددة.',
    ],
    'session.report.late' => [
        'subject' => 'تأخر تقرير الحصة',
        'body' => 'لم يُستكمل تقرير الحصة خلال المهلة، ويظهر الآن بصفته متأخرًا.',
    ],
    'discipline.action_applied' => [
        'subject' => 'تنبيه انضباطي',
        'body' => 'صدر إجراء انضباطي متعلق بقيدك. راجع التفاصيل من خلال حسابك أو تواصل مع الإدارة.',
    ],
    'discipline.student_frozen' => [
        'subject' => 'تم تجميد القيد',
        'body' => 'تم تجميد قيدك مؤقتًا، ولن تتمكن من الوصول إلى الكورسات حتى إعادة التفعيل. تواصل مع الإدارة لمعرفة التفاصيل.',
    ],
    'assignment.created' => [
        'subject' => 'واجب جديد',
        'body' => 'تم إسناد واجب جديد إليك. راجع تفاصيله وموعد التسليم من خلال حسابك.',
    ],
    'assignment.submitted' => [
        'subject' => 'تم تسليم واجب',
        'body' => 'سلّم أحد الطلاب واجبًا وهو جاهز الآن للتصحيح.',
    ],
    'submission.graded' => [
        'subject' => 'تم رصد درجة الواجب',
        'body' => 'تم رصد درجتك: {{score}} من {{max_score}}. اطّلع على الملاحظات من خلال حسابك.',
        'parameters' => ['score', 'max_score'],
    ],
    'schedule.change.requested' => [
        'subject' => 'طلب تغيير الموعد الدائم للحصص',
        'body' => 'طلب المعلم {{teacher_name}} تغيير موعد حصص {{course_name}} من {{current_schedule}} إلى {{proposed_schedule}}. لا يسري الموعد الجديد قبل قبول كل الطلاب، وتنتهي مهلة الرد في {{expires_at}}.',
        'parameters' => ['teacher_name', 'course_name', 'current_schedule', 'proposed_schedule', 'expires_at'],
    ],
    'schedule.change.applied' => [
        'subject' => 'اعتُمد الموعد الدائم الجديد للحصص',
        'body' => 'قبل كل الطلاب الموعد الجديد لحصص {{course_name}}: {{proposed_schedule}}. يسري على الحصص اعتبارًا من {{effective_from}}.',
        'parameters' => ['course_name', 'proposed_schedule', 'effective_from'],
    ],
    'schedule.change.rejected' => [
        'subject' => 'لم يُعتمد تغيير الموعد الدائم للحصص',
        'body' => 'انتهى طلب تغيير موعد حصص {{course_name}} إلى {{proposed_schedule}} دون اعتماد، والموعد الحالي مستمر كما هو.',
        'parameters' => ['course_name', 'proposed_schedule'],
    ],
    'session.join_link.teacher' => [
        'subject' => 'اقترب موعد حصتك — رابط الدخول',
        'body' => 'تبدأ حصة {{course_name}} بعد {{minutes_until_start}} دقيقة، في {{scheduled_start}}. افتح صفحة الحصة وسجّل دخولك منها ليحتسب النظام حضورك ويضيف مستحقات الحصة إلى رصيدك: {{join_url}}',
        'parameters' => ['course_name', 'minutes_until_start', 'scheduled_start', 'join_url'],
    ],
    'session.join_link.student' => [
        'subject' => 'اقترب موعد حصتك — رابط الدخول',
        'body' => 'تبدأ حصة {{course_name}} بعد {{minutes_until_start}} دقيقة، في {{scheduled_start}}. ادخل الفصل مباشرة من هذا الرابط: {{join_url}}',
        'parameters' => ['course_name', 'minutes_until_start', 'scheduled_start', 'join_url'],
    ],
    'discipline.absence_recorded' => [
        'subject' => 'تسجيل غياب عن الحصة',
        'body' => 'سُجِّل غياب {{student_name}} عن حصة {{course_name}} بتاريخ {{scheduled_start}}. بلغ عدد مرات الغياب {{absence_count}} خلال الفترة الحالية. رجاء العلم أن النظام مبرمج للقيام بتجميد القيد تلقائيًا وإتاحة المقعد لطالب آخر في حال بلوغ الغياب {{freeze_threshold}} مرات بدون عذر مسبق.',
        'parameters' => ['student_name', 'course_name', 'scheduled_start', 'absence_count', 'freeze_threshold'],
    ],
];
