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
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Staff\Domain\Contracts\StaffQueries;
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
                    // اسم المعلم بدل ULID من ٢٦ خانة. الأسماء تُجلب دفعة واحدة
                    // لكل صفحة عبر عقد Staff العام تفاديًا لـ N+1.
                    ->formatStateUsing(fn (?string $state): string => self::teacherName($state))
                    ->searchable(),

                TextColumn::make('entry_type')
                    ->label(__('payroll::filament.entry.type'))
                    ->formatStateUsing(fn (?string $state): string => self::translated('payroll::filament.entry_type', $state))
                    ->badge(),

                TextColumn::make('outcome_key')
                    ->label(__('payroll::filament.entry.outcome'))
                    ->formatStateUsing(fn (?string $state): string => self::translated('payroll::outcomes', $state))
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
                    ->formatStateUsing(fn (PayrollEntryStatus|string|null $state): string => $state instanceof PayrollEntryStatus
                        ? $state->label()
                        : (PayrollEntryStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->badge()
                    ->color(fn (PayrollEntryStatus|string|null $state): string => match (
                        $state instanceof PayrollEntryStatus ? $state : PayrollEntryStatus::tryFrom((string) $state)
                    ) {
                        PayrollEntryStatus::Released => 'success',
                        PayrollEntryStatus::Deferred => 'warning',
                        default => 'gray',
                    }),

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

    /**
     * ترجمة مفتاح مخزَّن؛ المفتاح نفسه هو البديل حين تنقص الترجمة، فلا يظهر
     * صف فارغ لقيمة موجودة في قاعدة البيانات.
     */
    private static function translated(string $group, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        $translation = __("{$group}.{$key}");

        return is_string($translation) && $translation !== "{$group}.{$key}"
            ? $translation
            : $key;
    }

    /**
     * أسماء المعلمين، مُحمَّلة مرة واحدة لكل طلب — لا استعلام لكل صف.
     */
    private static function teacherName(?string $staffProfileId): string
    {
        if ($staffProfileId === null || $staffProfileId === '') {
            return '—';
        }

        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $staffProfileId;
        }

        if (self::$teacherNames === null) {
            /** @var StaffQueries $staff */
            $staff = app(StaffQueries::class);
            self::$teacherNames = $staff->namesForProfiles(
                $organizationId,
                $staff->profileIdsForOrganization($organizationId),
            );
        }

        return self::$teacherNames[$staffProfileId] ?? $staffProfileId;
    }

    /** @var array<string, string>|null */
    private static ?array $teacherNames = null;
}
