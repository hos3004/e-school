<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\ValueObjects\Money;

/**
 * مورد لوحات المعلمين في لوحة الإدارة — قراءة وتصحيح موثّق فقط.
 */
final class TeacherDashboardResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = TeacherDashboard::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string
    {
        return __('reporting::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('reporting::navigation.teacher.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reporting::navigation.teacher.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('reporting::sections.counters'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('sessions_total')
                            ->label(__('reporting::fields.sessions_total'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('sessions_completed')
                            ->label(__('reporting::fields.sessions_completed'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('cancellations_by_self')
                            ->label(__('reporting::fields.cancellations_by_self'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('postponements')
                            ->label(__('reporting::fields.postponements'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('reporting::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('staff_profile_id')
                    ->label(__('reporting::fields.staff'))
                    // كان ULID خامًا — يُعرض اسم المعلم عبر عقد Staff المعلن.
                    ->formatStateUsing(static fn ($state): string => self::teacherNames()[(string) $state]
                        ?? (string) $state),
                TextColumn::make('sessions_total')
                    ->label(__('reporting::fields.sessions_total'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('sessions_completed')
                    ->label(__('reporting::fields.sessions_completed'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('cancellations_by_self')
                    ->label(__('reporting::fields.cancellations_by_self'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('postponements')
                    ->label(__('reporting::fields.postponements'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('payout_minor')
                    ->label(__('reporting::fields.payout'))
                    ->formatStateUsing(fn ($record): string => Money::of(
                        (int) $record->payout_minor,
                        (string) $record->currency,
                    )->toMajor().' '.(string) $record->currency)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('last_session_at')
                    ->label(__('reporting::fields.last_session_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('staff_profile_id')
                    ->label(__('reporting::fields.staff'))
                    ->options(fn (): array => self::teacherNames())
                    ->searchable(),
            ])
            ->defaultSort('sessions_completed', direction: 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => TeacherDashboardResource\Pages\ListTeacherDashboards::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function teacherNames(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return app(StaffQueries::class)->namesForProfiles(
            $organizationId,
            TeacherDashboard::query()
                ->forOrganization($organizationId)
                ->pluck('staff_profile_id')
                ->all(),
        );
    }
}
