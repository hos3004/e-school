<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Modules\Notifications\Application\Actions\MarkAllNotificationsAsReadAction;
use Modules\Notifications\Application\Actions\QueueManualNotificationAction;
use Modules\Notifications\Application\Services\ManualNotificationChannelResolver;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;
use Shared\Support\BusinessRuleViolation;

/** قائمة صندوق الإرسال مع إجراء جرس المستخدم الحالي دون قالب مخصص. */
final class ListNotificationOutboxes extends ListRecords
{
    protected static string $resource = NotificationOutboxResource::class;

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            self::sendNotificationAction(),
            Action::make('mark_all_as_read')
                ->label(__('notifications::actions.mark_all_as_read'))
                ->icon('heroicon-m-check-circle')
                ->authorize(fn (): bool => Gate::allows('markAllAsRead', NotificationOutbox::class))
                ->action(function (): void {
                    $markedCount = app(MarkAllNotificationsAsReadAction::class)->execute(
                        (string) auth()->id(),
                        (string) data_get(auth()->user(), 'organization_id'),
                    );

                    Notification::make()
                        ->title(__('notifications::messages.marked_all_as_read_count', [
                            'count' => $markedCount,
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function sendNotificationAction(): Action
    {
        return Action::make('send_notification')
            ->label(__('notifications::actions.send_notification'))
            ->icon('heroicon-m-paper-airplane')
            ->color('primary')
            ->authorize(fn (): bool => Gate::allows('create', NotificationOutbox::class))
            ->modalHeading(__('notifications::actions.send_notification_heading'))
            ->modalDescription(__('notifications::actions.send_notification_description'))
            ->modalSubmitActionLabel(__('notifications::actions.confirm_send'))
            ->modalWidth('2xl')
            ->fillForm(fn (): array => [
                'recipient_type' => ManualRecipientType::Student->value,
                'channel' => Channel::InApp->value,
                'request_id' => (string) Str::ulid(),
            ])
            ->schema([
                Select::make('recipient_type')
                    ->label(__('notifications::fields.recipient_type'))
                    ->options(ManualRecipientType::options())
                    ->live()
                    ->afterStateUpdated(static fn (Set $set): mixed => $set('recipient_id', null))
                    ->required(),
                Select::make('recipient_id')
                    ->label(__('notifications::fields.recipient'))
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                        $type = ManualRecipientType::tryFrom((string) $get('recipient_type'));

                        return $type === null
                            ? []
                            : app(ManualNotificationRecipientResolver::class)
                                ->search(self::organizationId(), $type, $search);
                    })
                    ->getOptionLabelUsing(function (mixed $value, Get $get): ?string {
                        $type = ManualRecipientType::tryFrom((string) $get('recipient_type'));

                        return $type === null || !is_string($value)
                            ? null
                            : app(ManualNotificationRecipientResolver::class)
                                ->label(self::organizationId(), $type, $value);
                    })
                    ->live()
                    ->required(),
                Select::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->options(fn (): array => app(ManualNotificationChannelResolver::class)->options())
                    ->default(Channel::InApp->value)
                    ->required(),
                TextInput::make('subject')
                    ->label(__('notifications::fields.subject'))
                    ->maxLength(255)
                    ->live()
                    ->required(),
                Textarea::make('body')
                    ->label(__('notifications::fields.body'))
                    ->rows(5)
                    ->live()
                    ->required(),
                Textarea::make('reason')
                    ->label(__('notifications::fields.reason'))
                    ->helperText(__('notifications::messages.manual_reason_help'))
                    ->maxLength(2000)
                    ->required(),
                Placeholder::make('recipient_count')
                    ->label(__('notifications::fields.recipient_count'))
                    ->content(fn (Get $get): string => (string) self::recipientCount($get)),
                Placeholder::make('preview')
                    ->label(__('notifications::fields.preview'))
                    ->content(fn (Get $get): HtmlString => self::preview($get)),
                Hidden::make('request_id')->required(),
            ])
            ->action(function (array $data): void {
                self::dispatchManual($data);
            });
    }

    private static function recipientCount(Get $get): int
    {
        $type = ManualRecipientType::tryFrom((string) $get('recipient_type'));
        $targetId = $get('recipient_id');

        if ($type === null || !is_string($targetId) || $targetId === '') {
            return 0;
        }

        try {
            return app(ManualNotificationRecipientResolver::class)
                ->resolve(self::organizationId(), $type, $targetId)
                ->count();
        } catch (BusinessRuleViolation) {
            return 0;
        }
    }

    private static function preview(Get $get): HtmlString
    {
        $subject = trim((string) $get('subject'));
        $body = trim((string) $get('body'));
        $count = self::recipientCount($get);

        if ($subject === '' && $body === '') {
            return new HtmlString(e(__('notifications::messages.manual_preview_empty')));
        }

        return new HtmlString(
            '<div class="space-y-2 rounded-lg border p-3">'
            .'<p class="font-medium">'.e($subject).'</p>'
            .'<p class="whitespace-pre-wrap text-sm">'.nl2br(e($body)).'</p>'
            .'<p class="text-xs">'.e(__('notifications::messages.manual_preview_count', ['count' => $count])).'</p>'
            .'</div>',
        );
    }

    /** @param array<string, mixed> $data */
    private static function dispatchManual(array $data): void
    {
        try {
            $result = app(QueueManualNotificationAction::class)->execute(
                organizationId: self::organizationId(),
                actorId: (string) auth()->id(),
                recipientType: ManualRecipientType::from((string) $data['recipient_type']),
                targetId: (string) $data['recipient_id'],
                channel: Channel::from((string) $data['channel']),
                subject: (string) $data['subject'],
                body: (string) $data['body'],
                reason: (string) $data['reason'],
                requestId: (string) $data['request_id'],
                locale: app()->getLocale(),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()
                ->title(__('notifications::messages.manual_send_failed'))
                ->body($violation->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($result->alreadyProcessed
                ? __('notifications::messages.manual_already_processed')
                : __('notifications::messages.manual_queued', [
                    'queued' => $result->queuedCount,
                    'recipients' => $result->recipientCount,
                ]))
            ->when($result->alreadyProcessed, fn (Notification $notification): Notification => $notification->warning())
            ->when(!$result->alreadyProcessed, fn (Notification $notification): Notification => $notification->success())
            ->send();
    }

    private static function organizationId(): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }
}
