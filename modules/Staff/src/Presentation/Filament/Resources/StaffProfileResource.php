<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages\ManageStaffProfiles;

final class StaffProfileResource extends Resource
{
    protected static ?string $model = StaffProfile::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): string
    {
        return __('staff::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('staff::filament.profile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff::filament.profile.plural_label');
    }

    public static function getNavigationBadge(): string
    {
        /** @var int $count */
        $count = StaffProfile::query()->active()->count();

        return (string) $count;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('staff_code')
                ->label(__('staff::filament.profile.fields.staff_code'))
                ->required()
                ->maxLength(32)
                ->unique(ignoreRecord: true),

            Select::make('employment_type')
                ->label(__('staff::filament.profile.fields.employment_type'))
                ->options(collect(EmploymentType::cases())->mapWithKeys(
                    fn (EmploymentType $type): array => [$type->value => $type->label()],
                )->all())
                ->required(),

            Select::make('gender')
                ->label(__('staff::filament.profile.fields.gender'))
                ->options(self::genderOptions())
                ->required(),

            Select::make('country_id')
                ->label(__('staff::filament.profile.fields.country'))
                ->options(fn (): array => self::countryOptions())
                ->searchable()
                ->preload()
                ->live()
                ->required(),

            Select::make('region_id')
                ->label(__('staff::filament.profile.fields.region'))
                ->options(function (callable $get): array {
                    $countryId = $get('country_id');

                    return is_string($countryId) ? self::regionOptions($countryId) : [];
                })
                ->searchable()
                ->preload()
                ->required(),

            DatePicker::make('date_of_birth')
                ->label(__('staff::filament.profile.fields.date_of_birth'))
                ->maxDate(now()),

            TextInput::make('phone')
                ->label(__('staff::filament.profile.fields.phone'))
                ->tel()
                ->maxLength(32),

            DatePicker::make('hired_at')
                ->label(__('staff::filament.profile.fields.hired_at'))
                ->maxDate(now()),

            TagsInput::make('specializations')
                ->label(__('staff::filament.profile.fields.specializations')),

            KeyValue::make('bio')
                ->label(__('staff::filament.profile.fields.bio')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('staff_code')
                    ->label(__('staff::filament.profile.fields.staff_code'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employment_type')
                    ->label(__('staff::filament.profile.fields.employment_type'))
                    ->badge()
                    ->formatStateUsing(fn (EmploymentType $state): string => $state->label())
                    ->color(fn (EmploymentType $state): string => $state->color()),
                TextColumn::make('hired_at')
                    ->label(__('staff::filament.profile.fields.hired_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('terminated_at')
                    ->label(__('staff::filament.profile.fields.terminated_at'))
                    ->date()
                    ->placeholder(__('staff::filament.common.active')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('staff::filament.profile.filters.active'))
                    ->queries(
                        true: fn ($query) => $query->whereNull('terminated_at'),
                        false: fn ($query) => $query->whereNotNull('terminated_at'),
                    ),
                SelectFilter::make('employment_type')
                    ->label(__('staff::filament.profile.fields.employment_type'))
                    ->options(collect(EmploymentType::cases())->mapWithKeys(
                        fn (EmploymentType $type): array => [$type->value => $type->label()],
                    )->all()),
                SelectFilter::make('gender')
                    ->label(__('staff::filament.profile.fields.gender'))
                    ->options(self::genderOptions()),
                SelectFilter::make('country_id')
                    ->label(__('staff::filament.profile.filters.country'))
                    ->options(fn (): array => self::countryOptions())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('region_id')
                    ->label(__('staff::filament.profile.filters.region'))
                    ->options(fn (): array => self::regionOptions())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // الإجراءات عبر صفحة الإدارة الموحّدة.
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => StaffProfileResource\Pages\ListStaffProfiles::route('/'),
            'create' => StaffProfileResource\Pages\CreateStaffProfile::route('/create'),
            'view' => StaffProfileResource\Pages\ViewStaffProfile::route('/{record}'),
            'edit' => StaffProfileResource\Pages\EditStaffProfile::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function genderOptions(): array
    {
        $options = [];

        foreach (StaffGender::cases() as $gender) {
            $options[$gender->value] = __('staff::filament.profile.gender_options.'.$gender->value);
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function countryOptions(): array
    {
        $options = [];

        foreach (self::geography()->countries() as $country) {
            $options[$country->id] = self::localizedName($country->name);
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function regionOptions(?string $countryId = null): array
    {
        $geography = self::geography();
        $options = [];

        if ($countryId !== null) {
            foreach ($geography->regionsOf($countryId) as $region) {
                $options[$region->id] = self::localizedName($region->name);
            }

            return $options;
        }

        foreach ($geography->countries() as $country) {
            $countryName = self::localizedName($country->name);

            foreach ($geography->regionsOf($country->id) as $region) {
                $options[$region->id] = $countryName.' — '.self::localizedName($region->name);
            }
        }

        return $options;
    }

    /** @param array<string, string> $names */
    private static function localizedName(array $names): string
    {
        $locale = app()->getLocale();

        return $names[$locale] ?? $names['ar'] ?? $names['en'] ?? (string) reset($names);
    }

    private static function geography(): GeographyQueries
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        return $geography;
    }
}
