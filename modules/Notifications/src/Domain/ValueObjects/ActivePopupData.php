<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * النافذة المنبثقة الوحيدة المؤهلة — DTO للعرض فقط، لا Eloquent.
 * كل الروابط جاهزة وآمنة، والمحتوى نص عادي (لا HTML).
 */
final readonly class ActivePopupData
{
    /**
     * @param array<string, string> $title
     * @param array<string, string> $body
     * @param list<string> $matchedAudiences
     */
    public function __construct(
        public string $campaignId,
        public string $type,
        public string $typeIcon,
        public string $typeColor,
        public array $title,
        public array $body,
        public ?string $acknowledgementLabel,
        public ?string $actionLabel,
        public ?string $actionUrl,
        public bool $actionIsExternal,
        public bool $isDismissible,
        public bool $requiresAcknowledgement,
        public array $matchedAudiences,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
    ) {}
}
