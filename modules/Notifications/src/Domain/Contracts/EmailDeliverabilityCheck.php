<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

/**
 * فحص صلاحية تسليم بريد إلكتروني للاستهلاك خارج موديول Notifications —
 * بلا استيراد `UndeliverableEmailDomains` (Application\Services) مباشرة من
 * موديول آخر. أي شاشة اختيار مستلم بريد خارج هذا الموديول تسأل هذا العقد
 * قبل قبول العنوان، بدل تكرار قائمة النطاقات المحجوزة.
 */
interface EmailDeliverabilityCheck
{
    /** صيغة صحيحة ونطاق غير محجوز (RFC 2606/6761) وغير فارغ. */
    public function isDeliverable(?string $email): bool;
}
