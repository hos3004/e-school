<?php

declare(strict_types=1);
use Illuminate\Support\Env;

/*
| إعدادات موديول التكاملات — كل رقم سياسة يعيش هنا وليس في الكود.
*/

return [

    /*
    | أكبر عدد محاولات لإيصال Webhook قبل إعلانها ميتة (dead letter).
    */
    'webhooks' => [
        'max_attempts' => (int) Env::get('INTEGRATIONS_WEBHOOK_MAX_ATTEMPTS', 5),

        /*
        | مهلة الانتظار (بالدقائق) قبل إعادة المحاولة بعد فشل الإيصال.
        */
        'retry_backoff_minutes' => (int) Env::get('INTEGRATIONS_WEBHOOK_RETRY_BACKOFF_MINUTES', 15),
    ],

    'connections' => [
        /*
        | عدد الاتصالات المسموح به للمؤسسة على المزوّد الواحد.
        */
        'max_per_provider' => (int) Env::get('INTEGRATIONS_CONNECTIONS_MAX_PER_PROVIDER', 1),
    ],

];
