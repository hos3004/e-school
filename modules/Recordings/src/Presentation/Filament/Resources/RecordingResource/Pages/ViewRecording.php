<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Filament\Resources\RecordingResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Recordings\Application\Actions\ArchiveRecordingAction;
use Modules\Recordings\Application\Actions\DeleteRecordingAction;
use Modules\Recordings\Application\Actions\GrantRecordingAccessAction;
use Modules\Recordings\Application\Actions\RevokeRecordingAccessAction;
use Modules\Recordings\Application\Queries\RecordingOperationsQueryService;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\ValueObjects\RecordingAdministrationData;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;
use Shared\Support\BusinessRuleViolation;

final class ViewRecording extends ViewRecord
{
    protected static string $resource = RecordingResource::class;

    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('watch')
                ->label(__('recordings::actions.watch'))
                ->icon('heroicon-m-play')
                ->color('success')
                ->authorize('watch')
                ->visible(fn (): bool => $this->recording()->status === RecordingStatus::Ready)
                ->url(fn (): string => URL::temporarySignedRoute(
                    'portal.recordings.watch',
                    now()->addMinutes(max(1, (int) config('recordings.access.signed_url_ttl_minutes'))),
                    ['recording' => (string) $this->recording()->getKey()],
                ))
                ->openUrlInNewTab(),
            $this->grantAccessAction(),
            $this->revokeAccessAction(),
            Action::make('archive')
                ->label(__('recordings::actions.archive'))
                ->icon('heroicon-m-archive-box-arrow-down')
                ->authorize('manageLifecycle')
                ->visible(fn (): bool => $this->recording()->status === RecordingStatus::Ready)
                ->requiresConfirmation()
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    $this->runSafely(function () use ($data): void {
                        app(ArchiveRecordingAction::class)->execute(
                            $this->recording(),
                            actorId: (string) auth()->id(),
                            reason: (string) $data['reason'],
                        );
                    }, __('recordings::messages.archived'));
                }),
            Action::make('delete')
                ->label(__('recordings::actions.delete'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->authorize('delete')
                ->visible(fn (): bool => !$this->recording()->trashed()
                    && $this->recording()->status !== RecordingStatus::Expired)
                ->requiresConfirmation()
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    $this->runSafely(function () use ($data): void {
                        app(DeleteRecordingAction::class)->execute(
                            $this->recording(),
                            (string) $data['reason'],
                            (string) auth()->id(),
                        );
                    }, __('recordings::messages.deleted'));
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('recordings::hub.overview'))
                ->icon('heroicon-o-video-camera')
                ->schema([
                    TextEntry::make('session_context')
                        ->label(__('recordings::fields.session'))
                        ->state(fn (): string => (string) $this->hub('context')[0]['session']),
                    TextEntry::make('course_context')
                        ->label(__('recordings::fields.course'))
                        ->state(fn (): string => (string) $this->hub('context')[0]['course']),
                    TextEntry::make('group_context')
                        ->label(__('recordings::fields.group'))
                        ->state(fn (): string => (string) $this->hub('context')[0]['group']),
                    TextEntry::make('teacher_context')
                        ->label(__('recordings::fields.teacher'))
                        ->state(fn (): string => (string) $this->hub('context')[0]['teacher']),
                    TextEntry::make('status')
                        ->label(__('recordings::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof RecordingStatus ? $state->label() : (string) $state)
                        ->color(fn (mixed $state): string => $state instanceof RecordingStatus ? $state->color() : 'gray'),
                    TextEntry::make('provider')->label(__('recordings::fields.provider'))->badge(),
                    TextEntry::make('duration_seconds')
                        ->label(__('recordings::fields.duration'))
                        ->formatStateUsing(fn (mixed $state): string => $state === null
                            ? __('recordings::messages.unavailable')
                            : __('recordings::messages.duration_minutes', ['minutes' => (int) ceil((int) $state / 60)])),
                    TextEntry::make('size_bytes')
                        ->label(__('recordings::fields.size'))
                        ->formatStateUsing(fn (mixed $state): string => self::fileSize($state === null ? null : (int) $state)),
                    TextEntry::make('available_from')->label(__('recordings::fields.available_from'))->dateTime(),
                    TextEntry::make('expires_at')->label(__('recordings::fields.expires_at'))->dateTime(),
                    TextEntry::make('active_grants_metric')
                        ->label(__('recordings::fields.active_grants'))
                        ->state(fn (): int => $this->metric('activeGrantCount'))
                        ->badge(),
                    TextEntry::make('views_metric')
                        ->label(__('recordings::fields.views'))
                        ->state(fn (): int => $this->metric('viewCount'))
                        ->badge(),
                    TextEntry::make('downloads_metric')
                        ->label(__('recordings::fields.downloads'))
                        ->state(fn (): int => $this->metric('downloadCount'))
                        ->badge(),
                    TextEntry::make('last_viewed_at_metric')
                        ->label(__('recordings::fields.last_viewed_at'))
                        ->state(fn (): ?string => $this->administration()?->lastViewedAt)
                        ->dateTime(),
                ])->columns(4),

            Tabs::make(__('recordings::hub.title'))
                ->persistTabInQueryString('recording-hub')
                ->tabs([
                    $this->grantsTab(),
                    $this->viewsTab(),
                    $this->auditTab(),
                ])->columnSpanFull(),
        ]);
    }

    private function grantsTab(): Tab
    {
        return Tab::make(__('recordings::hub.grants'))
            ->icon('heroicon-o-key')
            ->badge(fn (): int => count($this->hub('grants')))
            ->schema([
                RepeatableEntry::make('grants_hub')
                    ->hiddenLabel()
                    ->placeholder(__('recordings::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('grants'))
                    ->schema([
                        TextEntry::make('target_type')->label(__('recordings::fields.target_type')),
                        TextEntry::make('target')->label(__('recordings::fields.target')),
                        TextEntry::make('status')->label(__('recordings::fields.status'))->badge(),
                        TextEntry::make('granted_by')->label(__('recordings::fields.granted_by')),
                        TextEntry::make('expires_at')->label(__('recordings::fields.expires_at'))->dateTime(),
                        TextEntry::make('revoked_at')->label(__('recordings::fields.revoked_at'))->dateTime(),
                        TextEntry::make('reason')->label(__('recordings::fields.reason'))->columnSpan(2),
                    ])->columns(4),
            ]);
    }

    private function viewsTab(): Tab
    {
        return Tab::make(__('recordings::hub.views'))
            ->icon('heroicon-o-eye')
            ->badge(fn (): int => count($this->hub('views')))
            ->schema([
                RepeatableEntry::make('views_hub')
                    ->hiddenLabel()
                    ->placeholder(__('recordings::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('views'))
                    ->schema([
                        TextEntry::make('viewer')->label(__('recordings::fields.viewer')),
                        TextEntry::make('action')->label(__('recordings::fields.action'))->badge(),
                        TextEntry::make('viewed_at')->label(__('recordings::fields.viewed_at'))->dateTime(),
                        TextEntry::make('ip_address')->label(__('recordings::fields.ip_address')),
                    ])->columns(4),
            ]);
    }

    private function auditTab(): Tab
    {
        return Tab::make(__('recordings::hub.audit'))
            ->icon('heroicon-o-shield-check')
            ->schema([
                RepeatableEntry::make('audit_hub')
                    ->hiddenLabel()
                    ->placeholder(__('recordings::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('audit'))
                    ->schema([
                        TextEntry::make('action')->label(__('recordings::fields.action')),
                        TextEntry::make('actor')->label(__('recordings::fields.actor')),
                        TextEntry::make('reason')->label(__('recordings::fields.reason')),
                        TextEntry::make('created_at')->label(__('recordings::fields.changed_at'))->dateTime(),
                    ])->columns(4),
            ]);
    }

    private function grantAccessAction(): Action
    {
        return Action::make('grant_access')
            ->label(__('recordings::actions.grant_access'))
            ->icon('heroicon-m-key')
            ->authorize(fn (): bool => (bool) (auth()->user()?->can('recording.grant') ?? false))
            ->visible(fn (): bool => in_array($this->recording()->status, [RecordingStatus::Processing, RecordingStatus::Ready], true))
            ->schema([
                Select::make('target_type')
                    ->label(__('recordings::fields.target_type'))
                    ->options([
                        'user' => __('recordings::fields.user'),
                        'group' => __('recordings::fields.group'),
                    ])
                    ->default('user')
                    ->live()
                    ->required(),
                Select::make('user_id')
                    ->label(__('recordings::fields.user'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => collect(app(UserAccountDirectory::class)
                        ->search($this->organizationId(), $search, 25))
                        ->mapWithKeys(static fn (mixed $user): array => [$user->id => $user->name])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => app(UserAccountDirectory::class)
                        ->find($this->organizationId(), (string) $value)?->name)
                    ->visible(fn (callable $get): bool => $get('target_type') === 'user')
                    ->required(fn (callable $get): bool => $get('target_type') === 'user'),
                Select::make('group_id')
                    ->label(__('recordings::fields.group'))
                    ->options(fn (): array => collect(app(GroupAdministrationQueries::class)
                        ->activeGroupsForScheduling($this->organizationId()))
                        ->mapWithKeys(fn (mixed $group): array => [$group->id => $this->localized($group->name)])
                        ->all())
                    ->searchable()
                    ->visible(fn (callable $get): bool => $get('target_type') === 'group')
                    ->required(fn (callable $get): bool => $get('target_type') === 'group'),
                DateTimePicker::make('expires_at')
                    ->label(__('recordings::fields.expires_at'))
                    ->default(fn (): CarbonImmutable => CarbonImmutable::now('UTC')
                        ->addDays(max(1, (int) config('recordings.grants.default_expires_days')))
                        ->min(CarbonImmutable::instance($this->recording()->expires_at)))
                    ->maxDate(fn (): CarbonImmutable => CarbonImmutable::instance($this->recording()->expires_at))
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(GrantRecordingAccessAction::class)->execute(
                        $this->recording(),
                        (string) auth()->id(),
                        grantedToUserId: ($data['target_type'] ?? null) === 'user' ? (string) $data['user_id'] : null,
                        grantedToGroupId: ($data['target_type'] ?? null) === 'group' ? (string) $data['group_id'] : null,
                        expiresAt: CarbonImmutable::parse((string) $data['expires_at']),
                        reason: (string) $data['reason'],
                    );
                }, __('recordings::messages.access_granted'));
            });
    }

    private function revokeAccessAction(): Action
    {
        return Action::make('revoke_access')
            ->label(__('recordings::actions.revoke_access'))
            ->icon('heroicon-m-key')
            ->color('warning')
            ->authorize(fn (): bool => (bool) (auth()->user()?->can('recording.grant') ?? false))
            ->visible(fn (): bool => collect($this->hub('grants'))->contains('status_value', 'active'))
            ->schema([
                Select::make('grant_id')
                    ->label(__('recordings::fields.active_grant'))
                    ->options(fn (): array => collect($this->hub('grants'))
                        ->where('status_value', 'active')
                        ->mapWithKeys(static fn (array $grant): array => [(string) $grant['id'] => (string) $grant['target']])
                        ->all())
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(RevokeRecordingAccessAction::class)->execute(
                        $this->recording(),
                        (string) $data['grant_id'],
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                }, __('recordings::messages.access_revoked'));
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('recordings::fields.reason'))
            ->required()
            ->minLength(3)
            ->maxLength(1000);
    }

    private function runSafely(callable $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        $this->hubData = null;
        Notification::make()->title($successMessage)->success()->send();
    }

    /** @return list<array<string, mixed>> */
    private function hub(string $section): array
    {
        $this->hubData ??= app(RecordingOperationsQueryService::class)
            ->hub($this->organizationId(), $this->recording());

        return $this->hubData[$section] ?? [];
    }

    private function administration(): ?RecordingAdministrationData
    {
        return app(RecordingAdministrationQueries::class)->findForOrganization(
            $this->organizationId(),
            (string) $this->recording()->getKey(),
        );
    }

    /** @param 'activeGrantCount'|'viewCount'|'downloadCount' $property */
    private function metric(string $property): int
    {
        $data = $this->administration();

        return $data === null ? 0 : $data->{$property};
    }

    private function recording(): Recording
    {
        abort_unless($this->record instanceof Recording, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->recording()->organization_id;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value) ?: '');
    }

    private static function fileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return (string) __('recordings::messages.unavailable');
        }

        return $bytes >= 1_000_000_000
            ? (string) __('recordings::messages.size_gigabytes', ['size' => number_format($bytes / 1_000_000_000, 2)])
            : (string) __('recordings::messages.size_megabytes', ['size' => number_format($bytes / 1_000_000, 1)]);
    }
}
