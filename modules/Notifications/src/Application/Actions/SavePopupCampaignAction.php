<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Shared\Support\BusinessRuleViolation;
use Throwable;

/**
 * حفظ الحملة (إنشاء/تعديل) — لا حفظ Eloquent مباشر من الواجهة.
 *
 * المؤسسة مشتقة من جلسة الفاعل وتُمرَّر صراحةً؛ لا يُوثق بأي organization_id
 * قادم من النموذج. كما تُفرض قواعد السلامة نفسها عند أي استدعاء للـAction.
 */
final class SavePopupCampaignAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $scheduleChanges
     */
    public function execute(
        ?PopupCampaign $campaign,
        string $organizationId,
        array $attributes,
        ?array $scheduleChanges,
        string $actorId,
        string $reason,
    ): PopupCampaign {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'notifications.popup_reason_required',
                'notifications::popups.errors.reason_required',
            );
        }

        if ($organizationId === '') {
            $this->violate('notifications.popup_foreign_tenant', 'notifications::popups.errors.foreign_tenant');
        }

        $isCreate = $campaign === null;
        /** @var PopupCampaign $campaign */
        $campaign = $campaign ?? new PopupCampaign;

        if (!$isCreate && !hash_equals($organizationId, (string) $campaign->organization_id)) {
            $this->violate('notifications.popup_foreign_tenant', 'notifications::popups.errors.foreign_tenant');
        }

        if (!$isCreate && $campaign->status === PopupCampaignStatus::Published) {
            throw BusinessRuleViolation::make(
                'notifications.popup_locked_while_published',
                'notifications::popups.errors.locked_while_published',
            );
        }

        $attributes = $this->validatedAttributes($attributes, $isCreate, $campaign);
        $attributes['organization_id'] = $organizationId;

        $tracked = [
            'internal_name', 'type', 'status', 'title', 'body', 'audiences', 'excluded_audiences',
            'placement', 'display_mode', 'page_key', 'frequency', 'is_dismissible', 'requires_acknowledgement',
            'acknowledgement_label', 'action_label', 'action_type', 'action_target', 'links',
            'priority', 'auto_dismiss_seconds', 'starts_at', 'ends_at',
        ];
        $old = $isCreate ? null : $this->auditValues($campaign->only($tracked));

        return DB::transaction(function () use (
            $campaign,
            $attributes,
            $actorId,
            $organizationId,
            $isCreate,
            $old,
            $reason,
            $tracked,
        ): PopupCampaign {
            $campaign->fill($attributes);
            $campaign->created_by = $campaign->created_by ?? $actorId;
            $campaign->updated_by = $actorId;
            $campaign->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: $isCreate ? 'notifications.popup_campaign_created' : 'notifications.popup_campaign_updated',
                auditableType: 'popup_campaign',
                auditableId: (string) $campaign->getKey(),
                oldValues: $old,
                newValues: $this->auditValues($campaign->only($tracked)),
                reason: trim($reason),
            );

            return $campaign;
        });
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validatedAttributes(array $attributes, bool $isCreate, PopupCampaign $campaign): array
    {
        unset($attributes['organization_id'], $attributes['created_by'], $attributes['updated_by']);

        if ($isCreate) {
            $attributes['status'] = PopupCampaignStatus::Draft->value;
        } else {
            unset($attributes['status']);
        }

        $attributes['internal_name'] = trim((string) ($attributes['internal_name'] ?? ''));
        if ($attributes['internal_name'] === '') {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        $attributes['type'] = $this->enumValue($attributes['type'] ?? null);
        if (PopupType::tryFrom($attributes['type']) === null) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        foreach (['title', 'body'] as $field) {
            $values = is_array($attributes[$field] ?? null) ? $attributes[$field] : [];
            $attributes[$field] = collect($values)
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->all();
        }
        if (($attributes['title']['ar'] ?? '') === '' || ($attributes['body']['ar'] ?? '') === '') {
            $this->violate('notifications.popup_arabic_content_required', 'notifications::popups.errors.arabic_content_required');
        }

        $audiences = array_values(array_unique(array_map(
            fn (mixed $audience): string => $this->enumValue($audience),
            is_array($attributes['audiences'] ?? null) ? $attributes['audiences'] : [],
        )));
        if ($audiences === [] || collect($audiences)->contains(
            static fn (string $audience): bool => PopupAudience::tryFrom($audience) === null,
        )) {
            $this->violate('notifications.popup_audience_required', 'notifications::popups.errors.audience_required');
        }
        $attributes['audiences'] = $audiences;

        // الاستثناء: قيم PopupAudience صالحة، ولا يجوز أن يتقاطع مع audiences
        // — تناقض إداري (اختر الجمهور واستبعده معًا) يُرفض بدل حسم فائز صامت.
        $excludedAudiences = array_values(array_unique(array_map(
            fn (mixed $audience): string => $this->enumValue($audience),
            is_array($attributes['excluded_audiences'] ?? null) ? $attributes['excluded_audiences'] : [],
        )));
        if (collect($excludedAudiences)->contains(
            static fn (string $audience): bool => PopupAudience::tryFrom($audience) === null,
        )) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }
        if (collect($excludedAudiences)->intersect($audiences)->isNotEmpty()) {
            $this->violate('notifications.popup_contradictory_audience', 'notifications::popups.errors.contradictory_audience');
        }
        $attributes['excluded_audiences'] = $excludedAudiences;

        $attributes['placement'] = $this->enumValue($attributes['placement'] ?? null);
        $placement = PopupPlacement::tryFrom($attributes['placement']);
        if ($placement === null) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        $attributes['display_mode'] = $this->enumValue(
            $attributes['display_mode'] ?? PopupDisplayMode::BottomBanner->value,
        );
        if (PopupDisplayMode::tryFrom($attributes['display_mode']) === null) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        $attributes['page_key'] = filled($attributes['page_key'] ?? null)
            ? (string) $attributes['page_key']
            : null;
        if ($placement === PopupPlacement::SpecificPage) {
            if (!array_key_exists((string) $attributes['page_key'], PopupPageRegistry::options())) {
                $this->violate('notifications.popup_invalid_page_key', 'notifications::popups.errors.invalid_page_key');
            }
        } else {
            $attributes['page_key'] = null;
        }

        $attributes['frequency'] = $this->enumValue($attributes['frequency'] ?? null);
        if (PopupFrequency::tryFrom($attributes['frequency']) === null) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        $attributes['is_dismissible'] = (bool) ($attributes['is_dismissible'] ?? false);
        $attributes['requires_acknowledgement'] = (bool) ($attributes['requires_acknowledgement'] ?? false);
        $attributes['auto_dismiss_seconds'] = filled($attributes['auto_dismiss_seconds'] ?? null)
            ? (int) $attributes['auto_dismiss_seconds']
            : null;

        if ($attributes['auto_dismiss_seconds'] !== null) {
            $minSeconds = (int) config('popups.auto_dismiss.min_seconds', 3);
            $maxSeconds = (int) config('popups.auto_dismiss.max_seconds', 300);
            if ($attributes['auto_dismiss_seconds'] < $minSeconds || $attributes['auto_dismiss_seconds'] > $maxSeconds) {
                $this->violate(
                    'notifications.popup_invalid_auto_dismiss',
                    'notifications::popups.errors.invalid_auto_dismiss',
                );
            }
        }

        // يطابق PopupCampaign::hasSafeExit() حرفيًا عمدًا — لا يجوز
        // أن تقبل هذه الدالة حملة وقت الحفظ ثم يرفضها فحص النشر
        // لاحقًا (hasSafeExit) — المنطقان يجب أن يبقيا متطابقين دائمًا.
        // auto_dismiss_seconds > 0 مخرج آمن مقبول أيضًا: حملة غير قابلة
        // للإغلاق يدويًا وغير مطالبة بإقرار، لكنها تختفي من
        // نفسها بعد مدة محددة، فلا تحبس المستخدم فعليًا.
        $hasSafeExit = $attributes['is_dismissible']
            || $attributes['requires_acknowledgement']
            || ($attributes['auto_dismiss_seconds'] !== null && $attributes['auto_dismiss_seconds'] > 0);
        if (!$hasSafeExit) {
            $this->violate('notifications.popup_unsafe_exit', 'notifications::popups.errors.unsafe_exit');
        }
        if (!$attributes['requires_acknowledgement']) {
            $attributes['acknowledgement_label'] = null;
        }

        $priority = filter_var($attributes['priority'] ?? null, FILTER_VALIDATE_INT);
        if ($priority === false
            || $priority < (int) config('popups.priority.min', 1)
            || $priority > (int) config('popups.priority.max', 10)) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }
        $attributes['priority'] = $priority;

        try {
            $startsAt = CarbonImmutable::parse($attributes['starts_at'] ?? null)->utc();
            $endsAt = filled($attributes['ends_at'] ?? null)
                ? CarbonImmutable::parse($attributes['ends_at'])->utc()
                : null;
        } catch (Throwable) {
            $this->violate('notifications.popup_invalid_window', 'notifications::popups.errors.invalid_window');
        }
        if ($endsAt !== null && !$endsAt->greaterThan($startsAt)) {
            $this->violate('notifications.popup_invalid_window', 'notifications::popups.errors.invalid_window');
        }
        $attributes['starts_at'] = $startsAt;
        $attributes['ends_at'] = $endsAt;

        $actionType = filled($attributes['action_type'] ?? null) ? (string) $attributes['action_type'] : null;
        $actionTarget = filled($attributes['action_target'] ?? null) ? trim((string) $attributes['action_target']) : null;
        if ($actionType === 'internal_page') {
            if ($actionTarget === null || !array_key_exists($actionTarget, PopupPageRegistry::options())) {
                $this->violate('notifications.popup_invalid_page_key', 'notifications::popups.errors.invalid_page_key');
            }
        } elseif ($actionType === 'external_url') {
            if ($actionTarget === null || preg_match('#^https://[^\s]+$#i', $actionTarget) !== 1) {
                $this->violate('notifications.popup_invalid_action', 'notifications::popups.errors.invalid_action');
            }
        } elseif ($actionType !== null) {
            $this->violate('notifications.popup_invalid_action', 'notifications::popups.errors.invalid_action');
        } else {
            $actionTarget = null;
            $attributes['action_label'] = null;
        }
        $attributes['action_type'] = $actionType;
        $attributes['action_target'] = $actionTarget;

        $attributes['links'] = $this->validatedLinks($attributes['links'] ?? null, $attributes['body'], $campaign);

        return $attributes;
    }

    /**
     * روابط «كلمة قابلة للنقر» داخل نص الحملة.
     *
     * text يجب أن يظهر فعليًا في نص إحدى اللغات المخزَّنة، وإلا فلا معنى
     * لجعله قابلًا للنقر عند العرض. url إما رابط خارجي https:// أو مرجع
     * popup-media:{id} لملف مرفوع فعليًا بهذه الحملة بالذات ونوعه file —
     * لا يجوز الإشارة إلى ميديا حملة أخرى ولا إلى صورة/فيديو/صوت كرابط تنزيل.
     *
     * @param array<string, string> $body
     * @return list<array{text: string, url: string}>
     */
    private function validatedLinks(mixed $rawLinks, array $body, PopupCampaign $campaign): array
    {
        $links = is_array($rawLinks) ? $rawLinks : [];

        $maxLinks = (int) config('popups.content.max_links', 10);
        if (count($links) > $maxLinks) {
            $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
        }

        $textMax = (int) config('popups.content.link_text_max', 60);
        $urlMax = (int) config('popups.content.link_url_max', 500);
        $bodyHaystack = implode("\n", array_values($body));
        $campaignId = $campaign->getKey();

        $normalized = [];

        foreach ($links as $link) {
            if (!is_array($link)) {
                $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
            }

            $text = trim((string) ($link['text'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($text === '' || mb_strlen($text) > $textMax) {
                $this->violate('notifications.popup_invalid_configuration', 'notifications::popups.errors.invalid_configuration');
            }

            if ($url === '' || mb_strlen($url) > $urlMax) {
                $this->violate('notifications.popup_invalid_link_url', 'notifications::popups.errors.invalid_link_url');
            }

            if (!str_contains($bodyHaystack, $text)) {
                $this->violate(
                    'notifications.popup_link_text_not_in_body',
                    'notifications::popups.errors.link_text_not_in_body',
                );
            }

            if (preg_match('#^https://[^\s]+$#i', $url) === 1) {
                $normalized[] = ['text' => $text, 'url' => $url];

                continue;
            }

            if (preg_match('#^popup-media:([A-Za-z0-9]+)$#', $url, $matches) === 1) {
                $mediaId = $matches[1];
                $exists = $campaignId !== null && PopupCampaignMedia::query()
                    ->where('id', $mediaId)
                    ->where('campaign_id', (string) $campaignId)
                    ->where('kind', 'file')
                    ->exists();

                if (!$exists) {
                    $this->violate(
                        'notifications.popup_invalid_media_link',
                        'notifications::popups.errors.invalid_media_link',
                    );
                }

                $normalized[] = ['text' => $text, 'url' => $url];

                continue;
            }

            $this->violate('notifications.popup_invalid_link_url', 'notifications::popups.errors.invalid_link_url');
        }

        return $normalized;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function auditValues(array $values): array
    {
        return collect($values)->map(static function (mixed $value): string {
            if ($value instanceof BackedEnum) {
                return (string) $value->value;
            }

            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        })->all();
    }

    private function violate(string $rule, string $message): never
    {
        throw BusinessRuleViolation::make($rule, $message);
    }
}
