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
                attributes: collect($data)->except('reason')->all(),
                scheduleChanges: null,
                actorId: (string) auth()->id(),
                reason: (string) ($data['reason'] ?? ''),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
