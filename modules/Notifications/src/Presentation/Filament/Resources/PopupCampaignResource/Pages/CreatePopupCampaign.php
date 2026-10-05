<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;
use Shared\Support\BusinessRuleViolation;

final class CreatePopupCampaign extends CreateRecord
{
    protected static string $resource = PopupCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * الإنشاء لا يتم بـEloquent المباشر — كل شيء عبر Action مع التدقيق.
     *
     * @param array<string, mixed> $data
     */
    protected function handleRecordCreation(array $data): PopupCampaign
    {
        try {
            return app(SavePopupCampaignAction::class)->execute(
                campaign: null,
                organizationId: (string) data_get(auth()->user(), 'organization_id'),
                attributes: $this->campaignAttributes($data),
                scheduleChanges: null,
                actorId: (string) auth()->id(),
                reason: (string) ($data['reason'] ?? ''),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function campaignAttributes(array $data): array
    {
        $actionType = (string) ($data['action_type'] ?? '');
        $actionTarget = match ($actionType) {
            'internal_page' => $data['internal_action_target'] ?? null,
            'external_url' => $data['external_action_target'] ?? null,
            default => null,
        };

        return collect($data)
            ->except(['reason', 'internal_action_target', 'external_action_target'])
            ->put('action_type', $actionType !== '' ? $actionType : null)
            ->put('action_target', $actionTarget)
            ->all();
    }
}
