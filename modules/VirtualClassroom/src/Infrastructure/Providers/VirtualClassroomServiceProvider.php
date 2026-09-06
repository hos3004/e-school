<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\VirtualClassroom\Application\Console\ManageClassroomWebhook;
use Modules\VirtualClassroom\Application\Console\ProvisionUpcomingClassrooms;
use Modules\VirtualClassroom\Application\Console\SmokeTestClassroom;
use Modules\VirtualClassroom\Application\Queries\ClassroomAdministrationQueryService;
use Modules\VirtualClassroom\Application\Queries\ClassroomPresenceQueryService;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomPresenceQueries;
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

    /** @return array<class-string, class-string> */
    protected function bindings(): array
    {
        return [
            ClassroomAdministrationQueries::class => ClassroomAdministrationQueryService::class,
            ClassroomPresenceQueries::class => ClassroomPresenceQueryService::class,
        ];
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('classroom-webhook', static function (Request $request): Limit {
            return Limit::perMinute((int) config('virtual-classroom.webhook.rate_limit_per_minute'))
                ->by('classroom-webhook:'.$request->ip());
        });

        $this->commands([
            ManageClassroomWebhook::class,
            ProvisionUpcomingClassrooms::class,
            SmokeTestClassroom::class,
        ]);
    }
}
