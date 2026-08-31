<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

interface UsernameSuggestionGateway
{
    /** @return list<string> */
    public function suggest(string $fullName, ?string $organizationId = null): array;
}
