<?php

declare(strict_types=1);

namespace Shared\Module;

use Illuminate\Support\ServiceProvider;

/**
 * يشغّل نظام الموديولات: يسجّل مزوّد الخدمة الخاص بكل موديول مفعّل،
 * ويحمّل هجراته وترجماته وإعداداته دون أن يحتاج الموديول لأي تسجيل يدوي.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/modules.php'), 'modules');

        foreach (ModuleRegistry::enabled() as $module) {
            $provider = ModuleRegistry::providerClass($module);

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(ModuleRegistry::migrationPaths());

        foreach (ModuleRegistry::enabled() as $module) {
            $lang = ModuleRegistry::path($module, 'resources/lang');

            if (is_dir($lang)) {
                $this->loadTranslationsFrom($lang, strtolower($module));
            }

            $views = ModuleRegistry::path($module, 'resources/views');

            if (is_dir($views)) {
                $this->loadViewsFrom($views, strtolower($module));
            }
        }
    }
}
