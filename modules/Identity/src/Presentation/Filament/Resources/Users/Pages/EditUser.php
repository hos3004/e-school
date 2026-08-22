<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
