<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Filament\Resources\AuditLogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Audit\Presentation\Filament\Resources\AuditLogResource;

/**
 * صفحة تصفّح قيود التدقيق — قراءة فقط بلا إنشاء.
 */
final class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public function getTitle(): string
    {
        return __('audit::labels.audit_log.plural');
    }
}
