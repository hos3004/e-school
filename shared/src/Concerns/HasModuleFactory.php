<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * يربط النموذج بمصنعه داخل الموديول.
 *
 * الاستدعاء الافتراضي في Laravel يبحث عن Database\Factories\<Model>Factory
 * بجوار namespace التطبيق، وهو ما لا يصلح هنا لأن كل موديول له namespace
 * مستقل. هذا التريت يستنبط المسار من namespace النموذج نفسه:
 *
 *   Modules\Students\Domain\Models\StudentProfile
 *        -> Modules\Students\Database\Factories\StudentProfileFactory
 */
trait HasModuleFactory
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /** @return Factory<self>|null */
    protected static function newFactory(): ?Factory
    {
        $separator = chr(92);
        $parts = explode($separator, static::class);

        // Modules \ <Name> \ Domain \ Models \ <Model>
        if (count($parts) < 5 || $parts[0] !== 'Modules') {
            return null;
        }

        $module = $parts[1];
        $model = end($parts);

        $factory = implode($separator, [
            'Modules', $module, 'Database', 'Factories', $model.'Factory',
        ]);

        if (!is_subclass_of($factory, Factory::class)) {
            return null;
        }

        /** @var class-string<Factory<self>> $factory */
        return $factory::new();
    }
}
