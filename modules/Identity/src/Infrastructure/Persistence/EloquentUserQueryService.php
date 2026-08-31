<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence;

use Modules\Identity\Domain\Contracts\DTOs\UserSummary;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;

/**
 * تنفيذ Eloquent لعقد القراءة — يعيش داخل الموديول ولا يتسرب منه
 * أي model إلى الخارج.
 */
final readonly class EloquentUserQueryService implements UserQueryService
{
    public function modelType(): string
    {
        return (new User)->getMorphClass();
    }

    public function findSummary(string $userId): ?UserSummary
    {
        /** @var User|null $user */
        $user = User::query()->find($userId);

        return $user === null ? null : self::toDto($user);
    }

    public function summariesByIds(array $userIds): array
    {
        $users = User::query()->whereIn('id', $userIds)->get();

        return $users
            ->map(fn (User $user): UserSummary => self::toDto($user))
            ->keyBy(fn (UserSummary $dto): string => $dto->id)
            ->all();
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function searchUserIdsForOrganization(string $organizationId, string $term, int $limit = 100): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return User::query()
            ->forOrganization($organizationId)
            ->where(static function ($query) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $query->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('username', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like);
            })
            ->limit(max(1, $limit))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    private static function toDto(User $user): UserSummary
    {
        return new UserSummary(
            id: $user->id,
            organizationId: $user->organization_id,
            name: $user->name,
            email: $user->email,
            avatarPath: $user->avatar_path,
            status: $user->status->value,
        );
    }
}
