<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Modules\Notifications\Application\Actions\DuplicatePopupCampaignAction;
use Modules\Notifications\Application\Actions\TransitionPopupCampaignAction;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;
use Shared\Support\BusinessRuleViolation;

final class ListPopupCampaigns extends ListRecords
{
    protected static string $resource = PopupCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_campaign')
                ->label(__('notifications::popups.actions.create'))
                ->icon('heroicon-o-plus')
                ->visible(static fn (): bool => auth()->user()?->can('create', PopupCampaign::class) ?? false)
                ->url(PopupCampaignResource::getUrl('create')),
        ];
    }

    /**
     * إجراءات الصف المختصرة في القائمة — نفس الأزرار الآمنة.
     *
     * @return list<Action>
     */
    public static function recordActionsForTable(): array
    {
        return [
            Action::make('view_campaign')
                ->label(__('notifications::popups.actions.view'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('view', $record) ?? false)
                ->url(static fn (PopupCampaign $record): string => PopupCampaignResource::getUrl('view', ['record' => $record])),
            Action::make('edit_campaign')
                ->label(__('notifications::popups.actions.edit'))
                ->icon('heroicon-o-pencil-square')
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('update', $record) ?? false
                    && in_array($record->status, [PopupCampaignStatus::Draft, PopupCampaignStatus::Paused], true))
                ->url(static fn (PopupCampaign $record): string => PopupCampaignResource::getUrl('edit', ['record' => $record])),
            Action::make('publish_campaign')
                ->label(__('notifications::popups.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('notifications::popups.confirm.publish_description'))
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('publish', $record) ?? false
                    && $record->status === PopupCampaignStatus::Draft)
                ->form([
                    Textarea::make('reason')
                        ->label(__('notifications::popups.fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(static function (PopupCampaign $record, array $data): void {
                    self::applyTransition($record, PopupCampaignStatus::Published, (string) $data['reason']);
                }),
            Action::make('pause_campaign')
                ->label(__('notifications::popups.actions.pause'))
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('pause', $record) ?? false
                    && $record->status === PopupCampaignStatus::Published)
                ->form([
                    Textarea::make('reason')
                        ->label(__('notifications::popups.fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(static function (PopupCampaign $record, array $data): void {
                    self::applyTransition($record, PopupCampaignStatus::Paused, (string) $data['reason']);
                }),
            Action::make('resume_campaign')
                ->label(__('notifications::popups.actions.resume'))
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('publish', $record) ?? false
                    && $record->status === PopupCampaignStatus::Paused)
                ->form([
                    Textarea::make('reason')
                        ->label(__('notifications::popups.fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(static function (PopupCampaign $record, array $data): void {
                    self::applyTransition($record, PopupCampaignStatus::Published, (string) $data['reason']);
                }),
            Action::make('duplicate_campaign')
                ->label(__('notifications::popups.actions.duplicate'))
                ->icon('heroicon-o-document-duplicate')
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('create', PopupCampaign::class) ?? false)
                ->requiresConfirmation()
                ->form([
                    Textarea::make('reason')
                        ->label(__('notifications::popups.fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(static function (PopupCampaign $record, array $data): void {
                    try {
                        app(DuplicatePopupCampaignAction::class)->execute(
                            source: $record,
                            actorId: (string) auth()->id(),
                            reason: (string) $data['reason'],
                        );
                    } catch (BusinessRuleViolation $violation) {
                        self::notifyViolation($violation);

                        return;
                    }

                    Notification::make()->title(__('notifications::popups.messages.duplicated'))->success()->send();
                }),
            Action::make('archive_campaign')
                ->label(__('notifications::popups.actions.archive'))
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('notifications::popups.confirm.archive_description'))
                ->visible(static fn (PopupCampaign $record): bool => auth()->user()?->can('archive', $record) ?? false
                    && $record->status !== PopupCampaignStatus::Archived)
                ->form([
                    Textarea::make('reason')
                        ->label(__('notifications::popups.fields.reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(static function (PopupCampaign $record, array $data): void {
                    self::applyTransition($record, PopupCampaignStatus::Archived, (string) $data['reason']);
                }),
        ];
    }

    protected static function applyTransition(PopupCampaign $record, PopupCampaignStatus $target, string $reason): void
    {
        try {
            app(TransitionPopupCampaignAction::class)->execute(
                campaign: $record,
                target: $target,
                actorId: (string) auth()->id(),
                reason: $reason,
            );
        } catch (BusinessRuleViolation $violation) {
            self::notifyViolation($violation);

            return;
        }

        Notification::make()
            ->title(__('notifications::popups.messages.status_changed'))
            ->success()
            ->send();
    }

    protected static function notifyViolation(BusinessRuleViolation $violation): void
    {
        Notification::make()->title($violation->getMessage())->danger()->send();

        throw new Halt;
    }
}
