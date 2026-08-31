<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Filament\Resources\AttendanceResource\Pages;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Attendance\Application\Queries\AttendanceOperationsQueryService;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;

final class ViewAttendance extends ViewRecord
{
    protected static string $resource = AttendanceFilamentResource::class;

    /** @var array<string, mixed>|null */
    private ?array $participantContext = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $auditRows = null;

    public function getTitle(): string
    {
        return (string) ($this->context()['student'] ?? __('attendance::filament.pages.view_title'));
    }

    protected function getHeaderActions(): array
    {
        return [
            AttendanceFilamentResource::confirmAction(),
            AttendanceFilamentResource::overrideAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('attendance::filament.hub.attendance_summary'))
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    TextEntry::make('status')
                        ->label(__('attendance::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof AttendanceStatus ? $state->label() : (string) $state),
                    TextEntry::make('derived_status')
                        ->label(__('attendance::fields.derived_status'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof AttendanceStatus ? $state->label() : (string) $state),
                    TextEntry::make('attended_minutes')->label(__('attendance::fields.attended_minutes')),
                    TextEntry::make('joined_after_minutes')->label(__('attendance::fields.joined_after_minutes')),
                    TextEntry::make('left_before_minutes')->label(__('attendance::fields.left_before_minutes')),
                    TextEntry::make('confirmed_at')
                        ->label(__('attendance::fields.confirmed_at'))
                        ->dateTime()
                        ->placeholder(__('attendance::messages.pending_confirmation')),
                    TextEntry::make('override_reason')
                        ->label(__('attendance::fields.override_reason'))
                        ->placeholder(__('attendance::messages.not_available'))
                        ->columnSpanFull(),
                ])->columns(3),

            Tabs::make(__('attendance::filament.hub.title'))
                ->persistTabInQueryString('attendance-hub')
                ->tabs([
                    Tab::make(__('attendance::filament.hub.participant'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextEntry::make('student_label')->label(__('attendance::fields.student'))->state(fn (): string => $this->contextValue('student')),
                            TextEntry::make('session_label')->label(__('attendance::fields.session'))->state(fn (): string => $this->contextValue('session')),
                            TextEntry::make('course_label')->label(__('attendance::fields.course'))->state(fn (): string => $this->contextValue('course')),
                            TextEntry::make('group_label')->label(__('attendance::fields.group'))->state(fn (): string => $this->contextValue('group')),
                            TextEntry::make('teacher_label')->label(__('attendance::fields.teacher'))->state(fn (): string => $this->contextValue('teacher')),
                            TextEntry::make('session_status')->label(__('attendance::fields.session_status'))->state(fn (): string => $this->contextValue('session_status'))->badge(),
                            TextEntry::make('scheduled_start')->label(__('attendance::fields.scheduled_start'))->state(fn (): string => $this->contextValue('scheduled_start'))->dateTime(),
                            TextEntry::make('scheduled_end')->label(__('attendance::fields.scheduled_end'))->state(fn (): string => $this->contextValue('scheduled_end'))->dateTime(),
                            TextEntry::make('first_joined_at')->label(__('attendance::fields.first_joined_at'))->state(fn (): ?string => $this->nullableContextValue('first_joined_at'))->dateTime(),
                            TextEntry::make('last_left_at')->label(__('attendance::fields.last_left_at'))->state(fn (): ?string => $this->nullableContextValue('last_left_at'))->dateTime(),
                            TextEntry::make('classroom_minutes')->label(__('attendance::fields.classroom_minutes'))->state(fn (): string => $this->contextValue('classroom_minutes')),
                        ])->columns(3),
                    Tab::make(__('attendance::filament.hub.audit'))
                        ->icon('heroicon-o-shield-check')
                        ->badge(fn (): int => count($this->audit()))
                        ->schema([
                            RepeatableEntry::make('audit_rows')
                                ->hiddenLabel()
                                ->placeholder(__('attendance::filament.hub.empty'))
                                ->getStateUsing(fn (): array => $this->audit())
                                ->schema([
                                    TextEntry::make('action')
                                        ->label(__('attendance::fields.action'))
                                        ->formatStateUsing(fn (mixed $state): string => __('attendance::audit_actions.'.(string) $state)),
                                    TextEntry::make('reason')->label(__('attendance::fields.reason')),
                                    TextEntry::make('actor')->label(__('attendance::fields.actor')),
                                    TextEntry::make('created_at')->label(__('attendance::fields.changed_at'))->dateTime(),
                                ])->columns(4),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        return $this->participantContext ??= $this->operations()->participantContext(
            $this->organizationId(),
            (string) $this->attendance()->session_participant_id,
        );
    }

    private function contextValue(string $key): string
    {
        return (string) ($this->context()[$key] ?? __('attendance::messages.not_available'));
    }

    private function nullableContextValue(string $key): ?string
    {
        $value = $this->context()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<array<string, mixed>> */
    private function audit(): array
    {
        return $this->auditRows ??= $this->operations()->auditHistory(
            $this->organizationId(),
            (string) $this->attendance()->getKey(),
        );
    }

    private function attendance(): Attendance
    {
        abort_unless($this->record instanceof Attendance, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) data_get(auth()->user(), 'organization_id', '');
    }

    private function operations(): AttendanceOperationsQueryService
    {
        return app(AttendanceOperationsQueryService::class);
    }
}
