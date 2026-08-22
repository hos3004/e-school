<?php

declare(strict_types=1);

/*
| تسميات حالات موديول Integrations.
*/

return [

    'connection' => [
        'pending' => 'قيد الانتظار',
        'active' => 'نشط',
        'error' => 'خطأ',
        'disabled' => 'موقوف',
        'expired' => 'منتهي الصلاحية',
    ],

    'direction' => [
        'outbound' => 'صادر',
        'inbound' => 'وارد',
    ],

    'delivery' => [
        'pending' => 'في الطابور',
        'retrying' => 'إعادة محاولة',
        'delivered' => 'وصلت',
        'failed' => 'فاشلة',
        'dead' => 'ميتة',
    ],

];
