<?php

declare(strict_types=1);

/**
 * أكواد العرض القصيرة لكل كيان في النظام.
 *
 * القاعدة: كل كود ظاهر للمستخدم = حرف واحد + ثلاثة أرقام = أربع خانات بالضبط.
 * المعرّف الحقيقي للسجل يبقى ULID في عمود `id`؛ هذا الكود للعرض والبحث فقط،
 * ولا يُستخدم أبدًا كمفتاح ربط بين الجداول.
 *
 * لماذا هنا: القاعدة رقم ٣ في CLAUDE.md — أي رقم يخص سياسة المدرسة يعيش في
 * config وليس داخل الكود. تغيير حرف أو عرض الترقيم يتم من هنا فقط.
 */
return [

    /*
     * عدد خانات الجزء الرقمي. ثلاث خانات = ٩٩٩ سجلًا لكل كيان.
     * عند تجاوز السقف يتوسّع المولّد تلقائيًا (E1000) بدل أن يفشل أو يكرّر كودًا.
     */
    'digits' => 3,

    /*
     * الحرف المميّز لكل كيان. كل مفتاح هنا يقابل جدولًا وعمودًا في `entities`.
     */
    'prefixes' => [
        'student' => 'E',
        'staff' => 'T',
        'group' => 'G',
        'course' => 'C',
        'program' => 'P',
        'level' => 'L',
        'badge' => 'B',
    ],

    /*
     * أين يعيش كود كل كيان. يستخدمها المولّد لحساب الرقم التالي وضمان التفرّد،
     * وتستخدمها هجرة إعادة الترقيم.
     *
     * scope يجب أن يطابق قيد التفرّد الفعلي في قاعدة البيانات، وإلا وُلِّد كود
     * مكرّر ورُفض الإدراج. `null` = العمود فريد على مستوى الجدول كله.
     */
    'entities' => [
        'student' => ['table' => 'student_profiles', 'column' => 'student_code', 'scope' => null],
        'staff' => ['table' => 'staff_profiles', 'column' => 'staff_code', 'scope' => null],
        'group' => ['table' => 'groups', 'column' => 'code', 'scope' => null],
        'course' => ['table' => 'courses', 'column' => 'code', 'scope' => null],
        'program' => ['table' => 'programs', 'column' => 'code', 'scope' => null],
        'level' => ['table' => 'levels', 'column' => 'code', 'scope' => 'program_id'],
        'badge' => ['table' => 'badges', 'column' => 'code', 'scope' => null],
    ],
];
