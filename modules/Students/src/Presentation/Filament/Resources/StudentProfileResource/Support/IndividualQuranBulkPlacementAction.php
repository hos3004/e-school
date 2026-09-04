<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Support;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use App\Application\DTO\BulkIndividualSchedulePreview;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

final class IndividualQuranBulkPlacementAction
{
    public static function make(): BulkAction
    {
        return self::build(fromApplications: false);
    }

    public static function forApplications(): BulkAction
    {
        return self::build(fromApplications: true);
    }

    private static function build(bool $fromApplications): BulkAction
    {
        $action = BulkAction::make('place_individual_quran')
            ->label(__('students::admin.individual_quran.action'))
            ->icon('heroicon-o-book-open')
            ->color('primary')
            ->modalHeading(__('students::admin.individual_quran.heading'))
            ->modalDescription(__('students::admin.individual_quran.description'))
            ->modalSubmitActionLabel(__('students::admin.individual_quran.confirm'))
            ->modalWidth('4xl')
            ->fillForm(fn (Collection $records): array => [
                'student_ids' => self::studentProfileIds($records),
                'interval_weeks' => 1,
                'duration_minutes' => (int) config('scheduling.default_individual_duration_minutes'),
                'timezone' => (string) (auth()->user()?->getAttribute('timezone') ?? config('app.timezone')),
                'starts_on' => now()->toDateString(),
            ])
            ->form([
                Hidden::make('student_ids'),
                Select::make('staff_profile_id')
                    ->label(__('students::admin.individual_quran.teacher'))
                    ->options(fn (): array => self::action()->teacherOptions(self::organizationId()))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                CheckboxList::make('weekdays')
                    ->label(__('students::admin.individual_quran.weekdays'))
                    ->options(self::weekdayOptions())
                    ->columns(4)
                    ->live()
                    ->required()
                    ->columnSpanFull(),
                Select::make('duration_minutes')
                    ->label(__('students::admin.individual_quran.duration'))
                    ->options(collect((array) config('scheduling.individual_session_durations'))
                        ->mapWithKeys(static fn (mixed $minutes): array => [
                            (int) $minutes => __('scheduling::filament.schedule.minutes', ['minutes' => $minutes]),
                        ])->all())
                    ->live()
                    ->required(),
                TextInput::make('interval_weeks')
                    ->label(__('students::admin.individual_quran.interval_weeks'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(1)
                    ->live()
                    ->required(),
                Select::make('timezone')
                    ->label(__('students::admin.individual_quran.timezone'))
                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->live()
                    ->required(),
                DatePicker::make('starts_on')
                    ->label(__('students::admin.individual_quran.starts_on'))
                    ->minDate(now()->toDateString())
                    ->live()
                    ->required(),
                DatePicker::make('ends_on')
                    ->label(__('students::admin.individual_quran.ends_on'))
                    ->afterOrEqual('starts_on')
                    ->live(),
                Placeholder::make('allocation_preview')
                    ->label(__('students::admin.individual_quran.preview'))
                    ->content(fn (Get $get): HtmlString => self::preview($get))
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label(__('students::admin.individual_quran.reason'))
                    ->helperText(__('students::admin.individual_quran.reason_help'))
                    ->maxLength(1000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(self::handle(...));

        return $fromApplications
            ? $action
                ->authorize('scheduleIndividualAny')
                ->authorizeIndividualRecords('scheduleIndividual')
            : $action
                ->visible(static fn (): bool => (bool) auth()->user()?->can('schedule.manage'))
                ->authorizeIndividualRecords('view');
    }

    /**
     * @param Collection<int, RegistrationApplication|StudentProfile> $records
     * @param array<string, mixed> $data
     */
    private static function handle(Collection $records, array $data): void
    {
        try {
            $result = self::action()->execute(
                organizationId: self::organizationId(),
                studentProfileIds: self::studentProfileIds($records),
                staffProfileId: (string) $data['staff_profile_id'],
                weekdays: (array) $data['weekdays'],
                intervalWeeks: (int) $data['interval_weeks'],
                durationMinutes: (int) $data['duration_minutes'],
                timezone: (string) $data['timezone'],
                startsOn: (string) $data['starts_on'],
                endsOn: self::nullableString($data['ends_on'] ?? null),
                actorId: (string) auth()->id(),
                reason: (string) $data['reason'],
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title(__('students::admin.individual_quran.succeeded', ['count' => $result->createdCount()]))
            ->body(__('students::admin.individual_quran.result', [
                'failed' => $result->failedCount(),
                'skipped' => $result->skippedCount,
            ]))
            ->color($result->failedCount() > 0 ? 'warning' : 'success')
            ->send();
    }

    private static function preview(Get $get): HtmlString
    {
        try {
            $preview = self::action()->preview(
                organizationId: self::organizationId(),
                studentProfileIds: (array) $get('student_ids'),
                staffProfileId: self::nullableString($get('staff_profile_id')),
                weekdays: (array) $get('weekdays'),
                intervalWeeks: max(1, (int) $get('interval_weeks')),
                durationMinutes: (int) $get('duration_minutes'),
                timezone: (string) ($get('timezone') ?: config('app.timezone')),
                startsOn: self::nullableString($get('starts_on')),
                endsOn: self::nullableString($get('ends_on')),
            );
        } catch (BusinessRuleViolation $violation) {
            return new HtmlString('<p class="fi-color-danger text-sm">'.e($violation->getMessage()).'</p>');
        }

        return new HtmlString(self::previewHtml($preview));
    }

    private static function previewHtml(BulkIndividualSchedulePreview $preview): string
    {
        $times = $preview->assignedStartTimes === []
            ? __('students::admin.individual_quran.no_slots')
            : implode(' · ', $preview->assignedStartTimes);

        return '<div class="space-y-2 text-sm">'
            .'<p>'.e(__('students::admin.individual_quran.preview_counts', [
                'selected' => $preview->selectedCount,
                'eligible' => $preview->eligibleCount(),
                'slots' => $preview->availableSlotCount,
            ])).'</p>'
            .'<p><strong>'.e(__('students::admin.individual_quran.assigned_times')).':</strong> '.e($times).'</p>'
            .($preview->blockedCount() > 0
                ? '<p class="fi-color-warning">'.e(__('students::admin.individual_quran.skipped_notice', [
                    'count' => $preview->blockedCount(),
                ])).'</p>'
                : '')
            .'</div>';
    }

    /** @return array<int, string> */
    private static function weekdayOptions(): array
    {
        return collect(range(0, 6))->mapWithKeys(static fn (int $day): array => [
            $day => __('scheduling::filament.schedule.weekdays.'.$day),
        ])->all();
    }

    private static function action(): BulkCreateIndividualQuranSchedulesAction
    {
        return app(BulkCreateIndividualQuranSchedulesAction::class);
    }

    private static function organizationId(): string
    {
        $id = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    /**
     * @param Collection<int, RegistrationApplication|StudentProfile> $records
     * @return list<string>
     */
    private static function studentProfileIds(Collection $records): array
    {
        return $records
            ->map(static fn (RegistrationApplication|StudentProfile $record): ?string => $record instanceof StudentProfile
                ? (string) $record->getKey()
                : $record->student_profile_id)
            ->filter(static fn (?string $id): bool => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
