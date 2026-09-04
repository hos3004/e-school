<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Support\IndividualQuranBulkPlacementAction;

final class IndividualQuranPlacement extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    /** @var array<string, string>|null */
    private ?array $activeScheduleIds = null;

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
        $studentIds = $this->placement()->individualQuranStudentIds($organizationId);
        $query = StudentProfileResource::getEloquentQuery();

        return $studentIds === [] ? $query->whereRaw('1 = 0') : $query->whereKey($studentIds);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordActions([
                Action::make('edit_individual_quran_placement')
                    ->label(__('students::admin.individual_quran.edit_action'))
                    ->tooltip(__('students::admin.individual_quran.edit_action'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (StudentProfile $record): bool => $this->hasActiveSchedule($record))
                    ->url(fn (StudentProfile $record): string => $this->editScheduleUrl($record)),
            ])
            ->toolbarActions([
                IndividualQuranBulkPlacementAction::make(),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (StudentProfile $record): bool => !$this->hasActiveSchedule($record),
            )
            ->recordClasses(fn (StudentProfile $record): ?string => $this->hasActiveSchedule($record)
                ? '!bg-success-50 dark:!bg-success-950/30'
                : null)
            ->recordAction(null)
            ->recordUrl(null);
    }

    private function hasActiveSchedule(StudentProfile $student): bool
    {
        return isset($this->scheduleIds()[(string) $student->getKey()]);
    }

    private function editScheduleUrl(StudentProfile $student): string
    {
        $scheduleId = $this->scheduleIds()[(string) $student->getKey()] ?? null;

        return $scheduleId === null
            ? '#'
            : route('filament.admin.resources.schedules.edit', ['record' => $scheduleId]);
    }

    /** @return array<string, string> */
    private function scheduleIds(): array
    {
        return $this->activeScheduleIds ??= $this->placement()->activeScheduleIdsByStudent(
            (string) auth()->user()?->getAttribute('organization_id'),
        );
    }

    private function placement(): BulkCreateIndividualQuranSchedulesAction
    {
        return app(BulkCreateIndividualQuranSchedulesAction::class);
    }
}
