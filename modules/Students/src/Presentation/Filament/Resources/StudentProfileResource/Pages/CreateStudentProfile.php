<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
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
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Domain\Enums\StudentAccountMode;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

final class CreateStudentProfile extends CreateRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('students::admin.onboarding.steps.account'))
                    ->description(__('students::admin.onboarding.steps.account_description'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Radio::make('account_mode')
                            ->label(__('students::admin.onboarding.account_mode'))
                            ->options([
                                StudentAccountMode::NewAccount->value => __('students::admin.onboarding.new_account'),
                                StudentAccountMode::ExistingAccount->value => __('students::admin.onboarding.existing_account'),
                            ])
                            ->default(StudentAccountMode::NewAccount->value)
                            ->inline()
                            ->live()
                            ->required(),

                        Select::make('existing_user_id')
                            ->label(__('students::admin.onboarding.existing_user'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->queries()->accountOptions(
                                $this->organizationId(),
                                $search,
                                StudentProfile::query()
                                    ->withTrashed()
                                    ->forOrganization($this->organizationId())
                                    ->pluck('user_id')
                                    ->map(static fn (mixed $id): string => (string) $id)
                                    ->all(),
                            ))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? $this->queries()->accountOptionLabel($this->organizationId(), $value)
                                : null)
                            ->helperText(__('students::admin.onboarding.existing_user_help'))
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::ExistingAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::ExistingAccount->value),

                        TextInput::make('full_name')
                            ->label(__('students::attributes.full_name'))
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
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        TextInput::make('email')
                            ->label(__('students::attributes.email'))
                            ->email()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        TextInput::make('phone')
                            ->label(__('students::attributes.phone'))
                            ->tel()
                            ->maxLength(32)
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        TextInput::make('username')
                            ->label(__('identity::labels.username'))
                            ->minLength((int) config('admission.username.min_length'))
                            ->maxLength((int) config('admission.username.max_length'))
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        TextInput::make('password')
                            ->label(__('identity::labels.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        TextInput::make('password_confirmation')
                            ->label(__('students::admin.onboarding.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->visible(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === StudentAccountMode::NewAccount->value),

                        Select::make('locale')
                            ->label(__('identity::labels.locale'))
                            ->options(Locales::options('identity::locales.'))
                            ->default('ar')
                            ->required(),

                        TextInput::make('timezone')
                            ->label(__('students::admin.onboarding.timezone'))
                            ->default(fn (): string => (string) (data_get(auth()->user(), 'timezone') ?: config('app.timezone')))
                            ->required(),
                    ])->columns(2),

                Step::make(__('students::admin.onboarding.steps.profile'))
                    ->description(__('students::admin.onboarding.steps.profile_description'))
                    ->icon('heroicon-o-identification')
                    ->schema([
                        DatePicker::make('date_of_birth')
                            ->label(__('students::attributes.date_of_birth'))
                            ->maxDate(now()->subDay()->toDateString())
                            ->required(),

                        Select::make('gender')
                            ->label(__('students::attributes.gender'))
                            ->options(collect(StudentGender::cases())
                                ->mapWithKeys(fn (StudentGender $gender): array => [$gender->value => $gender->label()])
                                ->all())
                            ->required(),

                        Select::make('country_id')
                            ->label(__('students::attributes.country_id'))
                            ->options(StudentProfileResource::countryOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('region_id', null);
                            })
                            ->required(),

                        Select::make('region_id')
                            ->label(__('students::attributes.region_id'))
                            ->options(fn (Get $get): array => is_string($get('country_id'))
                                ? StudentProfileResource::regionOptions((string) $get('country_id'))
                                : [])
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('nationality')
                            ->label(__('students::attributes.nationality'))
                            ->options(StudentProfileResource::nationalityOptions())
                            ->searchable()
                            ->preload(),

                        TextInput::make('city')
                            ->label(__('students::attributes.city'))
                            ->maxLength(120),

                        Select::make('preferred_language')
                            ->label(__('students::attributes.preferred_language'))
                            ->options(Locales::options('students::languages.'))
                            ->default('ar'),
                    ])->columns(2),

                Step::make(__('students::admin.onboarding.steps.admission'))
                    ->description(__('students::admin.onboarding.steps.admission_description'))
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Select::make('preferred_program_id')
                            ->label(__('students::attributes.preferred_program_id'))
                            ->options(fn (): array => $this->queries()->programOptions($this->organizationId()))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('preferred_course_id', null);
                            })
                            ->required(),

                        Select::make('preferred_course_id')
                            ->label(__('students::attributes.preferred_course_id'))
                            ->options(fn (Get $get): array => $this->queries()->courseOptions(
                                $this->organizationId(),
                                is_string($get('preferred_program_id')) ? $get('preferred_program_id') : null,
                            ))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('acceptance_reason')
                            ->label(__('students::admin.onboarding.acceptance_reason'))
                            ->helperText(__('students::admin.onboarding.acceptance_reason_help'))
                            ->maxLength(2000)
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label(__('students::attributes.notes'))
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateStudentOnboardingAction::class)->execute(
                data: $data,
                organizationId: $this->organizationId(),
                actorId: (string) auth()->id(),
            );
        } catch (BusinessRuleViolation $violation) {
            $this->getFailureNotification($violation->getMessage())->send();
            throw new Halt;
        }
    }

    private function getFailureNotification(string $message): Notification
    {
        return Notification::make()
            ->title($message)
            ->danger();
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

    protected function getCreatedNotificationTitle(): string
    {
        return __('students::admin.onboarding.created');
    }
}
