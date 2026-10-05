<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationFormResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Presentation\Filament\Resources\RegistrationFormResource;

final class EditRegistrationForm extends EditRecord
{
    protected static string $resource = RegistrationFormResource::class;

    private string $changeReason = '';

    /** @var array<string, mixed> */
    private array $before = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var RegistrationForm $form */
        $form = $this->record;
        $form->load('questions');
        $this->before = self::snapshot($form);
        $this->changeReason = trim((string) ($data['change_reason'] ?? ''));
        unset($data['change_reason']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var RegistrationForm $form */
        $form = $this->record;
        $form->load('questions');

        app(AuditRecorder::class)->record(
            organizationId: $form->organization_id,
            actorId: (string) auth()->id(),
            actorType: 'user',
            action: 'students.registration_form_updated',
            auditableType: 'registration_form',
            auditableId: (string) $form->getKey(),
            oldValues: $this->before,
            newValues: self::snapshot($form),
            reason: $this->changeReason,
        );
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    private static function snapshot(RegistrationForm $form): array
    {
        return [
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'is_active' => $form->is_active,
            'questions' => $form->questions->map->only([
                'id', 'question', 'type', 'options', 'is_required', 'is_active', 'is_filterable', 'sort_order',
            ])->all(),
        ];
    }
}
