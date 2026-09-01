<?php

declare(strict_types=1);

/*
| تعريب أسماء الصلاحيات والأدوار والموديولات لعرضها في لوحة الأدمن.
|
| المفتاح الحقيقي (settings.manage) يبقى كما هو في قاعدة البيانات والكود —
| هذه طبقة عرض فقط. أي صلاحية جديدة بلا ترجمة تُعرض بمفتاحها الأصلي بدل أن
| تُخفى، حتى لا يختفي شيء عن الأدمن بسبب سطر ترجمة ناقص.
*/

return [

    /*
     * الموديولات — عمود «الموديول» في شاشة الصلاحيات.
     */
    'modules' => [
        'AcademicReports' => 'التقارير الأكاديمية',
        'Academics' => 'المناهج والبرامج',
        'AccessControl' => 'التحكم بالوصول',
        'Assessments' => 'الاختبارات',
        'Assignments' => 'الواجبات',
        'Attendance' => 'الحضور',
        'Audit' => 'سجل التدقيق',
        'Billing' => 'الفوترة',
        'Certificates' => 'الشهادات والأوسمة',
        'Content' => 'المحتوى التعليمي',
        'Discipline' => 'الانضباط',
        'Enrollments' => 'القيود الدراسية',
        'Groups' => 'المجموعات',
        'Guardians' => 'أولياء الأمور',
        'Identity' => 'الحسابات والهوية',
        'Integrations' => 'التكاملات',
        'Messaging' => 'المراسلات',
        'Notifications' => 'الإشعارات',
        'Organization' => 'المؤسسة والإعدادات',
        'Payroll' => 'مستحقات المعلمين',
        'Popups' => 'الحملات المنبثقة',
        'Recordings' => 'التسجيلات',
        'Reporting' => 'التقارير',
        'Scheduling' => 'الجدولة',
        'Sessions' => 'الحصص',
        'Staff' => 'الكادر والمعلمون',
        'Students' => 'الطلاب',
        'VirtualClassroom' => 'الفصل الافتراضي',
    ],

    /*
     * الأدوار — عمود «اسم الدور» في شاشة الأدوار.
     */
    'roles' => [
        'platform_admin' => 'مدير المنصة',
        'academic_supervisor' => 'المشرف الأكاديمي',
        'finance_supervisor' => 'مشرف الشؤون المالية',
        'communications_officer' => 'مسؤول التواصل',
        'registrar' => 'مسؤول التسجيل',
        'auditor' => 'المدقّق',
        'teacher' => 'معلم',
        'student' => 'طالب',
        'guardian' => 'ولي أمر',
    ],

    /*
     * الصلاحيات — عمود «اسم الصلاحية».
     */
    'names' => [
        'accesscontrol.assignments.assign_role' => 'إسناد دور لمستخدم',
        'accesscontrol.assignments.revoke_role' => 'سحب دور من مستخدم',
        'accesscontrol.permissions.grant_direct' => 'منح صلاحية مباشرة',
        'accesscontrol.permissions.view' => 'عرض صلاحية',
        'accesscontrol.permissions.view_any' => 'عرض قائمة الصلاحيات',
        'accesscontrol.roles.create' => 'إنشاء دور',
        'accesscontrol.roles.delete' => 'حذف دور',
        'accesscontrol.roles.sync_permissions' => 'تعديل صلاحيات دور',
        'accesscontrol.roles.update' => 'تعديل دور',
        'accesscontrol.roles.view' => 'عرض دور',
        'accesscontrol.roles.view_any' => 'عرض قائمة الأدوار',

        'admin.panel.access' => 'الدخول إلى لوحة الإدارة',
        'announcement.publish' => 'نشر إعلان',

        'assessment.manage' => 'إدارة الاختبارات',
        'assessment.take' => 'أداء اختبار',

        'assignment.grade' => 'تصحيح الواجبات',
        'assignment.manage' => 'إدارة الواجبات',
        'assignment.submit' => 'تسليم واجب',

        'attendance.override' => 'تعديل الحضور بعد اعتماده',
        'attendance.record' => 'رصد الحضور',
        'attendance.view' => 'عرض الحضور',

        'audit.view' => 'عرض سجل التدقيق',

        'badge.award' => 'منح وسام',
        'certificate.issue' => 'إصدار شهادة',

        'class_wall.post' => 'النشر على حائط الفصل',
        'classroom.guest.invite' => 'دعوة ضيف للفصل الافتراضي',
        'classroom.guest.revoke' => 'سحب دعوة ضيف',
        'classroom.moderate' => 'إدارة الفصل الافتراضي',
        'classroom.observe' => 'مراقبة الفصل الافتراضي',

        'content.manage' => 'إدارة المحتوى التعليمي',
        'content.view' => 'عرض المحتوى التعليمي',
        'course.manage' => 'إدارة المواد',

        'enrollment.create' => 'إنشاء قيد دراسي',
        'enrollment.freeze' => 'تجميد قيد دراسي',
        'enrollment.pause' => 'إيقاف قيد دراسي مؤقتًا',
        'enrollment.reactivate' => 'إعادة تفعيل قيد دراسي',
        'enrollment.view' => 'عرض القيود الدراسية',

        'grade.view' => 'عرض الدرجات',
        'group.manage' => 'إدارة المجموعات',
        'group.view' => 'عرض المجموعات',
        'guardian.link' => 'ربط ولي أمر بطالب',
        'guardian.view' => 'عرض أولياء الأمور',

        'identity.users.change_status' => 'تغيير حالة حساب',
        'identity.users.create' => 'إنشاء حساب مستخدم',
        'identity.users.delete' => 'حذف حساب مستخدم',
        'identity.users.update' => 'تعديل حساب مستخدم',
        'identity.users.view' => 'عرض حساب مستخدم',
        'identity.users.view_any' => 'عرض قائمة المستخدمين',
        'user.impersonate' => 'انتحال هوية مستخدم',

        'message.moderate' => 'مراجعة الرسائل',
        'message.send' => 'إرسال رسالة',
        'messaging.inbound.view' => 'عرض الرسائل الواردة',

        'notifications.outbox.create' => 'إرسال إشعار',
        'monthly_report.create' => 'إنشاء تقرير شهري',
        'monthly_report.approve' => 'اعتماد التقرير الشهري',

        'payroll.adjustment.approve' => 'اعتماد قيدة تسوية',
        'payroll.adjustment.propose' => 'اقتراح قيدة تسوية',
        'payroll.approve' => 'اعتماد المستحقات',
        'payroll.calculate' => 'احتساب المستحقات',
        'payroll.lock' => 'إقفال فترة المستحقات',
        'payroll.pay' => 'صرف المستحقات',
        'payroll.review' => 'مراجعة المستحقات',
        'payroll.view' => 'عرض المستحقات',

        'program.manage' => 'إدارة البرامج',

        'popup_campaign.archive' => 'أرشفة حملة منبثقة',
        'popup_campaign.create' => 'إنشاء حملة منبثقة',
        'popup_campaign.pause' => 'إيقاف حملة منبثقة مؤقتًا',
        'popup_campaign.publish' => 'نشر حملة منبثقة',
        'popup_campaign.update' => 'تعديل حملة منبثقة',
        'popup_campaign.view' => 'عرض حملة منبثقة',
        'popup_campaign.view_analytics' => 'عرض تحليلات الحملات المنبثقة',
        'popup_campaign.view_any' => 'عرض قائمة الحملات المنبثقة',

        'recording.delete' => 'حذف تسجيل',
        'recording.download' => 'تنزيل تسجيل',
        'recording.grant' => 'منح صلاحية مشاهدة تسجيل',
        'recording.view' => 'مشاهدة تسجيل',
        'recording.view.any' => 'مشاهدة كل التسجيلات',

        'report.export' => 'تصدير التقارير',
        'report.view' => 'عرض التقارير',

        'schedule.manage' => 'إدارة الجداول',
        'schedule.view' => 'عرض الجداول',

        'session.assign_substitute' => 'إسناد معلم بديل',
        'session.cancel' => 'إلغاء حصة',
        'session.create' => 'إنشاء حصة',
        'session.finalize' => 'اعتماد حصة',
        'session.join' => 'الانضمام إلى حصة',
        'session.postpone.approve' => 'اعتماد طلب تأجيل',
        'session.postpone.request' => 'طلب تأجيل حصة',
        'session.view' => 'عرض الحصص',
        'session_report.create' => 'كتابة تقرير حصة',
        'session_report.view' => 'عرض تقارير الحصص',

        'settings.manage' => 'إدارة الإعدادات',
        'system.alerts' => 'تنبيهات النظام',

        'staff.availability.approve' => 'اعتماد أوقات إتاحة المعلم',
        'staff.availability.create' => 'تسجيل أوقات إتاحة المعلم',
        'staff.contract.update' => 'تعديل عقد معلم',
        'staff.contract.view' => 'عرض عقد معلم',
        'staff.leave.approve' => 'اعتماد إجازة معلم',
        'staff.view' => 'عرض ملف معلم',
        'staff.view.any' => 'عرض قائمة المعلمين',

        'student.create' => 'إنشاء ملف طالب',
        'student.update' => 'تعديل ملف طالب',
        'student.view' => 'عرض ملف طالب',
        'student.view.any' => 'عرض قائمة الطلاب',
    ],
];
