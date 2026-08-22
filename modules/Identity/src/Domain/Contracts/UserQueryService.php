<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Modules\Identity\Application\Queries\DTOs\UserSummary;

/**
 * عقد القراءة العام لموديول Identity — الباب الوحيد الذي تستخدمه
 * الموديولات الأخرى لمعرفة بيانات مستخدم. يُرجع DTOs وليس Eloquent models.
 */
interface UserQueryService
{
    public function findSummary(string $userId): ?UserSummary;

    /**
     * @param  list<string>  $userIds
     * @return array<string, UserSummary> مفتوحة بمعرّف المستخدم
     */
    public function summariesByIds(array $userIds): array;

    public function emailExists(string $email): bool;
}
