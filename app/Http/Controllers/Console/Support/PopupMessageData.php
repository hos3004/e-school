<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;

/**
 * بيانات صفحة الرسائل المنبثقة في /manage — قائمة الحملات وقيود الإعداد
 * وقوائم الخيارات. لا منطق حفظ أو انتقال هنا؛ هذا كله عبر
 * SavePopupCampaignAction وTransitionPopupCampaignAction كما في Filament.
 */
final class PopupMessageData
{
    /**
     * @return array<string, mixed>
     */
    public function forOrganization(string $organizationId): array
    {
        $campaigns = PopupCampaign::query()
            ->forOrganization($organizationId)
            ->with('media')
            ->orderByDesc('created_at')
            ->limit((int) config('notifications.admin_hub.max_items', 100))
            ->get();

        return [
            'items' => $campaigns
                ->map(fn (PopupCampaign $campaign): array => $this->summary($campaign))
                ->values()
                ->all(),
            'limits' => [
                'priorityMin' => (int) config('popups.priority.min', 1),
                'priorityMax' => (int) config('popups.priority.max', 10),
                'priorityDefault' => (int) config('popups.priority.default', 5),
                'autoDismissMinSeconds' => (int) config('popups.auto_dismiss.min_seconds', 3),
                'autoDismissMaxSeconds' => (int) config('popups.auto_dismiss.max_seconds', 300),
                'titleMax' => (int) config('popups.content.title_max', 120),
                'bodyMax' => (int) config('popups.content.body_max', 2000),
                'internalNameMax' => (int) config('popups.content.internal_name_max', 120),
                'linkTextMax' => (int) config('popups.content.link_text_max', 60),
                'linkUrlMax' => (int) config('popups.content.link_url_max', 500),
                'maxLinks' => (int) config('popups.content.max_links', 10),
            ],
            'options' => [
                'types' => PopupType::options(),
                'displayModes' => PopupDisplayMode::options(),
                'audiences' => PopupAudience::options(),
                'placements' => PopupPlacement::options(),
                'pageKeys' => PopupPageRegistry::options(),
                'frequencies' => PopupFrequency::options(),
                'statuses' => PopupCampaignStatus::options(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(PopupCampaign $campaign): array
    {
        $status = $campaign->status;

        return [
            'id' => (string) $campaign->getKey(),
            'internal_name' => $campaign->internal_name,
            'type' => $campaign->type->value,
            'status' => $status->value,
            'title' => $campaign->title,
            'body' => $campaign->body,
            'audiences' => $campaign->audiences ?? [],
            'excluded_audiences' => $campaign->excluded_audiences ?? [],
            'placement' => $campaign->placement->value,
            'display_mode' => $campaign->display_mode->value,
            'page_key' => $campaign->page_key,
            'frequency' => $campaign->frequency->value,
            'is_dismissible' => $campaign->is_dismissible,
            'requires_acknowledgement' => $campaign->requires_acknowledgement,
            'acknowledgement_label' => $campaign->acknowledgement_label,
            'auto_dismiss_seconds' => $campaign->auto_dismiss_seconds,
            'action_type' => $campaign->action_type,
            'action_target' => $campaign->action_target,
            'action_label' => $campaign->action_label,
            'links' => $campaign->links ?? [],
            'priority' => $campaign->priority,
            'starts_at' => $campaign->starts_at->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'published_at' => $campaign->published_at?->toIso8601String(),
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
            'media' => $campaign->media
                ->map(static fn (PopupCampaignMedia $media): array => [
                    'id' => (string) $media->getKey(),
                    'kind' => $media->kind,
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size_bytes' => $media->size_bytes,
                    'has_sound' => $media->has_sound,
                    'position' => $media->position,
                    'url' => route('popups.media.show', [
                        'campaign' => (string) $campaign->getKey(),
                        'media' => (string) $media->getKey(),
                    ]),
                ])
                ->values()
                ->all(),
            // الانتقالات المسموحة تُحسب هنا مرة واحدة — الواجهة تعرض الأزرار
            // حسبها فقط، ولا تعيد تطبيق قواعد آلة الحالة بنفسها.
            'can_transition_to' => [
                'published' => $status->canTransitionTo(PopupCampaignStatus::Published),
                'paused' => $status->canTransitionTo(PopupCampaignStatus::Paused),
                'archived' => $status->canTransitionTo(PopupCampaignStatus::Archived),
            ],
            'editable' => $status !== PopupCampaignStatus::Published,
            'urls' => [
                'update' => route('console.popup-messages.update', ['campaign' => (string) $campaign->getKey()]),
                'media' => route('console.popup-messages.media.store', ['campaign' => (string) $campaign->getKey()]),
                'publish' => route('console.popup-messages.publish', ['campaign' => (string) $campaign->getKey()]),
                'pause' => route('console.popup-messages.pause', ['campaign' => (string) $campaign->getKey()]),
                'archive' => route('console.popup-messages.archive', ['campaign' => (string) $campaign->getKey()]),
            ],
        ];
    }
}
