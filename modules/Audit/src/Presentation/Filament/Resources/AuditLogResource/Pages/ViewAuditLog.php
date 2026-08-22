<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Filament\Resources\AuditLogResource\Pages;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Audit\Presentation\Filament\Resources\AuditLogResource;

/**
 * عرض قيدة واحدة — كل الحقول للقراءة فقط، لا تحرير أبدًا.
 */
final class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function getTitle(): string
    {
        return __('audit::labels.audit_log.view_title');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('audit::labels.sections.context'))
                ->schema([
                    TextEntry::make('action')
                        ->label(__('audit::labels.fields.action'))
                        ->badge(),
                    TextEntry::make('actor_type')
                        ->label(__('audit::labels.fields.actor_type'))
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => $state !== null
                            ? __('audit::labels.actor_types.'.$state)
                            : '—'),
                    TextEntry::make('actor_id')
                        ->label(__('audit::labels.fields.actor'))
                        ->copyable(),
                    TextEntry::make('acting_for_user_id')
                        ->label(__('audit::labels.fields.acting_for'))
                        ->copyable()
                        ->placeholder('—'),
                ])->columns(2),
            Section::make(__('audit::labels.sections.subject'))
                ->schema([
                    TextEntry::make('auditable_type')
                        ->label(__('audit::labels.fields.auditable_type')),
                    TextEntry::make('auditable_id')
                        ->label(__('audit::labels.fields.auditable_id'))
                        ->copyable()
                        ->placeholder('—'),
                ])->columns(2),
            Section::make(__('audit::labels.sections.changes'))
                ->schema([
                    KeyValueEntry::make('old_values')
                        ->label(__('audit::labels.fields.old_values')),
                    KeyValueEntry::make('new_values')
                        ->label(__('audit::labels.fields.new_values')),
                    TextEntry::make('reason')
                        ->label(__('audit::labels.fields.reason'))
                        ->placeholder('—'),
                ])->columns(2),
            Section::make(__('audit::labels.sections.metadata'))
                ->schema([
                    TextEntry::make('ip_address')
                        ->label(__('audit::labels.fields.ip_address')),
                    TextEntry::make('correlation_id')
                        ->label(__('audit::labels.fields.correlation_id'))
                        ->copyable(),
                    TextEntry::make('created_at')
                        ->label(__('audit::labels.fields.created_at'))
                        ->dateTime(),
                ])->columns(3),
        ]);
    }
}
