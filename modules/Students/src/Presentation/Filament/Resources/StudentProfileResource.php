<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
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

    protected static \UnitEnum|string|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return __('students::filament.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('students::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('students::filament.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::filament.plural_model_label');
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
                    ->required()
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

                TextInput::make('country')
                    ->label(__('students::attributes.country'))
                    ->length(2)
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

                TextColumn::make('user.name')
                    ->label(__('students::filament.student_name'))
                    ->searchable(),

                TextColumn::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?StudentGender $state): ?string => $state?->label())
                    ->sortable(),

                TextColumn::make('city')
                    ->label(__('students::attributes.city'))
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
}
