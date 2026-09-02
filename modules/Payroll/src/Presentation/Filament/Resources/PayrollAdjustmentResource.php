<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Payroll\Application\Actions\ApprovePayrollAdjustmentAction;
use Modules\Payroll\Application\Actions\RejectPayrollAdjustmentAction;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * التسويات المالية — القناة الوحيدة لتصحيح دفتر المستحقات.
 *
 * `payroll_entries` دفتر append-only بحكم البند 4: القيدة لا تُعدَّل ولا تُحذف،
 * والتصحيح يكون بقيدة تسوية جديدة. كانت الإجراءات الثلاثة (اقتراح · اعتماد ·
 * رفض) مكتوبة ولها سياسة كاملة، **بلا أي مورد يعرضها** — فلم يكن للتصحيح
 * المالي مسارٌ في اللوحة إطلاقًا، وهو ما يدفع المستخدم إلى تعديل القاعدة يدويًا
 * وهو بالضبط ما يمنعه العقد.
 *
 * فصل الصلاحيتين مقصود: من يقترح لا يعتمد. السياسة تفرضه، والأزرار تعكسه.
 */
final class PayrollAdjustmentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = PayrollAdjustment::class;

    protected static ?string $slug = 'payroll-adjustments';

    protected static ?int $navigationSort = 522;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): ?string
    {
        return __('payroll::navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll::filament.adjustments.plural');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::filament.adjustments.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::filament.adjustments.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('payroll_period_id')
                ->label(__('payroll::filament.adjustments.period'))
                ->options(fn (): array => PayrollPeriod::query()
                    ->forOrganization((string) session('organization_id'))
                    ->orderByDesc('year')
                    ->orderByDesc('month')
                    ->limit(24)
                    ->get()
                    ->mapWithKeys(fn (PayrollPeriod $p): array => [
                        (string) $p->getKey() => $p->year.'/'.str_pad((string) $p->month, 2, '0', STR_PAD_LEFT),
                    ])
                    ->all())
                ->required(),

            Select::make('staff_profile_id')
                ->label(__('payroll::filament.adjustments.staff'))
                ->options(fn (): array => self::staffOptions())
                ->searchable()
                ->required(),

            Select::make('type')
                ->label(__('payroll::filament.adjustments.type'))
                ->options(self::typeOptions())
                ->required(),

            TextInput::make('amount')
                ->label(__('payroll::filament.adjustments.amount'))
                ->helperText(__('payroll::filament.adjustments.amount_hint'))
                ->numeric()
                ->required(),

            Textarea::make('reason')
                ->label(__('payroll::filament.adjustments.reason'))
                ->required()
                ->minLength(3)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('staff_profile_id')
                    ->label(__('payroll::filament.adjustments.staff'))
                    ->formatStateUsing(fn (string $state): string => self::staffName($state))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('payroll::filament.adjustments.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state] ?? $state),

                TextColumn::make('amount')
                    ->label(__('payroll::filament.adjustments.amount'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label(__('payroll::filament.adjustments.reason'))
                    ->limit(60)
                    ->toggleable(),

                TextColumn::make('status_label')
                    ->label(__('payroll::filament.adjustments.status'))
                    ->badge()
                    ->state(fn (PayrollAdjustment $record): string => self::statusLabel($record))
                    ->color(fn (PayrollAdjustment $record): string => match (true) {
                        $record->approved_at !== null => 'success',
                        $record->rejected_at !== null => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('proposed_at')
                    ->label(__('payroll::filament.adjustments.proposed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('payroll::filament.adjustments.type'))
                    ->options(self::typeOptions()),
            ])
            ->recordActions([
                self::decisionAction('approve', 'heroicon-m-check', 'success'),
                self::decisionAction('reject', 'heroicon-m-x-mark', 'danger'),
            ])
            ->defaultSort('proposed_at', 'desc');
    }

    /**
     * الاعتماد والرفض يتشاركان الشكل: كلاهما قرار بسبب مكتوب على تسوية معلّقة،
     * والسياسة `approve`/`reject` هي التي تمنع المُقترِح من اعتماد اقتراحه.
     */
    private static function decisionAction(string $name, string $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__('payroll::filament.adjustments.'.$name))
            ->icon($icon)
            ->color($color)
            ->authorize($name)
            ->form([
                Textarea::make('reason')
                    ->label(__('payroll::filament.adjustments.decision_reason'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(1000),
            ])
            ->action(function (PayrollAdjustment $record, array $data) use ($name): void {
                $action = $name === 'approve'
                    ? app(ApprovePayrollAdjustmentAction::class)
                    : app(RejectPayrollAdjustmentAction::class);

                $action->execute(
                    (string) $record->organization_id,
                    (string) $record->getKey(),
                    (string) auth()->id(),
                    (string) $data['reason'],
                );

                Notification::make()
                    ->title(__('payroll::filament.adjustments.'.$name.'_done'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        /** @var list<string> $types */
        $types = (array) config('payroll.adjustments.types', []);

        $options = [];
        foreach ($types as $type) {
            $options[$type] = (string) __('payroll::filament.adjustments.types.'.$type);
        }

        return $options;
    }

    private static function statusLabel(PayrollAdjustment $record): string
    {
        return match (true) {
            $record->approved_at !== null => (string) __('payroll::filament.adjustments.approved'),
            $record->rejected_at !== null => (string) __('payroll::filament.adjustments.rejected'),
            default => (string) __('payroll::filament.adjustments.pending'),
        };
    }

    /**
     * أسماء موظفي المؤسسة عبر عقد Staff المعلن — لا استيراد لنماذجه.
     *
     * @return array<string, string>
     */
    private static function staffOptions(): array
    {
        $organizationId = (string) session('organization_id');
        $staff = app(StaffQueries::class);

        return $staff->namesForProfiles(
            $organizationId,
            $staff->profileIdsForOrganization($organizationId),
        );
    }

    private static function staffName(string $staffProfileId): string
    {
        $names = app(StaffQueries::class)->namesForProfiles(
            (string) session('organization_id'),
            [$staffProfileId],
        );

        return $names[$staffProfileId] ?? $staffProfileId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => PayrollAdjustmentResource\Pages\ListPayrollAdjustments::route('/'),
            'create' => PayrollAdjustmentResource\Pages\CreatePayrollAdjustment::route('/create'),
        ];
    }
}
