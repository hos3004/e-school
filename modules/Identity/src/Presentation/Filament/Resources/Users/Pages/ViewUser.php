<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
use Modules\AccessControl\Domain\Contracts\DirectPermissionGateway;
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
            Action::make('optional_access')
                ->label(__('identity::admin.optional_access_manage'))
                ->icon('heroicon-o-key')
                ->color('primary')
                // لا يعدّل أحد صلاحياته الاختيارية بنفسه، وإلا صارت البوابة تصعيدًا ذاتيًا.
                ->visible(fn (): bool => self::optionalPermissions() !== []
                    && $this->userRecord()->getKey() !== auth()->id()
                    && (auth()->user()?->can('accesscontrol.permissions.grant_direct') ?? false))
                ->fillForm(fn (): array => $this->optionalAccessForm())
                ->schema(fn (): array => [
                    ...array_map(
                        static fn (string $permission): Toggle => Toggle::make(self::toggleKey($permission))
                            ->label(self::permissionLabel($permission)),
                        self::optionalPermissions(),
                    ),
                    Textarea::make('reason')
                        ->label(__('identity::labels.reason'))
                        ->helperText(__('identity::admin.optional_access_help'))
                        ->maxLength(2000)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $gateway = app(DirectPermissionGateway::class);
                    $user = $this->userRecord();
                    $modelType = app(UserQueryService::class)->modelType();
                    $modelId = (string) $user->getKey();
                    $organizationId = (string) $user->organization_id;
                    $actorId = (string) auth()->id();
                    $reason = (string) ($data['reason'] ?? '');
                    $changed = 0;

                    try {
                        foreach (self::optionalPermissions() as $permission) {
                            $changed += (int) ((bool) ($data[self::toggleKey($permission)] ?? false)
                                ? $gateway->grantIfMissing($permission, $modelType, $modelId, $organizationId, $actorId, $reason)
                                : $gateway->revokeIfPresent($permission, $modelType, $modelId, $organizationId, $actorId, $reason));
                        }
                    } catch (BusinessRuleViolation $violation) {
                        Notification::make()->title($violation->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title(__($changed === 0
                            ? 'identity::admin.optional_access_unchanged'
                            : 'identity::admin.optional_access_saved'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * الصلاحيات الاختيارية المعلنة — قائمة سياسة في الإعدادات لا في الكود.
     *
     * @return list<string>
     */
    private static function optionalPermissions(): array
    {
        return array_values(array_filter(
            (array) config('accesscontrol.optional_direct_permissions', []),
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));
    }

    /** مفتاح حقل النموذج: النقطة تعني التفرّع في Filament فتُستبدل. */
    private static function toggleKey(string $permission): string
    {
        return 'grant_'.str_replace('.', '_', $permission);
    }

    private static function permissionLabel(string $permission): string
    {
        $key = 'identity::admin.optional_access_labels.'.str_replace('.', '_', $permission);

        return trans()->has($key) ? __($key) : $permission;
    }

    /** @return array<string, bool> */
    private function optionalAccessForm(): array
    {
        $querier = app(AccessControlQuerier::class);
        $modelType = app(UserQueryService::class)->modelType();
        $modelId = (string) $this->userRecord()->getKey();
        $state = [];

        foreach (self::optionalPermissions() as $permission) {
            $state[self::toggleKey($permission)] = $querier
                ->modelHasDirectPermission($modelType, $modelId, $permission);
        }

        return $state;
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
                            RepeatableEntry::make('optional_access_hub')
                                ->label(__('identity::admin.optional_access'))
                                ->placeholder(__('identity::admin.empty'))
                                ->getStateUsing(function (): array {
                                    // الحالة تُقرأ مرة واحدة، لا استعلام لكل صف في حلقة العرض.
                                    $state = $this->optionalAccessForm();

                                    return array_map(
                                        static fn (string $permission): array => [
                                            'permission' => self::permissionLabel($permission),
                                            'state' => ($state[self::toggleKey($permission)] ?? false)
                                                ? __('identity::admin.optional_access_granted')
                                                : __('identity::admin.optional_access_withheld'),
                                        ],
                                        self::optionalPermissions(),
                                    );
                                })
                                ->schema([
                                    TextEntry::make('permission')->label(__('identity::admin.optional_access_permission')),
                                    TextEntry::make('state')->label(__('identity::admin.optional_access_state'))->badge(),
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
