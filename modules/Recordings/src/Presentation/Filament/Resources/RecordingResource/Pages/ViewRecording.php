<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Filament\Resources\RecordingResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Recordings\Application\Actions\DeleteRecordingAction;
use Modules\Recordings\Application\Actions\GrantRecordingAccessAction;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Modules\Recordings\Domain\Models\RecordingViewLog;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;

/**
 * صفحة عرض التسجيل مع تفاصيل العرض ومنح الوصول وسجل المشاهدات.
 */
final class ViewRecording extends ViewRecord
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grant_access')
                ->label(__('recordings::actions.grant_access'))
                ->icon('heroicon-m-key')
                ->color('primary')
                ->form([
                    TextInput::make('granted_to_user_id')
                        ->label(__('recordings::fields.granted_to_user'))
                        ->required(),
                    DateTimePicker::make('expires_at')
                        ->label(__('recordings::fields.expires_at'))
                        ->default(now()->addDays(7))
                        ->required(),
                    TextInput::make('reason')
                        ->label(__('recordings::fields.grant_reason'))
                        ->required(),
                ])
                ->action(function (Recording $record, array $data): void {
                    app(GrantRecordingAccessAction::class)->execute(
                        $record,
                        (string) auth()->id(),
                        grantedToUserId: (string) $data['granted_to_user_id'],
                        expiresAt: CarbonImmutable::parse($data['expires_at']),
                        reason: (string) $data['reason'],
                    );

                    Notification::make()
                        ->title(__('recordings::messages.access_granted'))
                        ->success()
                        ->send();
                }),

            Action::make('delete')
                ->label(__('recordings::actions.delete'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->authorize('delete')
                ->form([
                    TextInput::make('reason')
                        ->label(__('recordings::fields.deletion_reason'))
                        ->required(),
                ])
                ->action(function (Recording $record, array $data): void {
                    app(DeleteRecordingAction::class)->execute(
                        $record,
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );

                    Notification::make()
                        ->title(__('recordings::messages.deleted'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('recordings::fields.recording_info'))
                ->schema([
                    TextEntry::make('id')
                        ->label(__('recordings::fields.id'))
                        ->copyable(),
                    TextEntry::make('session_id')
                        ->label(__('recordings::fields.session'))
                        ->copyable(),
                    TextEntry::make('provider')
                        ->label(__('recordings::fields.provider'))
                        ->badge(),
                    TextEntry::make('status')
                        ->label(__('recordings::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof RecordingStatus
                            ? $state->label()
                            : (string) $state)
                        ->color(fn ($state): string => $state instanceof RecordingStatus
                            ? $state->color()
                            : 'gray'),
                    TextEntry::make('duration_seconds')
                        ->label(__('recordings::fields.duration'))
                        ->formatStateUsing(fn ($state): string => $state === null
                            ? __('recordings::fields.duration')
                            : __('recordings::messages.duration_minutes', ['minutes' => (int) ceil($state / 60)])),
                    TextEntry::make('available_from')
                        ->label(__('recordings::fields.available_from'))
                        ->dateTime(),
                    TextEntry::make('expires_at')
                        ->label(__('recordings::fields.expires_at'))
                        ->dateTime(),
                ])->columns(2),

            Section::make(__('recordings::fields.active_grants'))
                ->schema([
                    RepeatableEntry::make('access_grants')
                        ->label(__('recordings::fields.active_grants'))
                        ->getStateUsing(fn (Recording $record) => RecordingAccessGrant::query()
                            ->where('recording_id', $record->id)
                            ->get())
                        ->schema([
                            TextEntry::make('granted_to_user_id')
                                ->label(__('recordings::fields.user_id')),
                            TextEntry::make('granted_by_user_id')
                                ->label(__('recordings::fields.granted_by')),
                            TextEntry::make('expires_at')
                                ->label(__('recordings::fields.expires_at'))
                                ->dateTime(),
                            TextEntry::make('reason')
                                ->label(__('recordings::fields.reason')),
                        ])->columns(4),
                ]),

            Section::make(__('recordings::fields.view_logs'))
                ->schema([
                    RepeatableEntry::make('view_logs')
                        ->label(__('recordings::fields.view_logs'))
                        ->getStateUsing(fn (Recording $record) => RecordingViewLog::query()
                            ->where('recording_id', $record->id)
                            ->orderByDesc('viewed_at')
                            ->get())
                        ->schema([
                            TextEntry::make('user_id')
                                ->label(__('recordings::fields.user_id')),
                            TextEntry::make('viewed_at')
                                ->label(__('recordings::fields.viewed_at'))
                                ->dateTime(),
                            TextEntry::make('ip_address')
                                ->label(__('recordings::fields.ip_address')),
                        ])->columns(3),
                ]),
        ]);
    }
}
