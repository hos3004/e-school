<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;

final class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;
}
