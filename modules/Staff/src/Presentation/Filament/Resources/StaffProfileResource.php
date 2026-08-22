<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages\ManageStaffProfiles;

final class StaffProfileResource extends Resource
{
    protected static ?string $model = StaffProfile::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function getModelLabel(): string
    {
        return __('staff::filament.profile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff::filament.profile.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('staff::filament.navigation_group');
    }

    public static function getNavigationBadge(): ?string
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
            ])
            ->actions([
                // الإجراءات عبر صفحة الإدارة الموحّدة.
            ])
            ->bulkActions([]);
    }

    /**
     * @return array<string, array<class-string>>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageStaffProfiles::route('/'),
        ];
    }
}
