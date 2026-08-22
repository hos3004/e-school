<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceResource\Pages;

/**
 * مورد Filament لقيود الحضور.
 *
 * القيد يُنشأ برمجيًا من أحداث الفصل — لا إنشاء من اللوحة.
 * التعديل الوحيد المسموح هو الاعتماد أو التجاوز بسبب، وكلاهما يمرّ
 * عبر إجراءات التطبيق (Application\Actions) حفاظًا على قواعد العمل والتدقيق.
 */
final class AttendanceFilamentResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static \UnitEnum|string|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 42;

    public static function getModelLabel(): string
    {
        return __('attendance::filament.attendance.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::filament.attendance.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->can('viewAny', Attendance::class) ?? false);
    }

    /** الرصد يتم من أحداث الفصل فقط — لا إنشاء يدوي من اللوحة. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->label(__('attendance::fields.status'))
                    ->options(self::statusOptions())
                    ->disabled(),
                Select::make('derived_status')
                    ->label(__('attendance::fields.derived_status'))
                    ->options(self::statusOptions())
                    ->disabled(),
                TextInput::make('attended_minutes')
                    ->label(__('attendance::fields.attended_minutes'))
                    ->numeric()
                    ->disabled(),
                TextInput::make('joined_after_minutes')
                    ->label(__('attendance::fields.joined_after_minutes'))
                    ->numeric()
                    ->disabled(),
                TextInput::make('left_before_minutes')
                    ->label(__('attendance::fields.left_before_minutes'))
                    ->numeric()
                    ->disabled(),
                TextInput::make('session_participant_id')
                    ->label(__('attendance::fields.session_participant_id'))
                    ->disabled(),
                Textarea::make('override_reason')
                    ->label(__('attendance::fields.override_reason'))
                    ->rows(3)
                    ->disabled(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_participant_id')
                    ->label(__('attendance::fields.session_participant_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('attendance::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (?AttendanceStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?AttendanceStatus $state): array => match ($state) {
                        AttendanceStatus::Present => Color::Emerald,
                        AttendanceStatus::Late, AttendanceStatus::Partial, AttendanceStatus::LeftEarly => Color::Amber,
                        AttendanceStatus::Excused, AttendanceStatus::TechnicalIssue => Color::Sky,
                        AttendanceStatus::Absent, AttendanceStatus::NoShow => Color::Rose,
                        default => Color::Gray,
                    }),
                TextColumn::make('derived_status')
                    ->label(__('attendance::fields.derived_status'))
                    ->badge()
                    ->formatStateUsing(fn (?AttendanceStatus $state): string => $state?->label() ?? '—')
                    ->color(Color::Gray)
                    ->toggleable(),
                TextColumn::make('attended_minutes')
                    ->label(__('attendance::fields.attended_minutes'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label(__('attendance::fields.confirmed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('attendance::messages.pending_confirmation')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('attendance::fields.status'))
                    ->options(self::statusOptions()),
                TernaryFilter::make('confirmed_at')
                    ->label(__('attendance::filters.confirmed'))
                    ->nullable(),
            ])
            ->actions([
                self::confirmAction(),
                self::overrideAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('attendance::filament.actions.confirm'))
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('attendance::filament.actions.confirm_description'))
            ->visible(fn (Attendance $record): bool => !$record->isConfirmed()
                && (bool) (auth()->user()?->can('confirm', $record) ?? false))
            ->action(function (Attendance $record): void {
                app(ConfirmAttendanceAction::class)->execute(
                    $record,
                    (string) auth()->id(),
                );
            });
    }

    private static function overrideAction(): Action
    {
        return Action::make('override')
            ->label(__('attendance::filament.actions.override'))
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->visible(fn (Attendance $record): bool => (bool) (auth()->user()?->can('override', $record) ?? false))
            ->form([
                Select::make('status')
                    ->label(__('attendance::fields.new_status'))
                    ->options(self::statusOptions())
                    ->required(),
                Textarea::make('reason')
                    ->label(__('attendance::fields.reason'))
                    ->required()
                    ->minLength((int) config('attendance.override.reason_min_chars', 5))
                    ->maxLength((int) config('attendance.override.reason_max_chars', 1000))
                    ->helperText(__('attendance::filament.actions.reason_helper')),
            ])
            ->action(function (array $data, Attendance $record): void {
                app(OverrideAttendanceAction::class)->execute(
                    $record,
                    AttendanceStatus::from((string) $data['status']),
                    (string) $data['reason'],
                );
            });
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $case): array => [$case->value => $case->label()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'view' => Pages\ViewAttendance::route('/{record}'),
        ];
    }
}
