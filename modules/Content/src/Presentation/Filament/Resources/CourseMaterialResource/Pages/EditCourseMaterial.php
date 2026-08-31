<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Content\Application\Actions\UpdateCourseMaterialAction;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;

final class EditCourseMaterial extends EditRecord
{
    protected static string $resource = CourseMaterialResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof CourseMaterial, 404);
        $data = $this->withStorageMetadata($record, $data);

        return app(UpdateCourseMaterialAction::class)->execute(
            material: $record,
            data: $data,
            reason: (string) $data['reason'],
            actorId: (string) auth()->id(),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withStorageMetadata(CourseMaterial $record, array $data): array
    {
        if (($data['type'] ?? null) !== MaterialType::File->value || !is_string($data['path'] ?? null)) {
            $data['disk'] = null;
            $data['size_bytes'] = null;

            return $data;
        }

        if ($data['path'] === $record->path) {
            $data['disk'] = $record->disk;
            $data['size_bytes'] = $record->size_bytes;

            return $data;
        }

        $disk = (string) config('content.uploads.disk');
        $data['disk'] = $disk;
        $data['size_bytes'] = Storage::disk($disk)->exists($data['path'])
            ? Storage::disk($disk)->size($data['path'])
            : null;

        return $data;
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('content::messages.updated');
    }
}
