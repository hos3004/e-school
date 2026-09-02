<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\ValueObjects\RoleData;
use Modules\Identity\Application\Actions\ChangeUserStatus;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Shared\Filament\UserAvatarAction;
use Shared\Support\BusinessRuleViolation;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            UserAvatarAction::make(
                (string) $this->userRecord()->organization_id,
                (string) $this->userRecord()->getKey(),
            )->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->userRecord())),
            Action::make('change_status')
                ->label(__('identity::admin.change_status'))
                ->icon('heroicon-o-no-symbol')
                ->color('primary')
                ->visible(fn (): bool => $this->userRecord()->getKey() !== auth()->id()
                    && (auth()->user()?->can('changeStatus', $this->userRecord()) ?? false))
                ->schema([
                    Select::make('status')
                        ->label(__('identity::labels.status'))
                        ->options(fn (): array => collect($this->userRecord()->status->allowedTransitions())
                            ->mapWithKeys(static fn (UserStatus $status): array => [$status->value => $status->label()])
                            ->all())
                        ->required(),
                    Textarea::make('reason')
                        ->label(__('identity::labels.reason'))
                        ->maxLength(2000)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(ChangeUserStatus::class)->execute(
                            target: $this->userRecord(),
                            to: UserStatus::from((string) $data['status']),
                            reason: (string) $data['reason'],
                            actorId: (string) auth()->id(),
                        );
                    } catch (BusinessRuleViolation $violation) {
                        Notification::make()->title($violation->getMessage())->danger()->send();

                        return;
                    }

                    $this->userRecord()->refresh();
                    Notification::make()->title(__('identity::admin.status_changed'))->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('identity::admin.overview'))
                ->icon('heroicon-o-user-circle')
                ->schema([
                    ImageEntry::make('avatar')
                        ->label(__('identity::avatars.field'))
                        ->circular()
                        ->state(fn (): string => app(AvatarQueries::class)
                            ->resolve($this->userRecord()->avatar_path, null)->url)
                        ->alt(fn (): string => __('identity::avatars.alt', ['name' => $this->userRecord()->name])),
                    TextEntry::make('name')->label(__('identity::labels.name')),
                    TextEntry::make('username')->label(__('identity::labels.username'))->copyable(),
                    TextEntry::make('status')
                        ->label(__('identity::labels.status'))
                        ->badge()
                        ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                        ->color(fn (UserStatus $state): string => $state->color()),
                    TextEntry::make('email')->label(__('identity::labels.email')),
                    TextEntry::make('phone')->label(__('identity::labels.phone')),
                    TextEntry::make('locale')->label(__('identity::labels.locale')),
                    TextEntry::make('timezone')->label(__('identity::labels.timezone')),
                    TextEntry::make('last_login_at')->label(__('identity::labels.last_login_at'))->dateTime(),
                    TextEntry::make('last_login_ip')->label(__('identity::admin.last_login_ip')),
                ])->columns(3),

            Tabs::make(__('identity::admin.hub'))
                ->persistTabInQueryString('user-hub')
                ->tabs([
                    Tab::make(__('identity::admin.roles_tab'))
                        ->icon('heroicon-o-identification')
                        ->schema([
                            RepeatableEntry::make('roles_hub')
                                ->hiddenLabel()
                                ->placeholder(__('identity::admin.empty'))
                                ->getStateUsing(fn (): array => array_map(
                                    static fn (RoleData $role): array => [
                                        'name' => UserResource::roleLabel($role->name),
                                        'scope' => $role->organizationId === null
                                            ? __('identity::admin.global_role')
                                            : __('identity::admin.organization_role'),
                                    ],
                                    app(AccessControlQuerier::class)->rolesForModel(
                                        app(UserQueryService::class)->modelType(),
                                        (string) $this->userRecord()->getKey(),
                                    ),
                                ))
                                ->schema([
                                    TextEntry::make('name')->label(__('identity::admin.role')),
                                    TextEntry::make('scope')->label(__('identity::admin.role_scope'))->badge(),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make(__('identity::admin.devices_tab'))
                        ->icon('heroicon-o-device-phone-mobile')
                        ->schema([
                            RepeatableEntry::make('devices_hub')
                                ->hiddenLabel()
                                ->placeholder(__('identity::admin.empty'))
                                ->getStateUsing(fn (): array => UserDevice::query()
                                    ->forUser((string) $this->userRecord()->getKey())
                                    ->orderByDesc('last_used_at')
                                    ->get()
                                    ->map(static fn (UserDevice $device): array => [
                                        'name' => $device->device_name,
                                        'platform' => $device->platform,
                                        'last_used_at' => $device->last_used_at,
                                        'status' => $device->isRevoked()
                                            ? __('identity::admin.device_revoked')
                                            : __('identity::admin.device_active'),
                                    ])->all())
                                ->schema([
                                    TextEntry::make('name')->label(__('identity::labels.device_name')),
                                    TextEntry::make('platform')->label(__('identity::labels.platform')),
                                    TextEntry::make('last_used_at')->label(__('identity::admin.last_used_at'))->dateTime(),
                                    TextEntry::make('status')->label(__('identity::labels.status'))->badge(),
                                ])
                                ->columns(4),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    private function userRecord(): User
    {
        $record = $this->record;
        abort_unless($record instanceof User, 404);

        return $record;
    }
}
