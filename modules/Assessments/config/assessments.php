<?php

declare(strict_types=1);

/*
| إعدادات موديول Assessments — كل أرقام السياسة تعيش هنا لا في الكود.
*/

return [

    /*
    | نافذة التسليم المتأخر: دقائق السماح بعد انتهاء مدة الاختبار (أو نافذة
    | التوفر) يُقبل خلالها تسليم المحاولة قبل رفضها نهائيًا.
    */
    'submission' => [
        'grace_minutes' => env('ASSESSMENTS_SUBMISSION_GRACE_MINUTES', 5),
    ],

];
