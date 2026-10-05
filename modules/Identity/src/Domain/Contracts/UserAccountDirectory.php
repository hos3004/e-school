<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;

/**
 * دليل حسابات آمن للاستخدام الإداري عبر حدود الموديولات.
 *
 * يعيد DTOs فقط، ويُلزم كل قراءة بمعرّف المؤسسة حتى لا يصبح اختيار
 * «حساب موجود» بابًا لتسريب حساب من مؤسسة أخرى.
 */
interface UserAccountDirectory
{
    /**
     * @return list<UserAccountData>
     */
    public function search(string $organizationId, string $search, int $limit): array;

    public function find(string $organizationId, string $userId): ?UserAccountData;

    /**
     * @param list<string> $userIds
     * @return array<string, UserAccountData>
     */
    public function findMany(string $organizationId, array $userIds): array;
}
