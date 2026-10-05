<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use Modules\Staff\Application\Actions\CreateTeacherOnboardingAction;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffAccountMode;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Shared\Codes\EntityCodeGenerator;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

final class CreateStaffProfile extends CreateRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('staff::admin.onboarding.steps.account'))
                    ->description(__('staff::admin.onboarding.steps.account_description'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Radio::make('account_mode')
                            ->label(__('staff::admin.onboarding.account_mode'))
                            ->options([
                                StaffAccountMode::NewAccount->value => __('staff::admin.onboarding.new_account'),
                                StaffAccountMode::ExistingAccount->value => __('staff::admin.onboarding.existing_account'),
                            ])
                            ->default(StaffAccountMode::NewAccount->value)
                            ->inline()
                            ->live()
                            ->required(),

                        Select::make('existing_user_id')
                            ->label(__('staff::admin.onboarding.existing_user'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->queries()->accountOptions(
                                $this->organizationId(),
                                $search,
                                StaffProfile::query()
                                    ->withTrashed()
                                    ->forOrganization($this->organizationId())
                                    ->pluck('user_id')
                                    ->map(static fn (mixed $id): string => (string) $id)
                                    ->all(),
                            ))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? $this->queries()->accountOptionLabel($this->organizationId(), $value)
                                : null)
                            ->helperText(__('staff::admin.onboarding.existing_user_help'))
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::ExistingAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::ExistingAccount->value),

                        TextInput::make('full_name')
                            ->label(__('staff::admin.onboarding.fields.full_name'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                if (!is_string($state) || trim($state) === '') {
                                    return;
                                }

                                $suggestion = $this->queries()->usernameSuggestions($this->organizationId(), $state)[0] ?? null;
                                if ($suggestion !== null) {
                                    $set('username', $suggestion);
                                }
                            })
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        TextInput::make('email')
                            ->label(__('staff::admin.onboarding.fields.email'))
                            ->email()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        TextInput::make('phone')
                            ->label(__('staff::admin.onboarding.fields.phone'))
                            ->tel()
                            ->maxLength(32)
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        TextInput::make('username')
                            ->label(__('staff::admin.onboarding.fields.username'))
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        TextInput::make('password')
                            ->label(__('staff::admin.onboarding.fields.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        TextInput::make('password_confirmation')
                            ->label(__('staff::admin.onboarding.fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->visible(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StaffAccountMode::NewAccount->value),

                        Select::make('locale')
                            ->label(__('staff::admin.onboarding.fields.locale'))
                            ->options(Locales::options('identity::locales.'))
                            ->default('ar')
                            ->required(),

                        TextInput::make('timezone')
                            ->label(__('staff::admin.onboarding.fields.timezone'))
                            ->default(fn (): string => (string) (data_get(auth()->user(), 'timezone') ?: config('app.timezone')))
                            ->required(),
                    ])->columns(2),

                Step::make(__('staff::admin.onboarding.steps.profile'))
                    ->description(__('staff::admin.onboarding.steps.profile_description'))
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('staff_code')
                            ->label(__('staff::filament.profile.fields.staff_code'))
                            // كود قصير جاهز (T001) — الأدمن يقبله كما هو أو يعدّله.
                            ->default(fn (EntityCodeGenerator $codes): string => $codes->next('staff'))
                            ->maxLength(8)
                            ->required()
                            ->unique('staff_profiles', 'staff_code'),

                        Select::make('employment_type')
                            ->label(__('staff::filament.profile.fields.employment_type'))
                            ->options(collect(EmploymentType::cases())->mapWithKeys(
                                fn (EmploymentType $type): array => [$type->value => $type->label()],
                            )->all())
                            ->required(),

                        Select::make('gender')
                            ->label(__('staff::filament.profile.fields.gender'))
                            ->options(collect(StaffGender::cases())->mapWithKeys(
                                fn (StaffGender $gender): array => [
                                    $gender->value => __('staff::filament.profile.gender_options.'.$gender->value),
                                ],
                            )->all())
                            ->required(),

                        Select::make('country_id')
                            ->label(__('staff::filament.profile.fields.country'))
                            ->options(StaffProfileResource::countryOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('region_id', null);
                            })
                            ->required(),

                        Select::make('region_id')
                            ->label(__('staff::filament.profile.fields.region'))
                            ->options(fn (Get $get): array => is_string($get('country_id'))
                                ? StaffProfileResource::regionOptions((string) $get('country_id'))
                                : [])
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('date_of_birth')
                            ->label(__('staff::filament.profile.fields.date_of_birth'))
                            ->maxDate(now()->subDay()->toDateString()),

                        DatePicker::make('hired_at')
                            ->label(__('staff::filament.profile.fields.hired_at'))
                            ->default(now()->toDateString())
                            ->required(),

                        TagsInput::make('specializations')
                            ->label(__('staff::filament.profile.fields.specializations')),

                        Textarea::make('bio')
                            ->label(__('staff::filament.profile.fields.bio'))
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])->columns(2),

                Step::make(__('staff::admin.onboarding.steps.contract'))
                    ->description(__('staff::admin.onboarding.steps.contract_description'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Select::make('contract_basis')
                            ->label(__('staff::admin.onboarding.fields.contract_basis'))
                            ->options(collect(ContractBasis::cases())->mapWithKeys(
                                fn (ContractBasis $basis): array => [$basis->value => $basis->label()],
                            )->all())
                            ->default(ContractBasis::PerSession->value)
                            ->live()
                            ->required(),

                        Select::make('currency')
                            ->label(__('staff::admin.onboarding.fields.currency'))
                            ->options(collect((array) config('staff.currency.supported'))->mapWithKeys(
                                static fn (string $currency): array => [$currency => $currency],
                            )->all())
                            ->default((string) config('staff.currency.default'))
                            ->required(),

                        DatePicker::make('contract_effective_from')
                            ->label(__('staff::admin.onboarding.fields.contract_effective_from'))
                            ->default(now()->toDateString())
                            ->required(),

                        DatePicker::make('contract_effective_to')
                            ->label(__('staff::admin.onboarding.fields.contract_effective_to'))
                            ->after('contract_effective_from'),

                        TextInput::make('base_amount_major')
                            ->label(__('staff::admin.onboarding.fields.base_amount'))
                            ->inputMode('decimal')
                            ->rule('decimal:0,2')
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => in_array($get('contract_basis'), [
                                ContractBasis::Salary->value,
                                ContractBasis::Hybrid->value,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('contract_basis'), [
                                ContractBasis::Salary->value,
                                ContractBasis::Hybrid->value,
                            ], true)),

                        TextInput::make('default_rate_major')
                            ->label(__('staff::admin.onboarding.fields.default_rate'))
                            ->helperText(__('staff::admin.onboarding.fields.default_rate_help'))
                            ->inputMode('decimal')
                            ->rule('decimal:0,2')
                            ->minValue(0.01)
                            ->visible(fn (Get $get): bool => in_array($get('contract_basis'), [
                                ContractBasis::PerSession->value,
                                ContractBasis::Hybrid->value,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('contract_basis'), [
                                ContractBasis::PerSession->value,
                                ContractBasis::Hybrid->value,
                            ], true)),

                        TextInput::make('monthly_target_sessions')
                            ->label(__('staff::admin.onboarding.fields.monthly_target_sessions'))
                            ->integer()
                            ->minValue(0),

                        TextInput::make('target_admin_tasks')
                            ->label(__('staff::admin.onboarding.fields.target_admin_tasks'))
                            ->integer()
                            ->minValue(0),

                        TextInput::make('target_training_sessions')
                            ->label(__('staff::admin.onboarding.fields.target_training_sessions'))
                            ->integer()
                            ->minValue(0),
                    ])->columns(2),

                Step::make(__('staff::admin.onboarding.steps.qualifications'))
                    ->description(__('staff::admin.onboarding.steps.qualifications_description'))
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Select::make('course_ids')
                            ->label(__('staff::admin.onboarding.fields.courses'))
                            ->options(fn (): array => $this->queries()->allCourseOptions($this->organizationId()))
                            ->multiple()
                            ->searchable()
                            ->preload(),

                        Textarea::make('qualification_notes')
                            ->label(__('staff::admin.onboarding.fields.qualification_notes'))
                            ->maxLength(2000),

                        Textarea::make('onboarding_reason')
                            ->label(__('staff::admin.onboarding.fields.onboarding_reason'))
                            ->helperText(__('staff::admin.onboarding.fields.onboarding_reason_help'))
                            ->maxLength(2000)
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateTeacherOnboardingAction::class)->execute(
                data: $data,
                organizationId: $this->organizationId(),
                actorId: (string) auth()->id(),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()
                ->title($violation->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('staff::admin.onboarding.created');
    }

    private function organizationId(): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }

    private function queries(): ProfileAdministrationQueryService
    {
        return app(ProfileAdministrationQueryService::class);
    }
}
