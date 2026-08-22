# Assignments — الواجبات

## يملك

`assignments` · `assignment_submissions`

## ينشر

- `assignments.assigned`
- `assignments.submitted`
- `assignments.graded`

## يعتمد على

- `Academics` (الكورس) · `Groups` (الإسناد الجماعي) · `Staff` (المُسند) · `Students` (المُسلِّم).
- `Notifications` يستقبل أحداثه ويوزعها.

## قواعد خاصة

- تسليم واحد لكل طالب لكل واجب: `UNIQUE (assignment_id, student_profile_id)`.
- التأخير مقيد بـ `allows_late` + `late_penalty_percent` — لا خصم تلقائي خارج هذين الحقلين.
- حالة التسليم عبر Enum: `pending | submitted | late | graded`، والدرجة والتصحيح يُسجلان مع `graded_by` و`graded_at`.
