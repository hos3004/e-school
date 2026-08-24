<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Contracts;

/**
 * Read-only port used by Messaging to authorize people that belong to other
 * modules. Implementations return primitive decisions and never expose models.
 */
interface ClassAudienceQueries
{
    /** @param list<string> $userIds */
    public function usersBelongToOrganization(string $organizationId, array $userIds): bool;

    public function isGuardian(string $organizationId, string $userId): bool;

    /** @param list<string> $participantUserIds */
    public function isStudentTeacherConversation(string $organizationId, array $participantUserIds): bool;

    /**
     * Active group students and currently assigned teachers return true.
     * Frozen/withdrawn students and ended teacher assignments return false.
     */
    public function canAccessClass(string $organizationId, string $groupId, string $userId): bool;
}
