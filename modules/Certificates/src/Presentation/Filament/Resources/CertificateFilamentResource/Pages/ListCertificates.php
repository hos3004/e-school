<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources\CertificateFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Certificates\Presentation\Filament\Resources\CertificateFilamentResource;

/**
 * صفحة فهرس CertificateFilamentResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateFilamentResource::class;
}
