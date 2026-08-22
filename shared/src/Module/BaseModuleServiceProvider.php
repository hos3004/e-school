<?php

declare(strict_types=1);

namespace Shared\Module;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * الأساس الذي يرث منه مزوّد خدمة كل موديول.
 *
 * الموديول يعلن ثلاثة أشياء فقط:
 *  - listeners : ربط Domain Events بمستمعيها (داخل هذا الموديول فقط)
 *  - policies  : ربط الموارد بسياسات الصلاحيات
 *  - bindings  : ربط الـ Contracts بتنفيذاتها
 */
abstract class BaseModuleServiceProvider extends ServiceProvider
{
    /**
     * اسم الموديول كما في config/modules.php
     */
    abstract protected function moduleName(): string;

    /**
     * ربط Domain Event بمستمعيه.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * ربط الموارد بسياساتها.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * ربط الـ Contracts بتنفيذاتها.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [];
    }

    public function register(): void
    {
        foreach ($this->bindings() as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        $config = ModuleRegistry::path($this->moduleName(), 'config');

        if (is_dir($config)) {
            foreach (glob($config.'/*.php') ?: [] as $file) {
                $this->mergeConfigFrom($file, basename($file, '.php'));
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->listeners() as $event => $handlers) {
            foreach ($handlers as $handler) {
                Event::listen($event, $handler);
            }
        }

        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
