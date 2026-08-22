<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

use Shared\Support\BusinessRuleViolation;

/**
 * منفذ محرّك الإشعارات — ما تستخدمه الموديولات الأخرى (عبر Event أو مباشرة).
 *
 * التنفيذ يكتب في صندوق الصادر فقط ولا يرسل بنفسه؛ الإرسال مهمة الخلفية
 * SendQueuedNotification التي تلتقط السطور المستحقة.
 */
interface NotificationDispatcher
{
    /**
     * قيد إشعارًا لعدة مستلمين عبر القنوات المحسوبة لكل مستلم.
     *
     * @param string $category فئة الإشعار كما في config('notifications.categories')
     * @param list<string> $recipientIds معرّفات المستلمين — لا نماذج من موديولات أخرى
     * @param array<string, mixed> $payload محتوى الرسالة: subject · body · متغيرات العرض
     * @param string|null $correlationId معرّف الربط للتتبع عبر الموديولات
     * @return int عدد السطور المكتوبة فعليًا في الصندوق الصادر (queued + suppressed)
     *
     * @throws BusinessRuleViolation إذا كانت الفئة غير معروفة
     */
    public function dispatch(
        string $category,
        array $recipientIds,
        array $payload,
        ?string $correlationId = null,
    ): int;
}
