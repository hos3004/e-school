<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Contracts\PopupQueries;
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
            // الفائز الوحيد هو من تُبنى منه الـDTO، لكن eager-load هنا
            // (بدل تحميل كسول لاحقًا في toDto) يتجنب N+1 لو تغيّر ترتيب
            // التقييم مستقبلًا، ويحترم منع lazy loading في بيئة الاختبار.
            ->with('media')
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
            // منطق المطابقة الوحيد على النموذج نفسه الذي تستدعيه
            // ShowPopupAttachmentController و RecordPopupInteractionAction — لا نسخ ثانية هنا.
            if (!$campaign->isEligibleForAudiences($userAudiences)) {
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
            displayMode: $campaign->display_mode->value,
            autoDismissSeconds: $campaign->auto_dismiss_seconds,
            links: self::resolveLinks($campaign),
            media: self::resolveMedia($campaign),
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

    /**
     * روابط النص داخل جسم الرسالة: خارجية HTTPS أو إشارة داخلية
     * popup-media:{id} تُحل إلى رابط تنزيل حقيقي فقط إن كان الملف مملوكًا
     * فعلًا لنفس الحملة ومن نوع file — لا ثقة بمعرّف ميديا عشوائي مخزَّن.
     *
     * @return list<array{text: string, url: string}>
     */
    private static function resolveLinks(PopupCampaign $campaign): array
    {
        /** @var list<array<string, mixed>> $rawLinks */
        $rawLinks = $campaign->links ?? [];

        if ($rawLinks === []) {
            return [];
        }

        $fileMediaIds = $campaign->media
            ->where('kind', 'file')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $resolved = [];

        foreach ($rawLinks as $link) {
            $text = trim((string) ($link['text'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($text === '' || $url === '') {
                continue;
            }

            if (str_starts_with($url, 'popup-media:')) {
                $mediaId = substr($url, strlen('popup-media:'));

                if (!in_array($mediaId, $fileMediaIds, true)) {
                    // إشارة لملف لا ينتمي لهذه الحملة أو ليس قابلًا للتنزيل — تُسقط بصمت.
                    continue;
                }

                $resolved[] = [
                    'text' => $text,
                    'url' => route('popups.media.show', [
                        'campaign' => (string) $campaign->getKey(),
                        'media' => $mediaId,
                    ]),
                ];

                continue;
            }

            if (str_starts_with(strtolower($url), 'https://')) {
                $resolved[] = ['text' => $text, 'url' => $url];
            }
        }

        return $resolved;
    }

    /**
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     url: string,
     *     mime_type: string,
     *     has_sound: bool|null,
     *     download_request_url: string|null
     * }>
     */
    private static function resolveMedia(PopupCampaign $campaign): array
    {
        return $campaign->media
            ->map(static function ($media) use ($campaign): array {
                return [
                    'id' => (string) $media->id,
                    'kind' => $media->kind,
                    'url' => route('popups.media.show', [
                        'campaign' => (string) $campaign->getKey(),
                        'media' => (string) $media->id,
                    ]),
                    'mime_type' => $media->mime_type,
                    'has_sound' => $media->has_sound,
                    'download_request_url' => $media->kind === 'file'
                        ? route('api.popups.media.download-request', [
                            'campaign' => (string) $campaign->getKey(),
                            'media' => (string) $media->id,
                        ])
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
