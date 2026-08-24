# AcademicReports — تقارير الحصة والتقارير الشهرية

## يملك

`session_reports` · `session_report_students` · `monthly_reports`

## ينشر

- `academic_reports.session_report_submitted`
- `academic_reports.monthly_drafted`
- `academic_reports.monthly_approved`

## يعتمد على

- `Sessions` (تقرير لكل حصة) · `Staff` (كاتب التقرير) · `Students` · `Enrollments` (التقرير الشهري).
- يستقبل `sessions.finalized` ليطالب المعلم بالتقرير.

## قواعد خاصة

- **مهلة تقرير الحصة من `config/scheduling.php`** (القيمة الحالية 60 دقيقة) من نهاية
  الحصة؛ بعدها يصبح `late` ويُنبّه المعلم ويُصعَّد للمشرف وفق `docs/13 §9`.
- تقرير شهري واحد لكل طالب: `UNIQUE (student_profile_id, period_year, period_month)`.
- `supervisor_private_note` لا يُعرض أبدًا للطالب أو ولي الأمر.
- حالات التقرير الشهري: `draft | approved | sent` — الإرسال بعد الاعتماد فقط.
