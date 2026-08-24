<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\PostponeSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;

final class ViewSession extends ViewRecord
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reschedule')
                ->label(__('sessions::actions.reschedule'))
                ->icon('heroicon-m-calendar-days')
                ->authorize('postpone')
                ->visible(fn (Session $record): bool => $record->status->canTransitionTo(SessionStatus::Postponed))
                ->form([
                    DateTimePicker::make('makeup_start')
                        ->label(__('sessions::fields.makeup_start'))
                        ->required(),
                    DateTimePicker::make('makeup_end')
                        ->label(__('sessions::fields.makeup_end'))
                        ->required(),
                    Textarea::make('reason')
                        ->label(__('sessions::fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (Session $record, array $data): void {
                    app(PostponeSessionAction::class)->execute(
                        $record,
                        (string) $data['makeup_start'],
                        (string) $data['makeup_end'],
                        (string) $data['reason'],
                        auth()->id() === null ? null : (string) auth()->id(),
                    );

                    Notification::make()->title(__('sessions::messages.rescheduled'))->success()->send();
                }),
            Action::make('cancel')
                ->label(__('sessions::actions.cancel'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->authorize('cancel')
                ->visible(fn (Session $record): bool => $record->status->canTransitionTo(SessionStatus::CancelledBySchool))
                ->form([
                    Textarea::make('reason')
                        ->label(__('sessions::fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (Session $record, array $data): void {
                    app(CancelSessionAction::class)->execute(
                        $record,
                        SessionStatus::CancelledBySchool,
                        (string) $data['reason'],
                        auth()->id() === null ? null : (string) auth()->id(),
                    );

                    Notification::make()->title(__('sessions::messages.cancelled'))->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('sessions::fields.details'))->schema([
                TextEntry::make('id')->label(__('sessions::fields.id'))->copyable(),
                TextEntry::make('title')->label(__('sessions::fields.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? (string) ($state[app()->getLocale()] ?? reset($state) ?: '') : (string) $state),
                TextEntry::make('course_id')->label(__('sessions::fields.course')),
                TextEntry::make('group_id')->label(__('sessions::fields.group')),
                TextEntry::make('scheduled_start')->label(__('sessions::fields.scheduled_start'))->dateTime(),
                TextEntry::make('scheduled_end')->label(__('sessions::fields.scheduled_end'))->dateTime(),
                TextEntry::make('actual_start')->label(__('sessions::fields.actual_start'))->dateTime(),
                TextEntry::make('actual_end')->label(__('sessions::fields.actual_end'))->dateTime(),
                TextEntry::make('status')->label(__('sessions::fields.status'))->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SessionStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof SessionStatus ? $state->color() : 'gray'),
            ])->columns(3),
            Section::make(__('sessions::fields.teachers'))->schema([
                TextEntry::make('original_teacher_id')->label(__('sessions::fields.original_teacher')),
                TextEntry::make('staff_profile_id')->label(__('sessions::fields.staff_profile')),
                TextEntry::make('substitute_for_staff_id')->label(__('sessions::fields.substitute_teacher'))
                    ->placeholder(__('sessions::fields.not_available')),
            ])->columns(3),
            Section::make(__('sessions::fields.participation'))->schema([
                RepeatableEntry::make('participants')->label(__('sessions::fields.participant'))
                    ->getStateUsing(fn (Session $record): array => $record->participants()->orderBy('id')->get()->map(fn ($participant): array => [
                        'student_profile_id' => $participant->student_profile_id,
                        'invited_at' => $participant->invited_at,
                        'first_joined_at' => $participant->first_joined_at,
                        'attended_minutes' => $participant->attended_minutes,
                    ])->all())
                    ->schema([
                        TextEntry::make('student_profile_id')->label(__('sessions::fields.student_profile')),
                        TextEntry::make('invited_at')->label(__('sessions::fields.invited_at'))->dateTime(),
                        TextEntry::make('first_joined_at')->label(__('sessions::fields.first_joined_at'))->dateTime(),
                        TextEntry::make('attended_minutes')->label(__('sessions::fields.attended_minutes')),
                    ])->columns(4),
            ]),
            Section::make(__('sessions::fields.status_history'))->schema([
                RepeatableEntry::make('status_history')->label(__('sessions::fields.status_history'))
                    ->getStateUsing(fn (Session $record): array => $record->statusHistory()->orderByDesc('changed_at')->get()->map(fn ($history): array => [
                        'from_status' => $history->from_status,
                        'to_status' => $history->to_status,
                        'reason' => $history->reason,
                        'changed_at' => $history->changed_at,
                    ])->all())
                    ->schema([
                        TextEntry::make('from_status')->label(__('sessions::fields.from_status')),
                        TextEntry::make('to_status')->label(__('sessions::fields.to_status')),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        TextEntry::make('changed_at')->label(__('sessions::fields.changed_at'))->dateTime(),
                    ])->columns(4),
            ]),
            Section::make(__('sessions::fields.substitution_history'))->schema([
                RepeatableEntry::make('substitutions')->label(__('sessions::fields.substitution_history'))
                    ->getStateUsing(fn (Session $record): array => DB::table('session_substitutions')
                        ->where('session_id', (string) $record->getKey())
                        ->orderByDesc('assigned_at')
                        ->get(['original_teacher_id', 'substitute_teacher_id', 'reason', 'is_override', 'override_reason', 'assigned_at'])
                        ->map(fn ($substitution): array => [
                            'original_teacher_id' => $substitution->original_teacher_id,
                            'substitute_teacher_id' => $substitution->substitute_teacher_id,
                            'reason' => $substitution->reason,
                            'is_override' => $substitution->is_override,
                            'override_reason' => $substitution->override_reason,
                            'assigned_at' => $substitution->assigned_at,
                        ])->all())
                    ->schema([
                        TextEntry::make('original_teacher_id')->label(__('sessions::fields.original_teacher')),
                        TextEntry::make('substitute_teacher_id')->label(__('sessions::fields.substitute_teacher')),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        TextEntry::make('is_override')->label(__('sessions::fields.is_override'))->boolean(),
                        TextEntry::make('override_reason')->label(__('sessions::fields.override_reason')),
                        TextEntry::make('assigned_at')->label(__('sessions::fields.assigned_at'))->dateTime(),
                    ])->columns(3),
            ]),
        ]);
    }
}
