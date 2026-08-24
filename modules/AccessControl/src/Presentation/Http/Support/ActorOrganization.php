<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Support;

use Illuminate\Http\Request;

final class ActorOrganization
{
    public static function from(Request $request): string
    {
        $organizationId = $request->user()?->getAttribute('organization_id');

        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }
}
