<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupMembershipResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Modules\Groups\Presentation\Filament\Resources\GroupMembershipResource;

final class ListGroupMemberships extends ListRecords
{
    protected static string $resource = GroupMembershipResource::class;

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
