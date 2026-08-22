<?php

declare(strict_types=1);

namespace Shared\Module;

use Illuminate\Support\Facades\Route;

/**
 * سجل الموديولات — نقطة الحقيقة الوحيدة لمعرفة أي موديول مفعّل وأين يعيش.
 *
 * ترتيب الموديولات في config/modules.php هو ترتيب التحميل، وهو يعكس
 * اتجاه الاعتماد الموصوف في docs/19-agent-dependency-graph.md
 */
final class ModuleRegistry
{
    /** @var list<string>|null */
    private static ?array $cache = null;

    /**
     * أسماء الموديولات المفعّلة بترتيب التحميل.
     *
     * @return list<string>
     */
    public static function enabled(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        /** @var array<string, bool> $modules */
        $modules = config('modules.enabled', []);

        return self::$cache = array_values(array_keys(array_filter($modules)));
    }

    public static function isEnabled(string $module): bool
    {
        return in_array($module, self::enabled(), true);
    }

    /**
     * المسار المطلق داخل الموديول.
     */
    public static function path(string $module, string $relative = ''): string
    {
        $base = base_path('modules/'.$module);

        return $relative === '' ? $base : $base.'/'.ltrim($relative, '/');
    }

    /**
     * الـ namespace الجذري للموديول.
     */
    public static function namespace(string $module): string
    {
        return 'Modules'.chr(92).$module;
    }

    /**
     * اسم صنف مزوّد الخدمة المتوقع للموديول.
     */
    public static function providerClass(string $module): string
    {
        $sep = chr(92);

        return 'Modules'.$sep.$module.$sep.'Infrastructure'.$sep.'Providers'.$sep.$module.'ServiceProvider';
    }

    /**
     * تحميل مسارات كل الموديولات المفعّلة.
     *
     * تُستدعى من bootstrap/app.php داخل withRouting(then:) بعد تسجيل
     * مسارات المنصة، حتى تتمكن الموديولات من الاعتماد على المجموعات الافتراضية.
     */
    public static function loadRoutes(): void
    {
        foreach (self::enabled() as $module) {
            $web = self::path($module, 'routes/web.php');
            $api = self::path($module, 'routes/api.php');

            if (is_file($web)) {
                Route::middleware('web')->group($web);
            }

            if (is_file($api)) {
                Route::middleware('api')->prefix('api')->group($api);
            }
        }
    }

    /**
     * مسارات هجرات كل الموديولات المفعّلة.
     *
     * @return list<string>
     */
    public static function migrationPaths(): array
    {
        $paths = [];

        foreach (self::enabled() as $module) {
            $path = self::path($module, 'database/migrations');

            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
