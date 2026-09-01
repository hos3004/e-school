<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;
use Shared\Support\BusinessRuleViolation;

final class EditPopupCampaign extends EditRecord
{
    protected static string $resource = PopupCampaignResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        /** @var PopupCampaign|null $record */
        $record = $parameters['record'] ?? null;

        // المحتوى لا يُعدَّل أثناء النشر — إيقاف مؤقت أولًا.
        if ($record instanceof PopupCampaign && $record->status->value === 'published') {
            return false;
        }

        return parent::canAccess($parameters)
            && (bool) (auth()->user()?->can('update', PopupCampaign::class) ?? false);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof PopupCampaign, 404);

        try {
            return app(SavePopupCampaignAction::class)->execute(
                campaign: $record,
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

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $targetKey = $data['action_type'] === 'internal_page'
            ? 'internal_action_target'
            : 'external_action_target';
        $data[$targetKey] = $data['action_target'] ?? null;

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function campaignAttributes(array $data): array
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
