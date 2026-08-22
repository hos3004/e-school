<?php

declare(strict_types=1);

/*
| تسميات الحقول لموديول Notifications — تُستهلك في النماذج والجداول والطلبات.
*/

return [

    'id' => 'المعرّف',
    'organization_id' => 'المؤسسة',
    'user_id' => 'المستخدم',
    'category' => 'الفئة',
    'channel' => 'القناة',
    'locale' => 'لغة الرسالة',
    'event_name' => 'اسم الحدث',
    'event_id' => 'معرّف الحدث',
    'correlation_id' => 'معرّف الربط',
    'subject' => 'الموضوع',
    'body' => 'نص الرسالة',
    'payload' => 'البيانات الإضافية',
    'idempotency_key' => 'مفتاح عدم التكرار',
    'scheduled_for' => 'موعد الإرسال',
    'status' => 'الحالة',
    'attempts' => 'عدد المحاولات',
    'last_error' => 'آخر خطأ',
    'sent_at' => 'تاريخ الإرسال',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',
    'enabled' => 'مفعّل',
    'reason' => 'السبب',
    'attempt_number' => 'رقم المحاولة',
    'attempted_at' => 'تاريخ المحاولة',
    'provider_response' => 'رد المزوّد',
    'error' => 'الخطأ',
    'succeeded' => 'نجحت',

    // أقسام نموذج صندوق الإرسال
    'routing' => 'التوجيه',
    'content' => 'المحتوى',
    'dispatching' => 'التسليم',
];
