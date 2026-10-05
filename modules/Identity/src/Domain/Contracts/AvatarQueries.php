<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Modules\Identity\Domain\Contracts\DTOs\AvatarPresentation;

/**
 * عقد قراءة صورة المستخدم — الباب الوحيد لعرض Avatar خارج Identity.
 * يستقبل المسار الخام وجنس صاحب الملف (من ملفه الشخصي في الموديول
 * المالك) ويعيد رابط الصورة النهائية أو الافتراضية حسب الجنس.
 */
interface AvatarQueries
{
    /**
     * @param string|null $avatarPath القيمة الخام من users.avatar_path
     * @param string|null $gender male|female|أي قيمة أخرى تعني محايدًا
     */
    public function resolve(?string $avatarPath, ?string $gender): AvatarPresentation;

    /** رابط الصورة الافتراضية حسب الجنس مباشرة. */
    public function defaultUrl(?string $gender): string;
}
