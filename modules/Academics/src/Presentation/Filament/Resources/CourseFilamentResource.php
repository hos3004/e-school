<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Academics\Domain\Models\Course;

final class CourseFilamentResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('academics.courses.view_any') || $user->can('academics.courses.view'));
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.course.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.course.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('academics::filament.group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('level_id')
                    ->label(__('academics::filament.course.fields.level'))
                    ->relationship('level', 'code')
                    ->required(),

                TextInput::make('organization_id')
                    ->label(__('academics::filament.course.fields.organization'))
                    ->length(26)
                    ->required(),

                TextInput::make('code')
                    ->label(__('academics::filament.course.fields.code'))
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),

                TextInput::make('name.ar')
                    ->label(__('academics::filament.course.fields.name_ar'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name.en')
                    ->label(__('academics::filament.course.fields.name_en'))
                    ->maxLength(255),

                TextInput::make('total_sessions')
                    ->label(__('academics::filament.course.fields.total_sessions'))
                    ->numeric()
                    ->minValue(1)
                    ->nullable(),

                KeyValue::make('completion_rules')
                    ->label(__('academics::filament.course.fields.completion_rules'))
                    ->nullable(),

                Toggle::make('is_active')
                    ->label(__('academics::filament.course.fields.is_active'))
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('academics::filament.course.fields.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('academics::filament.course.fields.name'))
                    ->formatStateUsing(fn ($state): string => (string) ($state['ar'] ?? $state['en'] ?? ''))
                    ->searchable(),

                TextColumn::make('level.code')
                    ->label(__('academics::filament.course.fields.level'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('total_sessions')
                    ->label(__('academics::filament.course.fields.total_sessions'))
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('academics::filament.course.fields.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('academics::filament.fields.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('academics::filament.course.filters.active')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Course $record): bool => auth()->user()?->can('update', $record) === true),
                DeleteAction::make()
                    ->visible(fn (Course $record): bool => auth()->user()?->can('delete', $record) === true),
            ]);
    }
}
