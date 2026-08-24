<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;

final class EditCourse extends EditRecord
{
    protected static string $resource = CourseFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            /*
             * الحذف النهائي ظاهر للمصرّح له فقط. عقد المستودع يمنع الحذف
             * الصلب لبيانات الأشخاص، والكورس ليس منها — لكنه يبقى قرارًا
             * لا يُتخذ بالخطأ، فيُخفى عمّن لا يملك صلاحيته.
             */
            ForceDeleteAction::make()
                ->visible(fn (): bool => $this->getUser()?->can('forceDelete', $this->getRecord()) === true),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $organizationId = data_get($this->getUser(), 'organization_id');
        $levelId = $data['level_id'] ?? null;

        $belongsToOrganization = is_string($organizationId)
            && $organizationId !== ''
            && is_string($levelId)
            && Level::query()
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

        // المؤسسة لا تُنقل بالتحرير؛ تُحذف من الحمولة حتى لو أُرسلت.
        unset($data['organization_id']);

        return $data;
    }
}
