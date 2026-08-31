<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationFormResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Presentation\Filament\Resources\RegistrationFormResource;

final class CreateRegistrationForm extends CreateRecord
{
    protected static string $resource = RegistrationFormResource::class;

    private string $changeReason = '';

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->changeReason = trim((string) ($data['change_reason'] ?? ''));
        unset($data['change_reason']);
        $data['organization_id'] = RegistrationFormResource::organizationId();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var RegistrationForm $form */
        $form = $this->record;
        $form->load('questions');

        app(AuditRecorder::class)->record(
            organizationId: $form->organization_id,
            actorId: (string) auth()->id(),
            actorType: 'user',
            action: 'students.registration_form_created',
            auditableType: 'registration_form',
            auditableId: (string) $form->getKey(),
            oldValues: null,
            newValues: self::snapshot($form),
            reason: $this->changeReason,
        );
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
