<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries\DTOs;

/**
 * ملخّص مستخدم للعرض خارج الموديول — بلا أي بيانات حسّاسة.
 */
final readonly class UserSummary
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $name,
        public ?string $email,
        public ?string $avatarPath,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatarPath,
            'status' => $this->status,
        ];
    }
}
