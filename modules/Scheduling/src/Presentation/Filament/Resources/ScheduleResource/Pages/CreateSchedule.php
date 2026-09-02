<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    /**
     * تعبئة المجموعة مسبقًا حين نأتي من صفحتها.
     *
     * كان المنسّق يترك المجموعة، ويفتح الجداول، ويعيد اختيار المجموعة نفسها من
     * قائمة طويلة — انقطاعٌ في التسلسل بلا سبب. المعرّف يصل في `?group=` من زر
     * «جدولة حصص» في صفحة المجموعة، ويبقى قابلًا للتغيير هنا.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $group = request()->query('group');

        if (is_string($group) && $group !== '') {
            $data['target_type'] = 'group';
            $data['group_id'] = $group;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return app(CreateScheduleAction::class)->execute(
            $organizationId,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
