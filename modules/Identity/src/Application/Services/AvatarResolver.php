<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\DTOs\AvatarPresentation;

/**
 * مُحلِّل صورة المستخدم الموحّد.
 *
 * 1) إذا وُجد avatar_path وكان الملف صالحًا على القرص ← رابط الصورة المرفوعة.
 * 2) إن لا ← صورة افتراضية محلية حسب الجنس (ذكر/أنثى/محايد).
 *
 * الجنس يصل مُمرَّرًا من طبقة التركيب أو من ملف الطالب/الموظف نفسه —
 * لا استعلامات عابرة للموديولات ولا استيراد Eloquent من Students أو Staff.
 */
final readonly class AvatarResolver implements AvatarQueries
{
    public function resolve(?string $avatarPath, ?string $gender): AvatarPresentation
    {
        if (is_string($avatarPath) && $avatarPath !== '') {
            /** @var Filesystem $disk */
            $disk = Storage::disk((string) config('avatars.disk'));

            if ($disk->exists($avatarPath)) {
                return new AvatarPresentation(
                    url: $disk->url($avatarPath),
                    isDefault: false,
                );
            }
        }

        return new AvatarPresentation(
            url: $this->defaultUrl($gender),
            isDefault: true,
        );
    }

    public function defaultUrl(?string $gender): string
    {
        $key = match ($gender) {
            'male' => 'male',
            'female' => 'female',
            default => 'neutral',
        };

        return (string) asset((string) config('avatars.defaults.'.$key));
    }
}
