<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Modules\Students\Application\Actions\AcceptRegistrationApplicationAction;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Application\Actions\ReviewRegistrationApplicationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Pages;

/** شاشة مراجعة الطلبات؛ كل انتقال يمر عبر Application Action ولا يكتب Filament مباشرة. */
final class RegistrationApplicationResource extends Resource
{
    protected static ?string $model = RegistrationApplication::class;

    protected static ?string $slug = 'registration-applications';

    protected static ?int $navigationSort = 19;

    public static function getNavigationGroup(): ?string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::registration.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::registration.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::registration.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')
                ->label(__('students::attributes.full_name'))
                ->disabled(),
            DatePicker::make('date_of_birth')
                ->label(__('students::attributes.date_of_birth'))
                ->disabled(),
            Select::make('gender')
                ->label(__('students::attributes.gender'))
                ->options(self::genderOptions())
                ->disabled(),
            Select::make('country_id')
                ->label(__('students::attributes.country_id'))
                ->options(self::countryOptions())
                ->disabled(),
            Select::make('region_id')
                ->label(__('students::attributes.region_id'))
                ->options(self::regionOptions())
                ->disabled(),
            TextInput::make('email')
                ->label(__('students::attributes.email'))
                ->disabled(),
            TextInput::make('phone')
                ->label(__('students::attributes.phone'))
                ->disabled(),
            TextInput::make('preferred_program_id')
                ->label(__('students::attributes.preferred_program_id'))
                ->disabled(),
            Textarea::make('notes')
                ->label(__('students::attributes.notes'))
                ->columnSpanFull()
                ->disabled(),
            Textarea::make('decision_reason')
                ->label(__('students::attributes.decision_reason'))
                ->columnSpanFull()
                ->disabled(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('students::attributes.student_code'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('full_name')
                    ->label(__('students::attributes.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('students::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => self::statusColor($state)),
                TextColumn::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->formatStateUsing(fn (string $state): string => self::countryOptions()[$state] ?? $state),
                TextColumn::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->formatStateUsing(fn (string $state): string => self::regionOptions()[$state] ?? $state),
                TextColumn::make('duplicate_of_application_id')
                    ->label(__('students::registration.duplicate'))
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? __('students::registration.duplicate_no')
                        : __('students::registration.duplicate_yes')),
                TextColumn::make('submitted_at')
                    ->label(__('students::attributes.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('students::registration.filters.status'))
                    ->options(self::statusOptions()),
                SelectFilter::make('country_id')
                    ->label(__('students::registration.filters.country'))
                    ->options(self::countryOptions())
                    ->searchable(),
                SelectFilter::make('region_id')
                    ->label(__('students::registration.filters.region'))
                    ->options(self::regionOptions())
                    ->searchable(),
            ])
            ->recordActions([
                self::submitAction(),
                self::reviewAction(),
                self::acceptAction(),
                self::rejectAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function submitAction(): Action
    {
        return Action::make('submit')
            ->label(__('students::registration.actions.submit'))
            ->visible(fn (RegistrationApplication $record): bool => $record->status === RegistrationStatus::Draft
                && (bool) auth()->user()?->can('submit', $record))
            ->action(function (RegistrationApplication $record): void {
                app(SubmitRegistrationApplicationAction::class)->execute($record);
                self::successNotification('submitted');
            });
    }

    private static function reviewAction(): Action
    {
        return Action::make('review')
            ->label(__('students::registration.actions.review'))
            ->visible(fn (RegistrationApplication $record): bool => $record->status === RegistrationStatus::Submitted
                && (bool) auth()->user()?->can('review', $record))
            ->action(function (RegistrationApplication $record): void {
                app(ReviewRegistrationApplicationAction::class)->execute($record, (string) auth()->id());
                self::successNotification('under_review');
            });
    }

    private static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label(__('students::registration.actions.accept'))
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (RegistrationApplication $record): bool => in_array($record->status, [
                RegistrationStatus::Submitted,
                RegistrationStatus::UnderReview,
                RegistrationStatus::Accepted,
            ], true) && (bool) auth()->user()?->can('accept', $record))
            ->action(function (RegistrationApplication $record): void {
                app(AcceptRegistrationApplicationAction::class)->execute($record, (string) auth()->id());
                self::successNotification('accepted');
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('students::registration.actions.reject'))
            ->color('danger')
            ->modalHeading(__('students::registration.actions.reject_heading'))
            ->modalDescription(__('students::registration.actions.reject_description'))
            ->visible(fn (RegistrationApplication $record): bool => in_array($record->status, [
                RegistrationStatus::Submitted,
                RegistrationStatus::UnderReview,
            ], true) && (bool) auth()->user()?->can('reject', $record))
            ->form([
                Textarea::make('reason')
                    ->label(__('students::attributes.decision_reason'))
                    ->required((bool) config('admission.application.rejection_requires_reason', true))
                    ->maxLength(2000),
            ])
            ->action(function (RegistrationApplication $record, array $data): void {
                app(RejectRegistrationApplicationAction::class)->execute(
                    $record,
                    (string) ($data['reason'] ?? ''),
                    (string) auth()->id(),
                );
                self::successNotification('rejected');
            });
    }

    private static function successNotification(string $key): void
    {
        Notification::make()
            ->title(__('students::registration.messages.'.$key))
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function genderOptions(): array
    {
        return collect(StudentGender::cases())
            ->mapWithKeys(fn (StudentGender $gender): array => [$gender->value => $gender->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function countryOptions(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        return collect($geography->countries())
            ->mapWithKeys(fn (CountryData $country): array => [
                $country->id => self::localizedName($country->name),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function regionOptions(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $options = [];

        foreach ($geography->countries() as $country) {
            foreach ($geography->regionsOf($country->id) as $region) {
                $options[$region->id] = self::localizedRegionName($country, $region);
            }
        }

        return $options;
    }

    /** @param array<string, string> $name */
    private static function localizedName(array $name): string
    {
        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? (string) reset($name);
    }

    private static function localizedRegionName(CountryData $country, RegionData $region): string
    {
        return self::localizedName($country->name).' — '.self::localizedName($region->name);
    }

    private static function statusColor(RegistrationStatus $status): string
    {
        return match ($status) {
            RegistrationStatus::Draft => 'gray',
            RegistrationStatus::Submitted, RegistrationStatus::UnderReview => 'warning',
            RegistrationStatus::Accepted, RegistrationStatus::WaitingAssignment => 'info',
            RegistrationStatus::Assigned => 'success',
            RegistrationStatus::Rejected => 'danger',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationApplications::route('/'),
            'view' => Pages\ViewRegistrationApplication::route('/{record}'),
        ];
    }
}
