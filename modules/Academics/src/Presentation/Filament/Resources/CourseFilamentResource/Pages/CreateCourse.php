<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;

final class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseFilamentResource::class;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $organizationId = data_get($this->getUser(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            throw ValidationException::withMessages([
                'level_id' => __('academics::filament.course.errors.no_organization'),
            ]);
        }

        /*
         * المستوى يأتي من قائمة مُصفّاة بالفعل، لكن القائمة تُبنى في المتصفح
         * ويمكن التلاعب بقيمتها في الطلب. التحقق هنا خادمي: لا يُربط كورس
         * بمستوى برنامجٍ من مؤسسة أخرى مهما كانت القيمة المرسلة.
         */
        $levelId = $data['level_id'] ?? null;

        $belongsToOrganization = is_string($levelId) && Level::query()
            ->whereKey($levelId)
            ->whereHas(
                'program',
                static fn ($program) => $program->where('organization_id', $organizationId),
            )
            ->exists();

        if (!$belongsToOrganization) {
            throw ValidationException::withMessages([
                'level_id' => __('academics::filament.course.errors.level_outside_organization'),
            ]);
        }

        $data['organization_id'] = $organizationId;

        return $data;
    }
}
