<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;

final class EditCourse extends EditRecord
{
    protected static string $resource = CourseFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $course = $this->getRecord();
        abort_unless($course instanceof Course, 404);
        $data['category_ids'] = $course->categories()->pluck('program_categories.id')->all();

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Course, 404);

        return app(UpdateCourseAction::class)->execute($record, $data, (string) auth()->id(), (string) $data['reason']);
    }
}
