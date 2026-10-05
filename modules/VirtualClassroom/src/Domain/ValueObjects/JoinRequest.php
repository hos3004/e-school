<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * طلب رابط دخول شخصي لمشارك بعينه.
 *
 * كلمة سر الدور تأتي من سجل الفصل المحلي (moderator_secret / attendee_secret)
 * ولا يعرف المزوّد عنها شيئًا حتى هذه اللحظة.
 *
 * returnUrl هو المكان الذي يعيد إليه المزوّد متصفح هذا المشارك بعد خروجه أو
 * انتهاء الحصة. يأتي جاهزًا من طبقة البوابة لأن الموديول لا يعرف مسارات
 * الواجهات؛ تركه null يترك سلوك المزوّد الافتراضي كما هو.
 */
final readonly class JoinRequest
{
    public function __construct(
        public string $externalId,
        public string $displayName,
        public JoinRole $role,
        public string $rolePassword,
        public ?string $externalUserId = null,
        public ?string $returnUrl = null,
    ) {}
}
