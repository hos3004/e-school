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
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
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

        $attributes = $this->validatedAttributes($attributes, $isCreate);
        $attributes['organization_id'] = $organizationId;

        $tracked = [
            'internal_name', 'type', 'status', 'title', 'body', 'audiences', 'placement',
            'page_key', 'frequency', 'is_dismissible', 'requires_acknowledgement',
            'acknowledgement_label', 'action_label', 'action_type', 'action_target',
            'priority', 'starts_at', 'ends_at',
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
    private function validatedAttributes(array $attributes, bool $isCreate): array
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

        $attributes['placement'] = $this->enumValue($attributes['placement'] ?? null);
        $placement = PopupPlacement::tryFrom($attributes['placement']);
        if ($placement === null) {
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
        if (!$attributes['is_dismissible'] && !$attributes['requires_acknowledgement']) {
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

        return $attributes;
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
