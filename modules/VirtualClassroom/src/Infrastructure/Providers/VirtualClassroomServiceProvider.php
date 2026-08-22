<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Infrastructure\Providers;

use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Shared\Module\BaseModuleServiceProvider;

final class VirtualClassroomServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'VirtualClassroom';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(VirtualClassroomProvider::class, function (): VirtualClassroomProvider {
            $provider = (string) config('virtual-classroom.default');
            $configuration = config('virtual-classroom.providers.'.$provider);

            if (!is_array($configuration)) {
                throw ClassroomProviderException::configuration(['provider' => $provider]);
            }

            $driver = $configuration['driver'] ?? null;

            if (!is_string($driver) || !is_a($driver, VirtualClassroomProvider::class, true)) {
                throw ClassroomProviderException::configuration(['provider' => $provider]);
            }

            if ($driver === BigBlueButtonProvider::class) {
                return new BigBlueButtonProvider($configuration);
            }

            /** @var VirtualClassroomProvider $instance */
            $instance = $this->app->make($driver);

            return $instance;
        });
    }
}
