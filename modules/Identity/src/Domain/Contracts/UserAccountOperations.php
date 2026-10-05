<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

/**
 * عمليات كتابة الحساب المعلنة للموديولات وطبقة التركيب.
 *
 * كل عملية تتحقق من انتماء الحساب للمؤسسة قبل التنفيذ، وتسجل
 * أثرًا تدقيقيًا عند اللزوم. البريد وكلمة المرور خارج هذا العقد —
 * لكلٍّ مساره المستقل (verification / reset).
 */
interface UserAccountOperations
{
    /**
     * تحديث الحقول الآمنة: name, phone, phone_country, locale, timezone.
     *
     * @param array<string, mixed> $fields
     */
    public function updateProfile(
        string $organizationId,
        string $userId,
        array $fields,
        string $actorId,
        string $reason,
    ): void;

    /**
     * تغيير حالة الحساب عبر آلة الحالات الرسمية مع سبب مكتوب.
     */
    public function changeStatus(
        string $organizationId,
        string $userId,
        string $status,
        string $actorId,
        string $reason,
    ): void;

    /**
     * اعتماد صورة الحساب (Avatar) المرفوعة مسبقًا على القرص المؤقت.
     *
     * تتحقق من أنّ الملف صورة حقيقية (لا مجرد امتداد)، تنقلها إلى مسارها
     * النهائي باسم عشوائي، تحدِّث users.avatar_path، ثم تنظّف الصورة القديمة
     * بعد نجاح العملية فقط. تمرير null يعني إزالة الصورة.
     */
    public function setAvatar(
        string $organizationId,
        string $userId,
        ?string $storedPath,
        string $actorId,
        string $reason,
    ): void;
}
