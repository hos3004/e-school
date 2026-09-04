<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class IndividualQuranPlacement extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user !== null
            && (bool) $user->can('student.view.any')
            && (bool) $user->can('schedule.manage');
    }

    public function getTitle(): string
    {
        return __('students::admin.individual_quran.page_title');
    }

    public function getSubheading(): string
    {
        return __('students::admin.individual_quran.page_description');
    }

    /** @return Builder<StudentProfile> */
    protected function getTableQuery(): Builder
    {
        $organizationId = (string) auth()->user()?->getAttribute('organization_id');
        $eligibleIds = app(BulkCreateIndividualQuranSchedulesAction::class)
            ->eligibleStudentIds($organizationId);
        $query = StudentProfileResource::getEloquentQuery();

        return $eligibleIds === [] ? $query->whereRaw('1 = 0') : $query->whereKey($eligibleIds);
    }
}
