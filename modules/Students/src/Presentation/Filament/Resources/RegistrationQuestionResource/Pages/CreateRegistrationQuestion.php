<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource;

final class CreateRegistrationQuestion extends CreateRecord
{
    protected static string $resource = RegistrationQuestionResource::class;

    /**
     * @param array<string, mixed> $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var RegistrationQuestion */
        return static::getModel()::query()->create([
            ...$data,
            'organization_id' => (string) data_get(auth()->user(), 'organization_id'),
        ]);
    }
}
