<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\ValueObjects\Money;

/**
 * مورد فترات المستحقات في لوحة الإدارة.
 *
 * الفترة تُدار عبر دورة حياتها (احتساب → مراجعة → اعتماد → صرف → إقفال)،
 * ولا يُسمح بإنشائها أو حذفها يدويًا.
 */
final class PayrollPeriodResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = PayrollPeriod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return __('payroll::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::navigation.period.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::navigation.period.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('payroll::fields.period'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('year')
                            ->label(__('payroll::fields.year'))
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2100)
                            ->required(),
                        Select::make('month')
                            ->label(__('payroll::fields.month'))
                            ->options(array_combine(range(1, 12), array_map(
                                fn (int $m): string => __('payroll::months.'.str_pad((string) $m, 2, '0', STR_PAD_LEFT)),
                                range(1, 12),
                            )))
                            ->required(),
                        DatePicker::make('starts_on')
                            ->label(__('payroll::fields.starts_on')),
                        DatePicker::make('ends_on')
                            ->label(__('payroll::fields.ends_on')),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('payroll::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('year')
                    ->label(__('payroll::fields.year'))
                    ->sortable(),
                TextColumn::make('month')
                    ->label(__('payroll::fields.month'))
                    ->formatStateUsing(fn (int $state): string => __('payroll::months.'.str_pad((string) $state, 2, '0', STR_PAD_LEFT)))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('payroll::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PayrollPeriodStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => match (true) {
                        $state === PayrollPeriodStatus::Paid,
                        $state === PayrollPeriodStatus::Locked => 'success',
                        $state === PayrollPeriodStatus::Approved => 'emerald',
                        $state === PayrollPeriodStatus::UnderReview => 'amber',
                        default => 'gray',
                    }),
                TextColumn::make('totals')
                    ->label(__('payroll::fields.totals'))
                    ->formatStateUsing(function ($state): string {
                        if (!is_array($state)) {
                            return '—';
                        }

                        $net = $state['net_minor_units'] ?? null;

                        return is_int($net)
                            ? Money::of($net, (string) config('payroll.currency'))->toMajor()
                            : '—';
                    })
                    ->alignEnd(),
                TextColumn::make('calculated_at')
                    ->label(__('payroll::fields.calculated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->label(__('payroll::fields.paid_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('payroll::fields.status'))
                    ->options(collect(PayrollPeriodStatus::cases())
                        ->mapWithKeys(fn (PayrollPeriodStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->recordActions([
                Action::make('lock')
                    ->label(__('payroll::actions_ui.lock'))
                    ->requiresConfirmation()
                    ->visible(fn (PayrollPeriod $record): bool => Gate::forUser(auth()->user())->allows('lock', $record))
                    ->action(fn () => null),
            ]);
    }
}
