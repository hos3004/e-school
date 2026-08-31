<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\DTOs\AvatarPresentation;
use Modules\Identity\Domain\Contracts\DTOs\UserSummary;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Staff\Application\Actions\AddTeacherRate;
use Modules\Staff\Application\Actions\AssignTeacherQualificationsAction;
use Modules\Staff\Application\Actions\CreateTeacherContract;
use Modules\Staff\Application\Actions\DecideTeacherAvailabilityAction;
use Modules\Staff\Application\Actions\RemoveTeacherAvailability;
use Modules\Staff\Application\Actions\RevokeTeacherQualificationAction;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Shared\Filament\UserAvatarAction;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;
use Shared\ValueObjects\Money;

/** @property StaffProfile $record */
final class ViewStaffProfile extends ViewRecord
{
    protected static string $resource = StaffProfileResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->openEditAction(),
            UserAvatarAction::make(
                (string) $this->record->organization_id,
                (string) $this->record->user_id,
            )->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update')),            $this->editAccountAction(),
            $this->changeAccountStatusAction(),
            $this->assignQualificationsAction(),
            $this->revokeQualificationAction(),
            $this->newContractAction(),
            $this->newRateAction(),
            $this->assignGroupAction(),
            $this->endAssignmentAction(),
            $this->addAvailabilityAction(),
            $this->cancelAvailabilityAction(),
            $this->decideAvailabilityAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('staff::admin.hub.overview'))
                ->icon('heroicon-o-identification')
                ->schema([
                    ImageEntry::make('avatar')
                        ->label(__('staff::filament.profile.fields.name'))
                        ->circular()
                        ->state(fn (): string => $this->avatarPresentation()->url)
                        ->alt(fn (): string => __('identity::avatars.alt', [
                            'name' => (string) ($this->accountSummary()->name ?? $this->record->staff_code),
                        ]))
                        ->columnSpanFull(),
                    TextEntry::make('staff_code')
                        ->label(__('staff::filament.profile.fields.staff_code'))
                        ->copyable(),
                    TextEntry::make('employment_type')
                        ->label(__('staff::filament.profile.fields.employment_type'))
                        ->badge()
                        ->formatStateUsing(fn (?EmploymentType $state): ?string => $state?->label())
                        ->color(fn (?EmploymentType $state): string => $state?->color() ?? 'gray'),
                    TextEntry::make('gender')
                        ->label(__('staff::filament.profile.fields.gender'))
                        ->formatStateUsing(fn (?StaffGender $state): ?string => $state === null
                            ? null
                            : __('staff::filament.profile.gender_options.'.$state->value)),
                    TextEntry::make('phone')->label(__('staff::filament.profile.fields.phone')),
                    TextEntry::make('country_id')
                        ->label(__('staff::filament.profile.fields.country'))
                        ->formatStateUsing(fn (?string $state): ?string => $state === null
                            ? null
                            : StaffProfileResource::countryOptions()[$state] ?? $state),
                    TextEntry::make('region_id')
                        ->label(__('staff::filament.profile.fields.region'))
                        ->formatStateUsing(fn (?string $state): ?string => $state === null
                            ? null
                            : StaffProfileResource::regionOptions()[$state] ?? $state),
                    TextEntry::make('date_of_birth')->label(__('staff::filament.profile.fields.date_of_birth'))->date(),
                    TextEntry::make('hired_at')->label(__('staff::filament.profile.fields.hired_at'))->date(),
                    TextEntry::make('terminated_at')->label(__('staff::filament.profile.fields.terminated_at'))->date(),
                    TextEntry::make('specializations')
                        ->label(__('staff::filament.profile.fields.specializations'))
                        ->badge()
                        ->separator(','),
                    TextEntry::make('bio')
                        ->label(__('staff::filament.profile.fields.bio'))
                        ->formatStateUsing(fn (mixed $state): string => self::localizedValue($state))
                        ->columnSpanFull(),
                ])->columns(3),

            Tabs::make(__('staff::admin.hub.title'))
                ->persistTabInQueryString('teacher-hub')
                ->tabs([
                    Tab::make(__('staff::admin.hub.account'))
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            $this->repeatable('account', [
                                TextEntry::make('name')->label(__('staff::admin.hub.fields.name')),
                                TextEntry::make('username')->label(__('staff::admin.hub.fields.username')),
                                TextEntry::make('email')->label(__('staff::admin.hub.fields.email')),
                                TextEntry::make('phone')->label(__('staff::admin.hub.fields.phone')),
                                TextEntry::make('status')->label(__('staff::admin.hub.fields.status'))->badge(),
                            ]),
                        ]),

                    Tab::make(__('staff::admin.hub.qualifications'))
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
                            $this->repeatable('qualifications', [
                                TextEntry::make('program')->label(__('staff::admin.hub.fields.program')),
                                TextEntry::make('course')->label(__('staff::admin.hub.fields.course')),
                                TextEntry::make('session_mode')->label(__('staff::admin.hub.fields.session_mode'))->badge(),
                            ]),
                        ]),

                    Tab::make(__('staff::admin.hub.contracts'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make(__('staff::admin.hub.contracts_list'))
                                ->schema([
                                    $this->repeatable('contracts', [
                                        TextEntry::make('basis')->label(__('staff::admin.hub.fields.basis'))->badge(),
                                        TextEntry::make('effective_from')->label(__('staff::admin.hub.fields.effective_from'))->date(),
                                        TextEntry::make('effective_to')->label(__('staff::admin.hub.fields.effective_to'))->date(),
                                        TextEntry::make('base_amount')->label(__('staff::admin.hub.fields.base_amount')),
                                        TextEntry::make('monthly_target_sessions')->label(__('staff::admin.hub.fields.monthly_target_sessions')),
                                        TextEntry::make('target_admin_tasks')->label(__('staff::admin.hub.fields.target_admin_tasks')),
                                        TextEntry::make('target_training_sessions')->label(__('staff::admin.hub.fields.target_training_sessions')),
                                    ]),
                                ]),
                            Section::make(__('staff::admin.hub.rates_list'))
                                ->schema([
                                    $this->repeatable('rates', [
                                        TextEntry::make('scope')->label(__('staff::admin.hub.fields.scope'))->badge(),
                                        TextEntry::make('amount')->label(__('staff::admin.hub.fields.amount')),
                                        TextEntry::make('program')->label(__('staff::admin.hub.fields.program')),
                                        TextEntry::make('course')->label(__('staff::admin.hub.fields.course')),
                                        TextEntry::make('session_type')->label(__('staff::admin.hub.fields.session_mode')),
                                        TextEntry::make('effective_from')->label(__('staff::admin.hub.fields.effective_from'))->date(),
                                        TextEntry::make('effective_to')->label(__('staff::admin.hub.fields.effective_to'))->date(),
                                    ]),
                                ]),
                        ]),

                    Tab::make(__('staff::admin.hub.groups'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            $this->repeatable('groups', [
                                TextEntry::make('group')->label(__('staff::admin.hub.fields.group')),
                                TextEntry::make('code')->label(__('staff::admin.hub.fields.code'))->copyable(),
                                TextEntry::make('course')->label(__('staff::admin.hub.fields.course')),
                                TextEntry::make('role')->label(__('staff::admin.hub.fields.role'))->badge(),
                                TextEntry::make('assigned_from')->label(__('staff::admin.hub.fields.assigned_from'))->date(),
                                TextEntry::make('assigned_to')->label(__('staff::admin.hub.fields.assigned_to'))->date(),
                            ]),
                        ]),

                    Tab::make(__('staff::admin.hub.availability'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            $this->repeatable('availability', [
                                TextEntry::make('weekday')->label(__('staff::admin.hub.fields.weekday')),
                                TextEntry::make('time')->label(__('staff::admin.hub.fields.time')),
                                TextEntry::make('timezone')->label(__('staff::admin.hub.fields.timezone')),
                                TextEntry::make('status')->label(__('staff::admin.hub.fields.status'))->badge(),
                                TextEntry::make('decision_reason')->label(__('staff::admin.availability.reason')),
                                TextEntry::make('effective_from')->label(__('staff::admin.hub.fields.effective_from'))->date(),
                                TextEntry::make('effective_to')->label(__('staff::admin.hub.fields.effective_to'))->date(),
                            ]),
                        ]),

                    Tab::make(__('staff::admin.hub.sessions'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            // للعرض فقط — أي إجراء على الحصة يتم من مركز عمليات الحصة.
                            $this->repeatable('sessions', [
                                TextEntry::make('title')->label(__('staff::admin.hub.fields.title')),
                                TextEntry::make('course')->label(__('staff::admin.hub.fields.course')),
                                TextEntry::make('group')->label(__('staff::admin.hub.fields.group')),
                                TextEntry::make('status')->label(__('staff::admin.hub.fields.status'))->badge(),
                                TextEntry::make('scheduled_start')->label(__('staff::admin.hub.fields.scheduled_start'))->dateTime(),
                                TextEntry::make('id')
                                    ->label(__('staff::admin.hub.open_session'))
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn (): string => __('staff::admin.hub.open_session'))
                                    ->url(fn (?string $state): ?string => $this->sessionHubUrl($state))
                                    ->openUrlInNewTab(),
                            ]),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    private function accountSummary(): ?UserSummary
    {
        return app(UserQueryService::class)->findSummary((string) $this->record->user_id);
    }

    private function avatarPresentation(): AvatarPresentation
    {
        /** @var StaffProfile $record */
        $record = $this->record;

        return app(AvatarQueries::class)->resolve(
            $this->accountSummary()?->avatarPath,
            $record->gender?->value,
        );
    }

    private function openEditAction(): Action
    {
        return Action::make('open_edit')
            ->label(__('staff::filament.profile.resources.actions.edit'))
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
            ->url(fn (): string => self::$resource::getUrl('edit', ['record' => $this->record->getKey()]));
    }

    private function editAccountAction(): Action
    {
        return Action::make('edit_account')
            ->label(__('staff::admin.hub.edit_account'))
            ->icon('heroicon-o-user')
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.update'))
            ->schema($this->accountFields())
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
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
                ), __('staff::admin.hub.account_saved'));

                $this->hubData = null;
            });
    }

    private function changeAccountStatusAction(): Action
    {
        return Action::make('change_account_status')
            ->label(__('staff::admin.hub.change_status'))
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn (): bool => (bool) auth()->user()?->can('identity.users.change_status'))
            ->schema([
                Select::make('status')
                    ->label(__('staff::admin.hub.fields.status'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->accountStatusOptions())
                    ->required(),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(UserAccountOperations::class)->changeStatus(
                    organizationId: (string) $record->organization_id,
                    userId: (string) $record->user_id,
                    status: (string) $data['status'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.hub.status_saved'));

                $this->hubData = null;
            });
    }

    private function assignQualificationsAction(): Action
    {
        return Action::make('assign_qualifications')
            ->label(__('staff::admin.qualifications.assign_action'))
            ->icon('heroicon-o-academic-cap')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
            ->schema([
                Select::make('course_ids')
                    ->label(__('staff::admin.onboarding.fields.courses'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->allCourseOptions($this->organizationId()))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->minItems(1),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(AssignTeacherQualificationsAction::class)->execute(
                    profile: $record,
                    courseIds: array_values((array) $data['course_ids']),
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.qualifications.assigned'));

                $this->hubData = null;
            });
    }

    private function revokeQualificationAction(): Action
    {
        return Action::make('revoke_qualification')
            ->label(__('staff::admin.qualifications.revoke_action'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
            ->schema([
                Select::make('course_id')
                    ->label(__('staff::admin.onboarding.fields.courses'))
                    ->options(function (): array {
                        /** @var StaffProfile $record */
                        $record = $this->record;
                        $qualifications = $this->hub($record, 'qualifications');

                        return array_column($qualifications, 'course', 'id');
                    })
                    ->required(),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->helperText(__('staff::admin.qualifications.revoke_reason_help'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(RevokeTeacherQualificationAction::class)->execute(
                    profile: $record,
                    courseId: (string) $data['course_id'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.qualifications.revoked'));

                $this->hubData = null;
            });
    }

    private function newContractAction(): Action
    {
        return Action::make('new_contract')
            ->label(__('staff::admin.contracts.new_action'))
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
            ->schema([
                Select::make('basis')
                    ->label(__('staff::admin.onboarding.fields.contract_basis'))
                    ->options(collect(ContractBasis::cases())->mapWithKeys(
                        fn (ContractBasis $basis): array => [$basis->value => $basis->label()],
                    )->all())
                    ->live()
                    ->required(),
                Select::make('currency')
                    ->label(__('staff::admin.onboarding.fields.currency'))
                    ->options(collect((array) config('staff.currency.supported'))->mapWithKeys(
                        static fn (string $currency): array => [$currency => $currency],
                    )->all())
                    ->default((string) config('staff.currency.default'))
                    ->required(),
                DatePicker::make('effective_from')
                    ->label(__('staff::admin.hub.fields.effective_from'))
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('effective_to')
                    ->label(__('staff::admin.hub.fields.effective_to'))
                    ->after('effective_from'),
                TextInput::make('base_amount_major')
                    ->label(__('staff::admin.hub.fields.base_amount'))
                    ->inputMode('decimal')
                    ->rule('decimal:0,2')
                    ->minValue(0)
                    ->visible(fn (callable $get): bool => ContractBasis::tryFrom((string) $get('basis'))?->requiresBaseAmount() === true)
                    ->required(fn (callable $get): bool => ContractBasis::tryFrom((string) $get('basis'))?->requiresBaseAmount() === true),
                TextInput::make('monthly_target_sessions')
                    ->label(__('staff::admin.hub.fields.monthly_target_sessions'))
                    ->integer()
                    ->minValue(0),
                TextInput::make('target_admin_tasks')
                    ->label(__('staff::admin.hub.fields.target_admin_tasks'))
                    ->integer()
                    ->minValue(0),
                TextInput::make('target_training_sessions')
                    ->label(__('staff::admin.hub.fields.target_training_sessions'))
                    ->integer()
                    ->minValue(0),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->helperText(__('staff::admin.contracts.append_only_help'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;
                $currency = strtoupper((string) $data['currency']);

                $this->guard(fn () => app(CreateTeacherContract::class)->execute(
                    profile: $record,
                    basis: ContractBasis::from((string) $data['basis']),
                    effectiveFrom: (string) $data['effective_from'],
                    effectiveTo: isset($data['effective_to']) ? (string) $data['effective_to'] : null,
                    baseAmount: filled($data['base_amount_major'] ?? null)
                        ? Money::fromMajor((string) $data['base_amount_major'], $currency)
                        : null,
                    monthlyTargetSessions: isset($data['monthly_target_sessions']) ? (int) $data['monthly_target_sessions'] : null,
                    targetAdminTasks: isset($data['target_admin_tasks']) ? (int) $data['target_admin_tasks'] : null,
                    targetTrainingSessions: isset($data['target_training_sessions']) ? (int) $data['target_training_sessions'] : null,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.contracts.created'));

                $this->hubData = null;
            });
    }

    private function newRateAction(): Action
    {
        return Action::make('new_rate')
            ->label(__('staff::admin.contracts.new_rate_action'))
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
            ->schema([
                Select::make('contract_id')
                    ->label(__('staff::admin.contracts.contract'))
                    ->options(function (): array {
                        /** @var StaffProfile $record */
                        $record = $this->record;
                        $contracts = $this->hub($record, 'contracts');

                        return collect($contracts)
                            ->mapWithKeys(static fn (array $contract): array => [
                                (string) $contract['id'] => __('staff::enums.contract_basis.'.self::basisValue($contract)).' · '
                                    .__('staff::admin.hub.fields.effective_from').' '.(string) $contract['effective_from']
                                    .' — '.(string) ($contract['effective_to'] ?? __('staff::filament.common.active')),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('scope')
                    ->label(__('staff::admin.hub.fields.scope'))
                    ->options(collect(RateScope::cases())->mapWithKeys(
                        fn (RateScope $scope): array => [$scope->value => $scope->label()],
                    )->all())
                    ->live()
                    ->required(),
                TextInput::make('amount_major')
                    ->label(__('staff::admin.hub.fields.amount'))
                    ->inputMode('decimal')
                    ->rule('decimal:0,2')
                    ->minValue(0.01)
                    ->required(),
                Select::make('currency')
                    ->label(__('staff::admin.onboarding.fields.currency'))
                    ->options(collect((array) config('staff.currency.supported'))->mapWithKeys(
                        static fn (string $currency): array => [$currency => $currency],
                    )->all())
                    ->default((string) config('staff.currency.default'))
                    ->required(),
                Select::make('program_id')
                    ->label(__('staff::admin.hub.fields.program'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->programOptions($this->organizationId()))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('course_id', null);
                    })
                    ->visible(fn (callable $get): bool => RateScope::tryFrom((string) $get('scope'))?->requiresProgram() === true)
                    ->required(fn (callable $get): bool => RateScope::tryFrom((string) $get('scope'))?->requiresProgram() === true),
                Select::make('course_id')
                    ->label(__('staff::admin.hub.fields.course'))
                    ->options(function (callable $get): array {
                        $programId = $get('program_id');
                        $queries = app(ProfileAdministrationQueryService::class);

                        return is_string($programId) && $programId !== ''
                            ? $queries->courseOptions($this->organizationId(), $programId)
                            : [];
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get): bool => RateScope::tryFrom((string) $get('scope'))?->requiresCourse() === true)
                    ->required(fn (callable $get): bool => RateScope::tryFrom((string) $get('scope'))?->requiresCourse() === true),
                DatePicker::make('effective_from')
                    ->label(__('staff::admin.hub.fields.effective_from'))
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('effective_to')
                    ->label(__('staff::admin.hub.fields.effective_to'))
                    ->after('effective_from'),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->helperText(__('staff::admin.contracts.append_only_help'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var TeacherContract|null $contract */
                $contract = TeacherContract::query()
                    ->whereKey((string) $data['contract_id'])
                    ->where('organization_id', $this->organizationId())
                    ->first();

                if ($contract === null) {
                    Notification::make()->title(__('staff::errors.organization_mismatch'))->danger()->send();

                    return;
                }

                $this->guard(fn () => app(AddTeacherRate::class)->execute(
                    contract: $contract,
                    scope: RateScope::from((string) $data['scope']),
                    amount: Money::fromMajor((string) $data['amount_major'], strtoupper((string) $data['currency'])),
                    effectiveFrom: (string) $data['effective_from'],
                    effectiveTo: isset($data['effective_to']) ? (string) $data['effective_to'] : null,
                    programId: filled($data['program_id'] ?? null) ? (string) $data['program_id'] : null,
                    courseId: filled($data['course_id'] ?? null) ? (string) $data['course_id'] : null,
                    sessionType: null,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.contracts.rate_created'));

                $this->hubData = null;
            });
    }

    private function assignGroupAction(): Action
    {
        return Action::make('assign_group')
            ->label(__('staff::admin.groups.assign_action'))
            ->icon('heroicon-o-user-group')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('group.manage'))
            ->schema([
                Select::make('program_id')
                    ->label(__('staff::admin.hub.fields.program'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->programOptions($this->organizationId()))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('course_id', null);
                        $set('group_id', null);
                    })
                    ->required(),
                Select::make('course_id')
                    ->label(__('staff::admin.hub.fields.course'))
                    ->options(function (callable $get): array {
                        $programId = $get('program_id');

                        return is_string($programId) && $programId !== ''
                            ? app(ProfileAdministrationQueryService::class)->courseOptions($this->organizationId(), $programId)
                            : [];
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('group_id', null);
                    }),
                Select::make('group_id')
                    ->label(__('staff::admin.hub.fields.group'))
                    ->options(function (callable $get): array {
                        $programId = $get('program_id');
                        $courseId = $get('course_id');

                        return is_string($programId) && $programId !== ''
                            ? app(ProfileAdministrationQueryService::class)->placementGroupOptions(
                                $this->organizationId(),
                                $programId,
                                is_string($courseId) && $courseId !== '' ? $courseId : null,
                            )
                            : [];
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('role')
                    ->label(__('staff::admin.hub.fields.role'))
                    ->options(collect(['lead', 'assistant', 'substitute'])->mapWithKeys(
                        static fn (string $role): array => [$role => __('groups::status.teacher_role.'.$role)],
                    )->all())
                    ->default('lead')
                    ->required(),
                DatePicker::make('assigned_from')
                    ->label(__('staff::admin.hub.fields.assigned_from'))
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('assigned_to')
                    ->label(__('staff::admin.hub.fields.assigned_to'))
                    ->after('assigned_from'),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(GroupAssignmentOperations::class)->assignTeacher(
                    organizationId: $this->organizationId(),
                    groupId: (string) $data['group_id'],
                    staffProfileId: (string) $record->getKey(),
                    courseId: filled($data['course_id'] ?? null) ? (string) $data['course_id'] : null,
                    role: (string) $data['role'],
                    assignedFrom: (string) $data['assigned_from'],
                    assignedTo: isset($data['assigned_to']) ? (string) $data['assigned_to'] : null,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.groups.assigned'));

                $this->hubData = null;
            });
    }

    private function endAssignmentAction(): Action
    {
        return Action::make('end_assignment')
            ->label(__('staff::admin.groups.end_assignment_action'))
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => (bool) auth()->user()?->can('group.manage'))
            ->schema([
                Select::make('assignment_id')
                    ->label(__('staff::admin.groups.assignment'))
                    ->options(function (): array {
                        /** @var StaffProfile $record */
                        $record = $this->record;
                        $assignments = $this->hub($record, 'groups');
                        $open = [];

                        foreach ($assignments as $assignment) {
                            if (empty($assignment['assigned_to'])) {
                                $open[(string) $assignment['id']] = (string) $assignment['group']
                                    .' · '.(string) $assignment['role']
                                    .' ('.(string) $assignment['assigned_from'].')';
                            }
                        }

                        return $open;
                    })
                    ->required(),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guard(fn () => app(GroupAssignmentOperations::class)->unassignTeacher(
                    organizationId: $this->organizationId(),
                    assignmentId: (string) $data['assignment_id'],
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.groups.assignment_ended'));

                $this->hubData = null;
            });
    }

    private function addAvailabilityAction(): Action
    {
        return Action::make('add_availability')
            ->label(__('staff::admin.availability.add_action'))
            ->icon('heroicon-o-clock')
            ->color('success')
            ->visible(fn (): bool => (bool) auth()->user()?->can('staff.availability.approve'))
            ->schema([
                Select::make('weekday')
                    ->label(__('staff::admin.hub.fields.weekday'))
                    ->options(self::weekdayOptions())
                    ->required(),
                TextInput::make('start_time')
                    ->label(__('staff::validation.attributes.start_time'))
                    ->placeholder('09:00')
                    ->rule('date_format:H:i')
                    ->required(),
                TextInput::make('end_time')
                    ->label(__('staff::validation.attributes.end_time'))
                    ->placeholder('12:00')
                    ->rule('date_format:H:i')
                    ->after('start_time')
                    ->required(),
                TextInput::make('timezone')
                    ->label(__('staff::admin.hub.fields.timezone'))
                    ->default(config('app.timezone'))
                    ->rule('timezone:all')
                    ->required(),
                DatePicker::make('effective_from')
                    ->label(__('staff::admin.hub.fields.effective_from'))
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('effective_to')
                    ->label(__('staff::admin.hub.fields.effective_to'))
                    ->afterOrEqual('effective_from'),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var StaffProfile $record */
                $record = $this->record;

                $this->guard(fn () => app(SetTeacherAvailability::class)->execute(
                    profile: $record,
                    weekday: (int) $data['weekday'],
                    startTime: (string) $data['start_time'],
                    endTime: (string) $data['end_time'],
                    timezone: (string) $data['timezone'],
                    effectiveFrom: (string) $data['effective_from'],
                    effectiveTo: isset($data['effective_to']) ? (string) $data['effective_to'] : null,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.availability.created'));

                $this->hubData = null;
            });
    }

    private function cancelAvailabilityAction(): Action
    {
        return Action::make('cancel_availability')
            ->label(__('staff::admin.availability.cancel_action'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => $this->pendingAvailabilityOptions() !== []
                && (auth()->user()?->can('staff.availability.approve') ?? false))
            ->schema([
                Select::make('availability_id')
                    ->label(__('staff::admin.availability.slot'))
                    ->options(fn (): array => $this->pendingAvailabilityOptions())
                    ->required(),
                Textarea::make('reason')
                    ->label(__('staff::filament.profile.fields.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var TeacherAvailability|null $availability */
                $availability = TeacherAvailability::query()
                    ->forProfile((string) $this->record->getKey())
                    ->whereKey((string) $data['availability_id'])
                    ->first();

                if ($availability === null) {
                    Notification::make()->title(__('staff::errors.organization_mismatch'))->danger()->send();

                    return;
                }

                $this->guard(fn () => app(RemoveTeacherAvailability::class)->execute(
                    availability: $availability,
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                ), __('staff::admin.availability.cancelled'));

                $this->hubData = null;
            });
    }

    private function decideAvailabilityAction(): Action
    {
        return Action::make('decide_availability')
            ->label(__('staff::admin.availability.decision_action'))
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (): bool => $this->pendingAvailabilityOptions() !== []
                && (auth()->user()?->can('staff.availability.approve') ?? false))
            ->schema([
                Select::make('availability_id')
                    ->label(__('staff::admin.availability.slot'))
                    ->options(fn (): array => $this->pendingAvailabilityOptions())
                    ->required(),
                Select::make('decision')
                    ->label(__('staff::admin.availability.decision'))
                    ->options([
                        TeacherAvailabilityApprovalStatus::Approved->value => __('staff::admin.availability.approval_status.approved'),
                        TeacherAvailabilityApprovalStatus::Rejected->value => __('staff::admin.availability.approval_status.rejected'),
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label(__('staff::admin.availability.reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var TeacherAvailability $availability */
                $availability = TeacherAvailability::query()
                    ->forProfile((string) $this->record->getKey())
                    ->findOrFail((string) $data['availability_id']);

                $this->authorize('approve', $availability);

                app(DecideTeacherAvailabilityAction::class)->execute(
                    availability: $availability,
                    decision: TeacherAvailabilityApprovalStatus::from((string) $data['decision']),
                    actorId: $this->actorId(),
                    reason: (string) $data['reason'],
                );

                $this->hubData = null;
                Notification::make()
                    ->title(__('staff::admin.availability.decision_saved'))
                    ->success()
                    ->send();
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

    /** @return array<int|string, string> */
    private static function weekdayOptions(): array
    {
        $options = [];

        foreach (range(0, 6) as $day) {
            $options[$day] = __('staff::admin.availability.weekdays.'.$day);
        }

        return $options;
    }

    /** @param array<string, mixed> $contract */
    private static function basisValue(array $contract): string
    {
        $basis = $contract['basis_value'] ?? null;

        return is_string($basis) && $basis !== '' ? $basis : '';
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
            ->placeholder(__('staff::admin.hub.empty'))
            ->getStateUsing(fn (StaffProfile $record): array => $this->hub($record, $section))
            ->schema($schema)
            ->columns(3);
    }

    /** @return array<int, Component> */
    private function accountFields(): array
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        return [
            TextInput::make('name')
                ->label(__('staff::admin.hub.fields.name'))
                ->maxLength(255)
                ->required(),
            TextInput::make('phone')
                ->label(__('staff::admin.hub.fields.phone'))
                ->tel()
                ->maxLength(32),
            Select::make('locale')
                ->label(__('staff::admin.onboarding.fields.locale'))
                ->options(Locales::options('identity::locales.'))
                ->required(),
            TextInput::make('timezone')
                ->label(__('staff::admin.onboarding.fields.timezone'))
                ->default(fn (): string => (string) (data_get($user, 'timezone') ?: config('app.timezone')))
                ->rule('timezone:all')
                ->required(),
            Textarea::make('reason')
                ->label(__('staff::filament.profile.fields.reason'))
                ->maxLength(2000)
                ->required(),
        ];
    }

    /** @return list<mixed> */
    private function hub(StaffProfile $record, string $section): array
    {
        $this->hubData ??= app(ProfileAdministrationQueryService::class)->teacherHub(
            (string) $record->organization_id,
            (string) $record->getKey(),
            (string) $record->user_id,
        );

        $data = $this->hubData[$section] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    private static function localizedValue(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) ($value ?? '');
        }

        $localized = $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value);

        return is_scalar($localized) ? (string) $localized : '';
    }

    /** @return array<string, string> */
    private function pendingAvailabilityOptions(): array
    {
        return TeacherAvailability::query()
            ->forProfile((string) $this->record->getKey())
            ->where('approval_status', TeacherAvailabilityApprovalStatus::Pending)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(static fn (TeacherAvailability $availability): array => [
                (string) $availability->getKey() => __('staff::admin.availability.slot_label', [
                    'day' => __('staff::admin.availability.weekdays.'.$availability->weekday),
                    'start' => $availability->start_time,
                    'end' => $availability->end_time,
                    'timezone' => $availability->timezone,
                ]),
            ])
            ->all();
    }
}
