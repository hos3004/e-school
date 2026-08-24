<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Audit\Domain\Enums\AuditAction;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Presentation\Filament\Resources\AuditLogResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد Filament لقيود التدقيق — قراءة فقط.
 *
 * دفتر التدقيق append-only: النموذج يُعرض للفحص والبحث فقط،
 * ولا توجد صفحات إنشاء أو تعديل أو حذف.
 */
final class AuditLogResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = AuditLog::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getModelLabel(): string
    {
        return __('audit::labels.audit_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit::labels.audit_log.plural');
    }

    /** عرض فقط — لا إنشاء من اللوحة. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('action')
                    ->label(__('audit::labels.fields.action'))
                    ->disabled(),
                Select::make('actor_type')
                    ->label(__('audit::labels.fields.actor_type'))
                    ->options(self::actorTypeOptions())
                    ->disabled(),
                TextInput::make('actor_id')
                    ->label(__('audit::labels.fields.actor'))
                    ->disabled(),
                TextInput::make('auditable_type')
                    ->label(__('audit::labels.fields.auditable_type'))
                    ->disabled(),
                TextInput::make('auditable_id')
                    ->label(__('audit::labels.fields.auditable_id'))
                    ->disabled(),
                KeyValue::make('old_values')
                    ->label(__('audit::labels.fields.old_values'))
                    ->disabled(),
                KeyValue::make('new_values')
                    ->label(__('audit::labels.fields.new_values'))
                    ->disabled(),
                Textarea::make('reason')
                    ->label(__('audit::labels.fields.reason'))
                    ->rows(3)
                    ->disabled(),
                DateTimePicker::make('created_at')
                    ->label(__('audit::labels.fields.created_at'))
                    ->disabled(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('audit::labels.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('action')
                    ->label(__('audit::labels.fields.action'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor_type')
                    ->label(__('audit::labels.fields.actor_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? __('audit::labels.actor_types.'.$state)
                        : '—'),
                TextColumn::make('actor_id')
                    ->label(__('audit::labels.fields.actor'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('auditable_type')
                    ->label(__('audit::labels.fields.auditable_type'))
                    ->searchable(),
                TextColumn::make('auditable_id')
                    ->label(__('audit::labels.fields.auditable_id'))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('reason')
                    ->label(__('audit::labels.fields.reason'))
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('correlation_id')
                    ->label(__('audit::labels.fields.correlation_id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('audit::labels.fields.action'))
                    ->options(self::actionOptions())
                    ->searchable(),
                SelectFilter::make('actor_type')
                    ->label(__('audit::labels.fields.actor_type'))
                    ->options(self::actorTypeOptions()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function actorTypeOptions(): array
    {
        return collect(AuditActorType::cases())
            ->mapWithKeys(fn (AuditActorType $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function actionOptions(): array
    {
        return collect(AuditAction::cases())
            ->mapWithKeys(fn (AuditAction $case): array => [$case->value => $case->label()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
