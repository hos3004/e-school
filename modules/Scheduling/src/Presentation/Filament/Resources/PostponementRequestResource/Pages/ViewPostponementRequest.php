<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\ProposePostponementAlternative;
use Modules\Scheduling\Application\Actions\RejectPostponement;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource;

final class ViewPostponementRequest extends ViewRecord
{
    protected static string $resource = PostponementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('scheduling::filament.postponement.approve'))
                ->icon('heroicon-m-check')
                ->color('primary')
                ->authorize('approve')
                ->visible(fn (PostponementRequest $record): bool => $record->status->canTransitionTo(PostponementStatus::Scheduled))
                ->form([
                    DateTimePicker::make('agreed_start')
                        ->label(__('scheduling::filament.postponement.agreed_start'))
                        ->default(fn (PostponementRequest $record) => $record->proposed_by_teacher_start ?? $record->proposed_start)
                        ->required(),
                    Textarea::make('reason')
                        ->label(__('scheduling::filament.postponement.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (PostponementRequest $record, array $data): void {
                    app(ApprovePostponement::class)->execute(
                        (string) $record->organization_id,
                        (string) $record->getKey(),
                        (string) auth()->id(),
                        CarbonImmutable::parse((string) $data['agreed_start']),
                        (string) $data['reason'],
                    );

                    Notification::make()->title(__('scheduling::filament.postponement.approved'))->success()->send();
                }),
            Action::make('propose_alternative')
                ->label(__('scheduling::filament.postponement.propose_alternative'))
                ->icon('heroicon-m-arrows-right-left')
                ->color('primary')
                ->authorize('approve')
                ->visible(fn (PostponementRequest $record): bool => $record->status === PostponementStatus::Requested
                    && !$record->requires_admin_review)
                ->form([
                    DateTimePicker::make('proposed_start')
                        ->label(__('scheduling::filament.postponement.teacher_proposed_start'))
                        ->required(),
                    Textarea::make('reason')
                        ->label(__('scheduling::filament.postponement.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (PostponementRequest $record, array $data): void {
                    app(ProposePostponementAlternative::class)->execute(
                        (string) $record->organization_id,
                        (string) $record->getKey(),
                        (string) auth()->id(),
                        CarbonImmutable::parse((string) $data['proposed_start']),
                        (string) $data['reason'],
                    );
                    $this->record->refresh();
                    Notification::make()->title(__('scheduling::filament.postponement.alternative_proposed'))->success()->send();
                }),
            Action::make('reject')
                ->label(__('scheduling::filament.postponement.reject'))
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->authorize('approve')
                ->visible(fn (PostponementRequest $record): bool => $record->status->canTransitionTo(PostponementStatus::Rejected))
                ->form([
                    Textarea::make('reason')
                        ->label(__('scheduling::filament.postponement.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (PostponementRequest $record, array $data): void {
                    app(RejectPostponement::class)->execute(
                        (string) $record->organization_id,
                        (string) $record->getKey(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );

                    Notification::make()->title(__('scheduling::filament.postponement.rejected'))->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('scheduling::filament.postponement.details'))->schema([
                TextEntry::make('id')->label(__('scheduling::filament.postponement.id'))->copyable(),
                TextEntry::make('session_label')
                    ->label(__('scheduling::filament.postponement.session'))
                    ->state(fn (PostponementRequest $record): string => (string) $this->details($record)['session']),
                TextEntry::make('requested_by_label')
                    ->label(__('scheduling::filament.postponement.requested_by'))
                    ->state(fn (PostponementRequest $record): string => (string) $this->details($record)['requested_by']),
                TextEntry::make('student_label')
                    ->label(__('scheduling::filament.postponement.student'))
                    ->state(fn (PostponementRequest $record): string => (string) $this->details($record)['student']),
                TextEntry::make('status')->label(__('scheduling::filament.postponement.status'))->badge()
                    ->formatStateUsing(fn (PostponementStatus $state): string => $state->label()),
                TextEntry::make('requires_admin_review')
                    ->label(__('scheduling::filament.postponement.requires_admin_review'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('scheduling::filament.common.yes')
                        : __('scheduling::filament.common.no')),
                TextEntry::make('reason')->label(__('scheduling::filament.postponement.reason')),
                TextEntry::make('proposed_start')->label(__('scheduling::filament.postponement.proposed_start'))->dateTime(),
                TextEntry::make('proposed_by_teacher_start')->label(__('scheduling::filament.postponement.teacher_proposed_start'))->dateTime(),
                TextEntry::make('agreed_start')->label(__('scheduling::filament.postponement.agreed_start'))->dateTime(),
                TextEntry::make('responded_by_label')
                    ->label(__('scheduling::filament.postponement.responded_by'))
                    ->state(fn (PostponementRequest $record): string => (string) $this->details($record)['responded_by']),
                TextEntry::make('responded_at')->label(__('scheduling::filament.postponement.responded_at'))->dateTime(),
                TextEntry::make('admin_note')->label(__('scheduling::filament.postponement.admin_note')),
            ])->columns(3),
        ]);
    }

    /** @return array<string, mixed> */
    private function details(PostponementRequest $record): array
    {
        return app(SchedulingAdministrationQueryService::class)->postponementDetails(
            (string) $record->organization_id,
            $record,
        );
    }
}
