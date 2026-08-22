<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource\Pages;

/**
 * مورد طلبات إعادة التفعيل — مراجعة إدارية بلا نصوص مكتوبة مباشرة.
 */
final class ReactivationRequestFilamentResource extends Resource
{
    protected static ?string $model = ReactivationRequest::class;

    protected static ?string $slug = 'discipline-reactivations';

    protected static \UnitEnum|string|null $navigationGroup = 'الانضباط';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('discipline::filament.reactivations.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('discipline::filament.reactivations.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discipline::filament.reactivations.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false; // التقديم يمر حصرًا عبر RequestReactivationAction.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('enrollment_id')
                ->label(__('discipline::attributes.enrollment_id'))
                ->disabled()
                ->dehydrated(false),

            TextInput::make('attempt_number')
                ->label(__('discipline::filament.attempt_number'))
                ->numeric()
                ->disabled()
                ->dehydrated(false),

            Textarea::make('student_statement')
                ->label(__('discipline::attributes.student_statement'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),

            Textarea::make('decision_note')
                ->label(__('discipline::attributes.decision_note'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enrollment_id')
                    ->label(__('discipline::attributes.enrollment_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('discipline::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (?ReactivationStatus $state): ?string => $state?->label())
                    ->color(fn (?ReactivationStatus $state): string => $state?->color() ?? 'gray')
                    ->sortable(),

                TextColumn::make('attempt_number')
                    ->label(__('discipline::filament.attempt_number'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('discipline::filament.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reviewed_at')
                    ->label(__('discipline::filament.reviewed_at'))
                    ->dateTime()
                    ->placeholder(__('discipline::filament.not_reviewed'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('discipline::attributes.status'))
                    ->options(collect(ReactivationStatus::cases())
                        ->mapWithKeys(fn (ReactivationStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReactivationRequests::route('/'),
            'view' => Pages\ViewReactivationRequest::route('/{record}'),
        ];
    }
}
