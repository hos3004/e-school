<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Providers;

use Modules\Audit\Application\Policies\AuditLogPolicy;
use Modules\Audit\Application\Services\AuditRecordingService;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Infrastructure\Persistence\AuditLogQueryService;
use Shared\Module\BaseModuleServiceProvider;

final class AuditServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Audit';
    }

    /**
     * لا مستمعين داخل الموديول: Audit ينشر أحداثه فقط، ويستمع
     * لأحداث غيره عبر طبقة الـ Actions عند الكتابة.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            AuditLog::class => AuditLogPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            AuditRecorder::class => AuditRecordingService::class,
            AuditQueryService::class => AuditLogQueryService::class,
        ];
    }
}
