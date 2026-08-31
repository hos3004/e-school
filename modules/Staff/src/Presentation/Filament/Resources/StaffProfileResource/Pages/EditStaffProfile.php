<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Modules\Staff\Application\Actions\UpdateStaffProfileAction;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Shared\Filament\UserAvatarAction;
use Shared\Support\BusinessRuleViolation;

final class EditStaffProfile extends EditRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserAvatarAction::make(
                (string) $this->getRecord()->organization_id,
                (string) $this->getRecord()->user_id,
            )->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update')),
        ];
    }

    /**
     * الحفظ لا يتم بـ Eloquent المباشر أبدًا — كل التعديل عبر
     * UpdateStaffProfileAction مع الفاعل والسبب المدققين.
     *
     * @param array<string, mixed> $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var StaffProfile $record */
        try {
            return app(UpdateStaffProfileAction::class)->execute(
                profile: $record,
                changes: collect($data)->except('reason')->all(),
                actorId: (string) auth()->id(),
                reason: (string) ($data['reason'] ?? ''),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()
                ->title($violation->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
