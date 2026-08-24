<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * قيود المستحقات — **للعرض فقط**.
 *
 * دفتر الأستاذ لا يُعدَّل: لا نموذج تحرير، ولا إنشاء، ولا حذف.
 * التصحيح يتم بقيدة تسوية جديدة عبر PayrollAdjustment.
 */
final class PayrollEntryResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = PayrollEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return __('payroll::navigation.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('payroll.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('payroll::filament.entry.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::filament.entry.plural');
    }

    public static function form(Schema $schema): Schema
    {
        // لا حقول قابلة للتحرير — القيدة غير قابلة للتعديل بحكم التصميم.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payroll::filament.entry.created_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('staff_profile_id')
                    ->label(__('payroll::filament.entry.staff'))
                    ->searchable(),

                TextColumn::make('entry_type')
                    ->label(__('payroll::filament.entry.type'))
                    ->badge(),

                TextColumn::make('outcome_key')
                    ->label(__('payroll::filament.entry.outcome'))
                    ->toggleable(),

                TextColumn::make('amount')
                    // المبالغ مخزَّنة بالوحدة الصغرى (القروش) — تُعرض مقسومة على 100.
                    ->label(__('payroll::filament.entry.amount'))
                    ->formatStateUsing(fn (?int $state, PayrollEntry $record): string => number_format(
                        (int) $state / 100,
                        2,
                    ).' '.$record->currency)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('payroll::filament.entry.status'))
                    ->badge(),

                TextColumn::make('session_id')
                    ->label(__('payroll::filament.entry.session'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->label(__('payroll::filament.entry.type'))
                    ->options([
                        'session_earning' => __('payroll::filament.entry_type.session_earning'),
                        'monthly_base' => __('payroll::filament.entry_type.monthly_base'),
                        'deduction' => __('payroll::filament.entry_type.deduction'),
                        'deferred' => __('payroll::filament.entry_type.deferred'),
                        'adjustment' => __('payroll::filament.entry_type.adjustment'),
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => PayrollEntryResource\Pages\ListPayrollEntries::route('/'),
        ];
    }
}
