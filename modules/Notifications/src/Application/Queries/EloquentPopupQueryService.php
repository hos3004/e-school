<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignUserState;
use Modules\Notifications\Domain\ValueObjects\ActivePopupData;

/**
 * محدِّد النافذة المؤهلة الوحيدة — قرار Server-Side بالكامل.
 *
 * الاستعلام يستفيد من الفهرس المركب (organization_id, status, placement,
 * starts_at, priority)، والمرشّحون محدودون بعدد أقصى من config، ثم تُقيَّم
 * قواعد الجمهور والتكرار بالترتيب الثابت: الأولوية ← البداية ← المعرّف.
 */
final readonly class EloquentPopupQueryService implements PopupQueries
{
    private const LOCALE_FALLBACK = 'ar';

    public function activeForUser(
        string $organizationId,
        string $userId,
        array $userAudiences,
        string $placement,
        ?string $pageKey,
        ?string $loginMarker,
        CarbonImmutable $now,
    ): ?ActivePopupData {
        /** @var Collection<int, PopupCampaign> $candidates */
        $candidates = PopupCampaign::query()
            ->forOrganization($organizationId)
            ->where('status', PopupCampaignStatus::Published->value)
            ->where('placement', $placement)
            ->where('starts_at', '<=', $now)
            ->where(static function ($query) use ($now): void {
                // نهاية مفتوحة أو لم تنتهِ بعد.
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->orderByDesc('priority')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit((int) config('popups.max_candidates_per_request'))
            ->get();

        // حالة المستخدم لكل المرشحين في استعلام واحد — لا N+1.
        $states = PopupCampaignUserState::query()
            ->where('user_id', $userId)
            ->whereIn('campaign_id', $candidates->pluck('id'))
            ->get()
            ->keyBy(static fn (PopupCampaignUserState $state): string => (string) $state->campaign_id);

        foreach ($candidates as $campaign) {
            if (!self::matchesAudience($campaign->audiences ?? [], $userAudiences)) {
                continue;
            }

            if (!$campaign->placement->matches($pageKey, $campaign->page_key)) {
                continue;
            }

            /** @var PopupCampaignUserState|null $state */
            $state = $states[(string) $campaign->getKey()] ?? null;

            // الإغلاق المتعمد يسدد الحملة القابلة للإغلاق نهائيًا.
            if ($state !== null && $state->dismissed_at !== null && $campaign->is_dismissible) {
                continue;
            }

            if (!$campaign->frequency->allowsShow($state, $loginMarker, $now)) {
                continue;
            }

            return self::toDto($campaign, $userAudiences);
        }

        return null;
    }

    /**
     * مطابقة الجمهور: «الجميع» يطابق أي مستخدم مصادق، وإلا تقاطع القيم.
     *
     * @param list<string> $campaignAudiences
     * @param list<string> $userAudiences
     */
    private static function matchesAudience(array $campaignAudiences, array $userAudiences): bool
    {
        if (in_array(PopupAudience::AllAuthenticated->value, $campaignAudiences, true)) {
            return true;
        }

        return collect($campaignAudiences)
            ->intersect($userAudiences)
            ->isNotEmpty();
    }

    /**
     * @param list<string> $matchedAudiences
     */
    private static function toDto(PopupCampaign $campaign, array $matchedAudiences): ActivePopupData
    {
        $locale = app()->getLocale();

        $title = self::localized($campaign->title ?? [], $locale);
        $body = self::localized($campaign->body ?? [], $locale);

        $actionLabel = $campaign->action_label === null
            ? null
            : self::localized($campaign->action_label, $locale);

        $acknowledgementLabel = $campaign->acknowledgement_label === null
            ? null
            : self::localized($campaign->acknowledgement_label, $locale);

        return new ActivePopupData(
            campaignId: (string) $campaign->getKey(),
            type: $campaign->type->value,
            typeIcon: $campaign->type->icon(),
            typeColor: $campaign->type->color(),
            title: ['value' => $title],
            body: ['value' => $body],
            acknowledgementLabel: $acknowledgementLabel,
            actionLabel: $actionLabel,
            actionUrl: self::resolveActionUrl($campaign),
            actionIsExternal: $campaign->action_type === 'external_url',
            isDismissible: $campaign->is_dismissible,
            requiresAcknowledgement: $campaign->requires_acknowledgement,
            matchedAudiences: array_values(array_intersect($campaign->audiences ?? [], $matchedAudiences)),
            startsAt: $campaign->starts_at,
            endsAt: $campaign->ends_at,
        );
    }

    /**
     * المحتوى مترجم بقيم نصية فقط — يُعرض مُهرَّبًا دائمًا، لا HTML أبدًا.
     *
     * @param array<string, string> $translations
     */
    private static function localized(array $translations, string $locale): string
    {
        return trim((string) ($translations[$locale] ?? $translations[self::LOCALE_FALLBACK] ?? ''));
    }

    /**
     * الرابط جاهز وآمن: داخلي من سجل الصفحات المعتمد فقط، أو خارجي HTTPS.
     */
    private static function resolveActionUrl(PopupCampaign $campaign): ?string
    {
        if ($campaign->action_type === 'internal_page') {
            $routeName = PopupPageRegistry::routeFor((string) $campaign->action_target);

            if ($routeName !== null && Route::has($routeName)) {
                try {
                    return (string) route($routeName);
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }

        if ($campaign->action_type === 'external_url'
            && is_string($campaign->action_target)
            && str_starts_with(strtolower($campaign->action_target), 'https://')) {
            return $campaign->action_target;
        }

        return null;
    }
}
