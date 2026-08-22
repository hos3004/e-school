<?php

declare(strict_types=1);

namespace Modules\Recordings\Infrastructure\Providers;

use Modules\Recordings\Application\Policies\RecordingPolicy;
use Modules\Recordings\Application\Policies\RecordingViewPolicy;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class RecordingsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Recordings';
    }

    /**
     * ربط الموارد بسياساتها.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Recording::class => RecordingPolicy::class,
            RecordingView::class => RecordingViewPolicy::class,
        ];
    }

    /**
     * ربط الـ Contracts بتنفيذاتها.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            Transaction::class => DatabaseTransaction::class,
        ];
    }

    /**
     * لا مستمعين بعد — بقية الموديولات قد تستمع لأحداثنا لاحقًا،
     * ونحن الآن لا نستهلك أحداثًا خارجية.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }
}
