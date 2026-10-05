<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Shared\Filament\UserAvatarAction;
use Shared\Support\BusinessRuleViolation;

final class EditStudentProfile extends EditRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        abort_unless($record instanceof StudentProfile, 404);

        return [
            UserAvatarAction::make(
                (string) $record->organization_id,
                (string) $record->user_id,
            )->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update')),
        ];
    }

    /**
     * الحفظ لا يتم بـ Eloquent المباشر أبدًا — كل التعديل عبر
     * UpdateStudentProfileAction مع الفاعل والسبب المدققين.
     *
     * @param array<string, mixed> $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var StudentProfile $record */
        try {
            return app(UpdateStudentProfileAction::class)->execute(
                student: $record,
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
