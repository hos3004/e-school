<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

/**
 * إدارة ملفات الطلاب في لوحة التحكم — كل النصوص عبر ملفات الترجمة.
 */
final class StudentProfileResource extends Resource
{
    protected static ?string $model = StudentProfile::class;

    protected static ?string $slug = 'students';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::filament.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::filament.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::filament.plural_model_label');
    }

    /** ملفات الطلاب لا تُنشأ إلا من قبول طلب التسجيل. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('organization_id')
                    ->label(__('students::attributes.organization_id'))
                    ->required()
                    ->length(26),

                TextInput::make('user_id')
                    ->label(__('students::attributes.user_id'))
                    ->nullable()
                    ->length(26)
                    ->unique(ignoreRecord: true),

                TextInput::make('student_code')
                    ->label(__('students::attributes.student_code'))
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),

                DatePicker::make('date_of_birth')
                    ->label(__('students::attributes.date_of_birth'))
                    ->maxDate(now()->toDateString())
                    ->nullable(),

                Select::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->options(collect(StudentGender::cases())
                        ->mapWithKeys(fn (StudentGender $g): array => [$g->value => $g->label()])
                        ->all())
                    ->nullable(),

                TextInput::make('nationality')
                    ->label(__('students::attributes.nationality'))
                    ->length(2)
                    ->nullable(),

                Select::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->options(self::countryOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable(),

                Select::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->options(function (callable $get): array {
                        $countryId = $get('country_id');

                        return is_string($countryId) ? self::regionOptions($countryId) : [];
                    })
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('city')
                    ->label(__('students::attributes.city'))
                    ->maxLength(120)
                    ->nullable(),

                Select::make('preferred_language')
                    ->label(__('students::attributes.preferred_language'))
                    ->options([
                        'ar' => __('students::languages.ar'),
                        'en' => __('students::languages.en'),
                        'fr' => __('students::languages.fr'),
                    ])
                    ->nullable(),

                DatePicker::make('joined_at')
                    ->label(__('students::attributes.joined_at'))
                    ->nullable(),

                Textarea::make('notes')
                    ->label(__('students::attributes.notes'))
                    ->columnSpanFull()
                    ->maxLength(5000)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_code')
                    ->label(__('students::attributes.student_code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrationApplication.full_name')
                    ->label(__('students::filament.student_name'))
                    ->searchable(),

                TextColumn::make('registrationApplication.status')
                    ->label(__('students::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (?RegistrationStatus $state): ?string => $state?->label()),

                TextColumn::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?StudentGender $state): ?string => $state?->label())
                    ->sortable(),

                TextColumn::make('city')
                    ->label(__('students::attributes.city'))
                    ->toggleable(),

                TextColumn::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? self::countryOptions()[$state] ?? $state : null)
                    ->toggleable(),

                TextColumn::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? self::regionOptions()[$state] ?? $state : null)
                    ->toggleable(),

                TextColumn::make('joined_at')
                    ->label(__('students::attributes.joined_at'))
                    ->date()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->label(__('students::filament.archived_at'))
                    ->dateTime()
                    ->placeholder(__('students::filament.not_archived'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->options(collect(StudentGender::cases())
                        ->mapWithKeys(fn (StudentGender $g): array => [$g->value => $g->label()])
                        ->all()),

                SelectFilter::make('country_id')
                    ->label(__('students::registration.filters.country'))
                    ->options(self::countryOptions())
                    ->searchable(),

                SelectFilter::make('region_id')
                    ->label(__('students::registration.filters.region'))
                    ->options(self::regionOptions())
                    ->searchable(),

                SelectFilter::make('registration_status')
                    ->label(__('students::registration.filters.status'))
                    ->options(self::statusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return $query->when(
                            is_string($status) && $status !== '',
                            fn (Builder $studentQuery): Builder => $studentQuery->whereHas(
                                'registrationApplication',
                                fn (Builder $applicationQuery): Builder => $applicationQuery->where('status', $status),
                            ),
                        );
                    }),

                TrashedFilter::make(),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentProfiles::route('/'),
            'create' => Pages\CreateStudentProfile::route('/create'),
            'view' => Pages\ViewStudentProfile::route('/{record}'),
            'edit' => Pages\EditStudentProfile::route('/{record}/edit'),
        ];
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
    private static function regionOptions(?string $countryId = null): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $options = [];

        if ($countryId !== null) {
            foreach ($geography->regionsOf($countryId) as $region) {
                $options[$region->id] = self::localizedName($region->name);
            }

            return $options;
        }

        foreach ($geography->countries() as $country) {
            foreach ($geography->regionsOf($country->id) as $region) {
                $options[$region->id] = self::localizedRegionName($country, $region);
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @param array<string, string> $name */
    private static function localizedName(array $name): string
    {
        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? reset($name) ?: '';
    }

    private static function localizedRegionName(CountryData $country, RegionData $region): string
    {
        return self::localizedName($country->name).' — '.self::localizedName($region->name);
    }
}
