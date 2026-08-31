<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Modules\Guardians\Application\Actions\CreateGuardianOnboardingAction;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianAccountMode;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

final class CreateGuardianProfile extends CreateRecord
{
    protected static string $resource = GuardianProfileFilamentResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('guardians::admin.onboarding.steps.account'))
                    ->description(__('guardians::admin.onboarding.steps.account_description'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Radio::make('account_mode')
                            ->label(__('guardians::admin.onboarding.account_mode'))
                            ->options([
                                GuardianAccountMode::NewAccount->value => __('guardians::admin.onboarding.new_account'),
                                GuardianAccountMode::ExistingAccount->value => __('guardians::admin.onboarding.existing_account'),
                            ])
                            ->default(GuardianAccountMode::NewAccount->value)
                            ->inline()
                            ->live()
                            ->required(),

                        Select::make('existing_user_id')
                            ->label(__('guardians::admin.onboarding.existing_user'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->queries()->accountOptions(
                                $this->organizationId(),
                                $search,
                                GuardianProfile::query()
                                    ->withTrashed()
                                    ->forOrganization($this->organizationId())
                                    ->pluck('user_id')
                                    ->map(static fn (mixed $id): string => (string) $id)
                                    ->all(),
                            ))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? $this->queries()->accountOptionLabel($this->organizationId(), $value)
                                : null)
                            ->helperText(__('guardians::admin.onboarding.existing_user_help'))
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::ExistingAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::ExistingAccount->value),

                        TextInput::make('full_name')
                            ->label(__('guardians::admin.fields.name'))
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
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        TextInput::make('email')
                            ->label(__('guardians::admin.fields.email'))
                            ->email()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        TextInput::make('phone')
                            ->label(__('guardians::admin.fields.phone'))
                            ->tel()
                            ->maxLength(32)
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        TextInput::make('username')
                            ->label(__('guardians::admin.fields.username'))
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        TextInput::make('password')
                            ->label(__('guardians::admin.fields.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        TextInput::make('password_confirmation')
                            ->label(__('guardians::admin.fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->visible(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value)
                            ->required(fn (Get $get): bool => $get('account_mode') === GuardianAccountMode::NewAccount->value),

                        Select::make('locale')
                            ->label(__('guardians::admin.fields.locale'))
                            ->options(Locales::options('identity::locales.'))
                            ->default('ar')
                            ->required(),

                        TextInput::make('timezone')
                            ->label(__('guardians::admin.fields.timezone'))
                            ->default(fn (): string => (string) (data_get(auth()->user(), 'timezone') ?: config('app.timezone')))
                            ->required(),
                    ])->columns(2),

                Step::make(__('guardians::admin.onboarding.steps.profile'))
                    ->description(__('guardians::admin.onboarding.steps.profile_description'))
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('national_id_last4')
                            ->label(__('guardians::filament.profile.fields.national_id_last4'))
                            ->numeric()
                            ->length(4),
                        TextInput::make('occupation')
                            ->label(__('guardians::filament.profile.fields.occupation'))
                            ->maxLength(120),
                        Select::make('preferred_contact_channel')
                            ->label(__('guardians::filament.profile.fields.preferred_contact_channel'))
                            ->options(collect(ContactChannel::cases())->mapWithKeys(
                                fn (ContactChannel $channel): array => [$channel->value => $channel->label()],
                            )->all()),
                    ])->columns(2),

                Step::make(__('guardians::admin.onboarding.steps.student'))
                    ->description(__('guardians::admin.onboarding.steps.student_description'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Select::make('student_profile_id')
                            ->label(__('guardians::admin.fields.student'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->queries()->studentOptions(
                                $this->organizationId(),
                                $search,
                            ))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? $this->queries()->studentOptionLabel($this->organizationId(), $value)
                                : null)
                            ->helperText(__('guardians::admin.onboarding.student_optional'))
                            ->live(),

                        Select::make('relationship')
                            ->label(__('guardians::filament.link.fields.relationship'))
                            ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                                fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                            )->all())
                            ->visible(fn (Get $get): bool => is_string($get('student_profile_id')) && $get('student_profile_id') !== '')
                            ->required(fn (Get $get): bool => is_string($get('student_profile_id')) && $get('student_profile_id') !== ''),

                        Toggle::make('is_primary')
                            ->label(__('guardians::filament.link.fields.is_primary'))
                            ->visible(fn (Get $get): bool => is_string($get('student_profile_id')) && $get('student_profile_id') !== ''),

                        Toggle::make('can_act_for')
                            ->label(__('guardians::filament.link.fields.can_act_for'))
                            ->visible(fn (Get $get): bool => is_string($get('student_profile_id')) && $get('student_profile_id') !== ''),

                        Select::make('visible_sections')
                            ->label(__('guardians::filament.link.fields.visible_sections'))
                            ->options(collect((array) config('guardians.links.allowed_visible_sections'))->mapWithKeys(
                                static fn (string $section): array => [$section => __('guardians::admin.sections.'.$section)],
                            )->all())
                            ->multiple()
                            ->default((array) config('guardians.links.default_visible_sections'))
                            ->visible(fn (Get $get): bool => is_string($get('student_profile_id')) && $get('student_profile_id') !== ''),

                        Textarea::make('onboarding_reason')
                            ->label(__('guardians::admin.fields.reason'))
                            ->helperText(__('guardians::admin.onboarding.reason_help'))
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
            return app(CreateGuardianOnboardingAction::class)->execute(
                data: $data,
                organizationId: $this->organizationId(),
                actorId: (string) auth()->id(),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();
            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('guardians::admin.onboarding.created');
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
