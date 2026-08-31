<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Models\User;

final readonly class EloquentUserAccountDirectory implements UserAccountDirectory
{
    public function search(string $organizationId, string $search, int $limit): array
    {
        $search = trim($search);
        $limit = max(1, min($limit, (int) config('identity.directory.max_results')));

        return User::query()
            ->forOrganization($organizationId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(static fn (User $user): UserAccountData => self::toDto($user))
            ->values()
            ->all();
    }

    public function find(string $organizationId, string $userId): ?UserAccountData
    {
        /** @var User|null $user */
        $user = User::query()
            ->forOrganization($organizationId)
            ->whereKey($userId)
            ->first();

        return $user === null ? null : self::toDto($user);
    }

    public function findMany(string $organizationId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->forOrganization($organizationId)
            ->whereKey($userIds)
            ->get()
            ->mapWithKeys(static fn (User $user): array => [
                (string) $user->getKey() => self::toDto($user),
            ])
            ->all();
    }

    private static function toDto(User $user): UserAccountData
    {
        return new UserAccountData(
            id: (string) $user->getKey(),
            organizationId: (string) $user->organization_id,
            name: (string) $user->name,
            email: $user->email,
            username: (string) $user->username,
            phone: $user->phone,
            status: $user->status->value,
        );
    }
}
