# مصفوفة صفحات وإجراءات UAT — 2026-09-01

هذه مصفوفة عمل لفرع `codex/client-uat-integration-20260901`. الجرد الساكن الحالي:
57 مورد Filament و3 صفحات Filament مخصصة، و8 صفحات طالب، و9 صفحات معلم، و5 صفحات ولي أمر، و4 صفحات مشتركة. الحالة `Working` أدناه تعني وجود route وتنفيذ خلفي واختبار آلي ناجح؛ ولا تعني قبولًا بصريًا شاملًا ما لم يُذكر اختبار المتصفح.

| الصفحة | الدور | الهدف العملي | الإجراء الأساسي | الإجراءات الثانوية | Route/API/Action | Permission/Policy | الحالة | الاختبار |
|---|---|---|---|---|---|---|---|---|
| لوحة الطالب | طالب | متابعة اليوم | فتح الحصة/الواجب | الجدول والتنبيهات | `student.dashboard` | بوابة الطالب + tenant | Working | Laravel + build |
| جدول الطالب | طالب | معرفة الحصص | فتح تفاصيل الحصة | الانضمام | `student.schedule` | ملكية الطالب | Working | Laravel + build |
| تفاصيل حصة الطالب | طالب | تنفيذ مهمة الحصة | طلب تأجيل | الانضمام/عرض الطلب | `student.sessions.show` + postponement API | `session.postpone.request` | Working | Scheduling 5/29 + build |
| واجبات الطالب | طالب | متابعة وتسليم الواجب | فتح الواجب | حالة التسليم | `student.assignments.index` | ملكية الطالب | Working | Laravel full path |
| تقارير الطالب | طالب | قراءة الأداء | عرض التقرير | العودة للوحة | `student.reports` | نطاق الطالب | Intentionally read-only | Laravel + build |
| برامج الطالب | طالب | قراءة البرامج | فتح البرنامج | — | `student.programs` | نطاق الطالب | Intentionally read-only | Laravel + build |
| مجموعة الطالب | طالب | متابعة المجموعة | عرض المجموعة | — | `student.group` | عضوية المجموعة | Intentionally read-only | Laravel + build |
| ملف الطالب | طالب | إدارة بياناته | حفظ الملف | — | `student.profile` | المستخدم نفسه | Working | Laravel + build |
| لوحة المعلم | معلم | متابعة الحصص والمهام | فتح الحصة التالية | التقارير الناقصة | `teacher.dashboard` | ملف المعلم | Working | Laravel + build |
| جدول المعلم | معلم | إدارة يوم التدريس | فتح تفاصيل الحصة | الانضمام | `teacher.schedule` | الحصص المسندة | Working | Laravel + build |
| تفاصيل حصة المعلم | معلم/بديل | إنهاء أعمال الحصة | إرسال تقرير الحصة | طلب تأجيل | `teacher.sessions.show` + session report/postponement APIs | `session_report.create`; `session.postpone.request` | Working | AcademicReports/Scheduling 13 اختبارًا |
| طلبات تأجيل المعلم | معلم | متابعة طلباته | إنشاء/عرض الطلب | الحالة | `teacher.postponements` | `session.postpone.request` | Working | Scheduling 5/29 + build |
| مجموعات المعلم | معلم | متابعة المجموعات | فتح المجموعة | — | `teacher.groups` | الإسناد الفعلي | Working | Laravel + build |
| طلاب المعلم | معلم | متابعة الطلاب | فتح الطالب المسموح | البحث | `teacher.students` | الإسناد + tenant | Working | Laravel + build |
| توافر المعلم | معلم | ضبط التوافر | حفظ التوافر | — | `teacher.availability` | ملف المعلم | Working | Laravel + build |
| مستحقات المعلم | معلم | قراءة المستحقات | عرض التفاصيل | — | `teacher.earnings` | دفتر الأستاذ + actor | Intentionally read-only | Laravel + build |
| ملف المعلم | معلم | إدارة بياناته | حفظ الملف | — | `teacher.profile` | المستخدم نفسه | Working | Laravel + build |
| لوحة ولي الأمر | ولي أمر | اختيار الابن ومتابعته | فتح ملف الابن | — | `guardian.dashboard` | ارتباط ولي الأمر | Working | Laravel + build |
| ملف الابن | ولي أمر | ملخص الابن | فتح الحضور/الجدول/التقارير | — | `guardian.child.show` | guardian link | Intentionally read-only | Laravel + build |
| حضور الابن | ولي أمر | قراءة الحضور | عرض السجل | — | `guardian.child.attendance` | guardian link | Intentionally read-only | Laravel + build |
| جدول الابن | ولي أمر | قراءة الجدول | فتح الحصة المسموحة | — | `guardian.child.schedule` | guardian link | Intentionally read-only | Laravel + build |
| تقارير الابن | ولي أمر | قراءة التقارير المتاحة | عرض التقرير | — | `guardian.child.reports` | guardian link | Intentionally read-only | Laravel + build |
| صندوق الرسائل | طالب/معلم | متابعة المحادثات | محادثة جديدة | فتح محادثة | `/messages`, `/messages/create` | ConversationPolicy + tenant | Working | Messaging 22/99 + TS/build |
| المحادثة | طالب/معلم | إرسال واستقبال الرسائل | إرسال رسالة | pagination | `/messages/{conversation}` | participant + tenant | Working | Messaging 22/99 + PHPStan |
| مركز الإشعارات | طالب/معلم | قراءة الإشعارات | تعليم كمقروء | فتح الهدف الآمن | `/notifications` | actor-scoped | Working | Notifications regression 23/122 |
| حملات النوافذ المنبثقة | إدارة | إنشاء حملة | إنشاء وحفظ | تعديل/حالة | `/admin/popup-campaigns/create` | PopupCampaignPolicy | Working | 18/62 + PHPStan |
| صندوق إرسال الإشعارات | مسؤول اتصال | إرسال إشعار داخلي | إرسال إشعار | معاينة/عدد المستلمين | Filament outbox + manual dispatch | `notifications.outbox.create` | Working | 5/37؛ regression 23/122 |
| التقارير الشهرية | مشرف أكاديمي | إنشاء سجل تقرير طالب | إنشاء تقرير شهري | عرض/اعتماد/إرسال | MonthlyReportResource + API | `monthly_report.create/approve`; `report.view` | Working | AcademicReports 26/109 ضمن 30/125 |
| التقارير التشغيلية | إدارة مخولة | تشغيل تقرير حسب الفلاتر | تشغيل التقرير | PDF بعد التشغيل | OperationalReports + export route | `report.view/export` | Working | Reporting 4/16 ضمن 30/125 |
| مراجعة طلبات التأجيل | إدارة مخولة | قبول/رفض الطلب | اعتماد أو رفض | السبب والتدقيق | PostponementRequestResource/actions | `session.postpone.approve` | Working | Scheduling 5/29 |
| بقية موارد Filament (54 موردًا) | إدارة حسب الصلاحية | CRUD/قراءة/اعتماد بحسب المورد | actions المعرفة في Resource | row/header actions | Filament resource routes | Policies + tenant | Backend incomplete | جرد ساكن فقط؛ يلزم crawler متصفح شامل |

## أرقام هذه الجولة

- أُغلقت 5 مجموعات عيوب معلنة: تقرير الحصة، التأجيل، Popup Campaign، صندوق الإرسال، وبدء التقارير.
- أضيفت/أعيد ربط 8 CTAs مركزية: إرسال تقرير الحصة، طلب التأجيل للطالب، طلب التأجيل للمعلم، مراجعة التأجيل، محادثة جديدة، إرسال إشعار، إنشاء تقرير شهري، وتشغيل تقرير تشغيلي.
- اختبارات التقارير الأخيرة: 30 ناجحة و125 توكيدًا. التحليل الساكن لكل ملفات التقارير المعدلة: صفر أخطاء.
- لم تكتمل جولة المتصفح لكل الموارد الإدارية أو seeds الأربعة أو Architecture في هذه المحاولة؛ لذلك الحكم الحالي `NO-GO FOR CLIENT UAT` حتى إتمام تلك البوابات.
