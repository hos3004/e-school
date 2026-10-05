<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\ValueObjects\ActivePopupData;

/**
 * قرار أهلية الظهور — Server-Side بالكامل.
 *
 * يستقبل سياق المستخدم عبر قيم بسيطة (لا User model عابرًا)، ويعيد
 * نافذة واحدة مؤهلة كحد أقصى (الأعلى أولوية) أو null.
 */
interface PopupQueries
{
    /**
     * @param list<string> $userAudiences جمهور المستخدم كما حلّه AudienceResolver القانوني
     * @param string|null $loginMarker علامة جلسة الدخول الحالية (لـOncePerLogin)
     */
    public function activeForUser(
        string $organizationId,
        string $userId,
        array $userAudiences,
        string $placement,
        ?string $pageKey,
        ?string $loginMarker,
        CarbonImmutable $now,
    ): ?ActivePopupData;
}
