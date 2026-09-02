<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources\OrganizationSnapshotResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Reporting\Presentation\Filament\Resources\OrganizationSnapshotResource;

/**
 * صفحة فهرس OrganizationSnapshotResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListOrganizationSnapshots extends ListRecords
{
    protected static string $resource = OrganizationSnapshotResource::class;
}
