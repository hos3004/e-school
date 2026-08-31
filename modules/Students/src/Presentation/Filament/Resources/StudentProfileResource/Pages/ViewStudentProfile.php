<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Groups\Domain\Contracts\GroupAssignmentOperations;
use Modules\Guardians\Domain\Contracts\GuardianLinkOperations;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Shared\Filament\UserAvatarAction;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

/** @property StudentProfile $record */
final class ViewStudentProfile extends ViewRecord
{
    protected static string $resource = StudentProfileResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            StudentProfileResource::placementAction(),
            UserAvatarAction::make(
                (string) $this->record->organization_id,
                (string) $this->record->user_id,
            )->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update')),
            $this->editAccountAction(),
            $this->changeAccountStatusAction(),
            $this->linkGuardianAction(),
            $this->unlinkGuardianAction(),
            $this->withdrawMembershipAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('students::admin.hub.overview'))
                ->icon('heroicon-o-identification')
                ->schema([
                    ImageEntry::make('avatar')
                        ->label(__('students::filament.student_name'))
                        ->circular()
                        ->state(fn (): string => app(AvatarQueries::class)
                            ->resolve(
                                app(UserQueryService::class)->findSummary((string) $this->record->user_id)?->avatarPath,
                                $this->record->gender?->value,
                            )->url)
                        ->alt(fn (): string => __('identity::avatars.alt', [
                            'name' => (string) ($this->record->registrationApplication->full_name
                                ?? $this->record->student_code),
                        ]))
                        ->columnSpanFull(),
                    TextEntry::make('registrationApplication.full_name')
                        ->label(__('students::attributes.full_name')),
                    TextEntry::make('student_code')
                        ->label(__('students::attributes.student_code'))
                        ->copyable(),
                    TextEntry::make('registrationApplication.status')
                        ->label(__('students::attributes.status'))
                        ->badge()
                        ->formatStateUsing(fn (?RegistrationStatus $state): ?string => $state?->label()),
                    TextEntry::make('date_of_birth')
                        ->label(__('students::attributes.date_of_birth'))
                        ->date(),
                    TextEntry::make('gender')
                        ->label(__('students::attributes.gender'))
                        ->badge()
                        ->formatStateUsing(fn (?StudentGender $state): ?string => $state?->label()),
                    TextEntry::make('country_id')
                        ->label(__('students::attributes.country_id'))
                        ->formatStateUsing(fn (?string $state): ?string => $state === null
                            ? null
                            : StudentProfileResource::countryOptions()[$state] ?? $state),
                    TextEntry::make('region_id')
                        ->label(__('students::attributes.region_id'))
                        ->formatStateUsing(fn (?string $state): ?string => $state === null
                            ? null
                            : StudentProfileResource::regionOptions()[$state] ?? $state),
                    TextEntry::make('city')
                        ->label(__('students::attributes.city')),
                    TextEntry::make('preferred_language')
                        ->label(__('students::attributes.preferred_language'))
                        ->formatStateUsing(fn (?string $state): ?string => $state === null
                            ? null
                            : __('students::languages.'.$state)),
                    TextEntry::make('joined_at')
                        ->label(__('students::attributes.joined_at'))
                        ->date(),
                    TextEntry::make('notes')
                        ->label(__('students::attributes.notes'))
                        ->columnSpanFull(),
                ])->columns(3),

            Tabs::make(__('students::admin.hub.title'))
                ->persistTabInQueryString('student-hub')
                ->tabs([
                    Tab::make(__('students::admin.hub.account'))
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            $this->repeatable('account', [
                                TextEntry::make('name')->label(__('students::admin.hub.fields.name')),
                                TextEntry::make('username')->label(__('students::admin.hub.fields.username')),
                                TextEntry::make('email')->label(__('students::admin.hub.fields.email')),
                                TextEntry::make('phone')->label(__('students::admin.hub.fields.phone')),
                                TextEntry::make('status')->label(__('students::admin.hub.fields.status'))->badge(),
                            ]),
                        ]),

                    Tab::make(__('students::admin.hub.enrollments'))
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
                            $this->repeatable('enrollments', [
                                TextEntry::make('program')->label(__('students::admin.hub.fields.program')),
                                TextEntry::make('status')->label(__('students::admin.hub.fields.status'))->badge(),
                                TextEntry::make('activated_at')->label(__('students::admin.hub.fields.activated_at'))->dateTime(),
                                TextEntry::make('expected_return_date')->label(__('students::admin.hub.fields.expected_return_date'))->date(),
                            ]),
                        ]),

                    Tab::make(__('students::admin.hub.groups'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            $this->repeatable('groups', [
                                TextEntry::make('group')->label(__('students::admin.hub.fields.group')),
                                TextEntry::make('code')->label(__('students::admin.hub.fields.code'))->copyable(),
                                TextEntry::make('status')->label(__('students::admin.hub.fields.status'))->badge(),
                                TextEntry::make('joined_at')->label(__('students::admin.hub.fields.joined_at'))->dateTime(),
                                TextEntry::make('left_at')->label(__('students::admin.hub.fields.left_at'))->dateTime(),
                            ]),
                        ]),

                    Tab::make(__('students::admin.hub.guardians'))
                        ->icon('heroicon-o-heart')
                        ->schema([
                            $this->repeatable('guardians', [
                                TextEntry::make('name')->label(__('students::admin.hub.fields.guardian_name')),
                                TextEntry::make('phone')->label(__('students::admin.hub.fields.phone')),
                                TextEntry::make('relationship')->label(__('students::admin.hub.fields.relationship'))->badge(),
                                TextEntry::make('is_primary')->label(__('students::admin.hub.fields.is_primary'))->badge(),
                                TextEntry::make('verified_at')->label(__('students::admin.hub.fields.verified_at'))->dateTime(),
                            ]),
                        ]),

                    Tab::make(__('students::admin.hub.sessions'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            // للعرض فقط — أي إجراء على الحصة يتم من مركز عمليات الحصة.
                            $this->repeatable('sessions', [
                                TextEntry::make('title')->label(__('students::admin.hub.fields.title')),
                                TextEntry::make('course')->label(__('students::admin.hub.fields.course')),
                                TextEntry::make('group')->label(__('students::admin.hub.fields.group')),
                                TextEntry::make('status')->label(__('students::admin.hub.fields.status'))->badge(),
                                TextEntry::make('scheduled_start')->label(__('students::admin.hub.fields.scheduled_start'))->dateTime(),
                                TextEntry::make('attended_minutes')->label(__('students::admin.hub.fields.attended_minutes')),
                                TextEntry::make('id')
                                    ->label(__('students::admin.hub.open_session'))
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn (): string => __('students::admin.hub.open_session'))
                                    ->url(fn (?string $state): ?string => $this->sessionHubUrl($state))
                                    ->openUrlInNewTab(),
                            ]),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    private function editAccountAction(): Action
    {
        return Action::make('edit_account')
            ->label(__('students::admin.hub.edit_account'))
            ->icon('heroicon-o-user')
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update'))
            ->schema($this->accountFields())
            ->action(function (array $data): void {
                /** @var StudentProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(UserAccountOperations::class)->updateProfile(
                    organizationId: (string) $record->organization_id,
                    userId: (string) $record->user_id,
                    fields: [
                        'name' => $data['name'],
                        'phone' => $data['phone'] ?? null,
                        'locale' => $data['locale'],
                        'timezone' => $data['timezone'],
                    ],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('students::admin.hub.account_saved'));

                $this->hubData = null;
            });
    }

    private function changeAccountStatusAction(): Action
    {
        return Action::make('change_account_status')
            ->label(__('students::admin.hub.change_status'))
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.change_status'))
            ->schema([
                Select::make('status')
                    ->label(__('students::admin.hub.fields.status'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->accountStatusOptions())
                    ->required(),
                Textarea::make('reason')
                    ->label(__('students::attributes.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StudentProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(UserAccountOperations::class)->changeStatus(
                    organizationId: (string) $record->organization_id,
                    userId: (string) $record->user_id,
                    status: (string) $data['status'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('students::admin.hub.status_saved'));

                $this->hubData = null;
            });
    }

    private function linkGuardianAction(): Action
    {
        return Action::make('link_guardian')
            ->label(__('students::admin.guardians.link_action'))
            ->icon('heroicon-o-heart')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('guardian.link'))
            ->schema([
                Select::make('guardian_profile_id')
                    ->label(__('students::admin.guardians.guardian'))
                    ->options(function (): array {
                        /** @var GuardianQuery $query */
                        $query = app(GuardianQuery::class);

                        return $query->guardianOptions($this->organizationId());
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('relationship')
                    ->label(__('students::admin.guardians.relationship'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->guardianRelationshipOptions())
                    ->required(),
                Checkbox::make('is_primary')
                    ->label(__('students::admin.guardians.is_primary')),
                Checkbox::make('can_act_for')
                    ->label(__('students::admin.guardians.can_act_for')),
                Textarea::make('reason')
                    ->label(__('students::attributes.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StudentProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(GuardianLinkOperations::class)->link(
                    organizationId: $this->organizationId(),
                    guardianProfileId: (string) $data['guardian_profile_id'],
                    studentProfileId: (string) $record->getKey(),
                    relationship: (string) $data['relationship'],
                    isPrimary: (bool) ($data['is_primary'] ?? false),
                    canActFor: (bool) ($data['can_act_for'] ?? false),
                    visibleSections: null,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('students::admin.guardians.linked'));

                $this->hubData = null;
            });
    }

    private function unlinkGuardianAction(): Action
    {
        return Action::make('unlink_guardian')
            ->label(__('students::admin.guardians.unlink_action'))
            ->icon('heroicon-o-link-slash')
            ->color('danger')
            ->visible(function (): bool {
                /** @var StudentProfile $record */
                $record = $this->record;

                return $this->hub($record, 'guardians') !== []
                    && (bool) auth()->user()?->can('guardian.link');
            })
            ->schema([
                Select::make('guardian_link_id')
                    ->label(__('students::admin.guardians.link'))
                    ->options(function (): array {
                        /** @var StudentProfile $record */
                        $record = $this->record;
                        $links = $this->hub($record, 'guardians');
                        $options = [];

                        foreach ($links as $link) {
                            $options[(string) $link['id']] = (string) $link['name']
                                .' · '.(string) $link['relationship'];
                        }

                        return $options;
                    })
                    ->required(),
                Textarea::make('reason')
                    ->label(__('students::attributes.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guard(fn () => app(GuardianLinkOperations::class)->unlink(
                    organizationId: $this->organizationId(),
                    guardianLinkId: (string) $data['guardian_link_id'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('students::admin.guardians.unlinked'));

                $this->hubData = null;
            });
    }

    private function withdrawMembershipAction(): Action
    {
        return Action::make('withdraw_membership')
            ->label(__('students::admin.memberships.withdraw_action'))
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->color('danger')
            ->visible(function (): bool {
                /** @var StudentProfile $record */
                $record = $this->record;
                $memberships = $this->hub($record, 'groups');

                foreach ($memberships as $membership) {
                    if (empty($membership['left_at'])) {
                        return (bool) auth()->user()?->can('group.manage');
                    }
                }

                return false;
            })
            ->schema([
                Select::make('membership_id')
                    ->label(__('students::admin.memberships.membership'))
                    ->options(function (): array {
                        /** @var StudentProfile $record */
                        $record = $this->record;
                        $memberships = $this->hub($record, 'groups');
                        $active = [];

                        foreach ($memberships as $membership) {
                            if (empty($membership['left_at'])) {
                                $active[(string) $membership['id']] = (string) $membership['group']
                                    .' ('.(string) $membership['joined_at'].')';
                            }
                        }

                        return $active;
                    })
                    ->required(),
                Textarea::make('reason')
                    ->label(__('students::attributes.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guard(fn () => app(GroupAssignmentOperations::class)->withdrawStudent(
                    organizationId: $this->organizationId(),
                    membershipId: (string) $data['membership_id'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('students::admin.memberships.withdrawn'));

                $this->hubData = null;
            });
    }

    /**
     * تشغيل إجراء حساس مع تحويل خرق القواعد إلى إشعار واضح.
     */
    private function guard(callable $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()
                ->title($violation->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        Notification::make()
            ->title($successMessage)
            ->success()
            ->send();
    }

    private function actorId(): string
    {
        return (string) auth()->id();
    }

    private function organizationId(): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }

    /** @return array<int, Component> */
    private function accountFields(): array
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        return [
            TextInput::make('name')
                ->label(__('students::admin.hub.fields.name'))
                ->maxLength(255)
                ->required(),
            TextInput::make('phone')
                ->label(__('students::admin.hub.fields.phone'))
                ->tel()
                ->maxLength(32),
            Select::make('locale')
                ->label(__('students::admin.hub.fields.locale'))
                ->options(Locales::options('identity::locales.'))
                ->required(),
            TextInput::make('timezone')
                ->label(__('students::admin.hub.fields.timezone'))
                ->default(fn (): string => (string) (data_get($user, 'timezone') ?: config('app.timezone')))
                ->rule('timezone:all')
                ->required(),
            Textarea::make('reason')
                ->label(__('students::attributes.reason'))
                ->maxLength(2000)
                ->required(),
        ];
    }

    private function sessionHubUrl(?string $sessionId): ?string
    {
        if (!is_string($sessionId) || !preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $sessionId)) {
            return null;
        }

        try {
            return route('filament.admin.resources.sessions.view', ['record' => $sessionId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, TextEntry> $schema
     */
    private function repeatable(string $section, array $schema): RepeatableEntry
    {
        return RepeatableEntry::make($section.'_hub')
            ->hiddenLabel()
            ->placeholder(__('students::admin.hub.empty'))
            ->getStateUsing(fn (StudentProfile $record): array => $this->hub($record, $section))
            ->schema($schema)
            ->columns(3);
    }

    /** @return list<mixed> */
    private function hub(StudentProfile $record, string $section): array
    {
        $this->hubData ??= app(ProfileAdministrationQueryService::class)->studentHub(
            (string) $record->organization_id,
            (string) $record->getKey(),
            (string) $record->user_id,
        );

        $data = $this->hubData[$section] ?? [];

        return is_array($data) ? array_values($data) : [];
    }
}
