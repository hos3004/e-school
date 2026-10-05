<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources\CertificateTemplateFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Certificates\Presentation\Filament\Resources\CertificateTemplateFilamentResource;

/**
 * صفحة فهرس CertificateTemplateFilamentResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListCertificateTemplates extends ListRecords
{
    protected static string $resource = CertificateTemplateFilamentResource::class;
}
