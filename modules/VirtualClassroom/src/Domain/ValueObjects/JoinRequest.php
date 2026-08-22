<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * طلب رابط دخول شخصي لمشارك بعينه.
 *
 * كلمة سر الدور تأتي من سجل الفصل المحلي (moderator_secret / attendee_secret)
 * ولا يعرف المزوّد عنها شيئًا حتى هذه اللحظة.
 */
final readonly class JoinRequest
{
    public function __construct(
        public string $externalId,
        public string $displayName,
        public JoinRole $role,
        public string $rolePassword,
        public ?string $externalUserId = null,
    ) {}
}
