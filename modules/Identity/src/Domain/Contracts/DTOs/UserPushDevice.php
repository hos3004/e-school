<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts\DTOs;

final readonly class UserPushDevice
{
    public function __construct(
        public string $id,
        public string $token,
    ) {}
}
