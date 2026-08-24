<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource;

final class CreateNotificationTemplate extends CreateRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    /**
     * القالب المُنشأ من اللوحة خاص بمؤسسة الأدمن دائمًا — لا يُنشئ أحد قالبًا
     * عامًا يدويًا؛ العام يأتي من البذور فقط ويُخصَّص بنسخة override.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        $data['organization_id'] = $organizationId;

        $exists = NotificationTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('event_key', $data['event_key'] ?? '')
            ->where('channel', $data['channel'] ?? '')
            ->where('locale', $data['locale'] ?? '')
            ->exists();

        if ($exists) {
            Notification::make()
                ->title(__('notifications::templates_admin.duplicate'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.event_key' => __('notifications::templates_admin.duplicate'),
            ]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
