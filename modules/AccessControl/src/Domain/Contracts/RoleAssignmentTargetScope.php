<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Contracts;

/**
 * يتحقق من أن هدف الإسناد ينتمي لمؤسسة الفاعل ويعيد اسم morph الآمن.
 * التنفيذ العابر للموديولات يعيش في composition root، لا داخل AccessControl.
 */
interface RoleAssignmentTargetScope
{
    public function modelTypeFor(string $organizationId, string $targetId): string;
}
