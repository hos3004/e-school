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
     * @param list<array{text: string, url: string}> $links
     * @param list<array{
     *     id: string,
     *     kind: string,
     *     url: string,
     *     mime_type: string,
     *     has_sound: bool|null,
     *     download_request_url: string|null
     * }> $media
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
        public string $displayMode = 'bottom_banner',
        public ?int $autoDismissSeconds = null,
        public array $links = [],
        public array $media = [],
    ) {}
}
