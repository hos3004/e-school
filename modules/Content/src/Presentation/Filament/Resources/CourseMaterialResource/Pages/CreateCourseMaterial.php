<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Content\Application\Actions\UploadCourseMaterialAction;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;

final class CreateCourseMaterial extends CreateRecord
{
    protected static string $resource = CourseMaterialResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->withStorageMetadata($data);

        return app(UploadCourseMaterialAction::class)->execute(
            organizationId: (string) auth()->user()?->getAttribute('organization_id'),
            data: $data,
            reason: (string) $data['reason'],
            actorId: (string) auth()->id(),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withStorageMetadata(array $data): array
    {
        if (($data['type'] ?? null) !== MaterialType::File->value || !is_string($data['path'] ?? null)) {
            $data['disk'] = null;
            $data['size_bytes'] = null;

            return $data;
        }

        $disk = (string) config('content.uploads.disk');
        $data['disk'] = $disk;
        $data['size_bytes'] = Storage::disk($disk)->exists($data['path'])
            ? Storage::disk($disk)->size($data['path'])
            : null;

        return $data;
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('content::messages.created');
    }
}
