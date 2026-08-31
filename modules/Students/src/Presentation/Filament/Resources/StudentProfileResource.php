<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\DTOs\UserSummary;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

/**
 * إدارة ملفات الطلاب في لوحة التحكم — كل النصوص عبر ملفات الترجمة.
 */
final class StudentProfileResource extends Resource
{
    protected static ?string $model = StudentProfile::class;

    protected static ?string $slug = 'students';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::filament.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::filament.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::filament.plural_model_label');
    }

    /** صفحة الإنشاء تنفّذ الحساب والطلب والقبول؛ لا تحفظ StudentProfile مباشرة. */
    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('student.create');
    }

    /** يمنع دخول الطالب صفحة Filament الحساسة اعتمادًا على الملكية وحدها. */
    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('student.update')
            && parent::canEdit($record);
    }

    /**
     * نطاق مؤسسة المستخدم دائمًا؛ غياب المؤسسة يغلق الاستعلام بدل كشف الجميع.
     *
     * @return Builder<StudentProfile>
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        /** @var Builder<StudentProfile> $query */
        $query = parent::getEloquentQuery();

        return $organizationId === ''
            ? $query->whereRaw('1 = 0')
            : $query->forOrganization($organizationId)->with('registrationApplication');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('student_code')
                    ->label(__('students::attributes.student_code'))
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('date_of_birth')
                    ->label(__('students::attributes.date_of_birth'))
                    ->maxDate(now()->toDateString())
                    ->nullable(),

                Select::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->options(collect(StudentGender::cases())
                        ->mapWithKeys(fn (StudentGender $g): array => [$g->value => $g->label()])
                        ->all())
                    ->nullable(),

                Select::make('nationality')
                    ->label(__('students::attributes.nationality'))
                    ->options(fn (?StudentProfile $record): array => self::nationalityOptions($record?->nationality))
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->options(self::countryOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable(),

                Select::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->options(function (callable $get): array {
                        $countryId = $get('country_id');

                        return is_string($countryId) ? self::regionOptions($countryId) : [];
                    })
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('city')
                    ->label(__('students::attributes.city'))
                    ->maxLength(120)
                    ->nullable(),

                Select::make('preferred_language')
                    ->label(__('students::attributes.preferred_language'))
                    ->options(Locales::options('students::languages.'))
                    ->nullable(),

                Textarea::make('notes')
                    ->label(__('students::attributes.notes'))
                    ->columnSpanFull()
                    ->maxLength(5000)
                    ->nullable(),

                // سبب إداري إلزامي — يُستهلك في التدقيق ولا يُخزَّن مع الملف.
                Textarea::make('reason')
                    ->label(__('students::attributes.reason'))
                    ->helperText(__('students::admin.profile.reason_help'))
                    ->columnSpanFull()
                    ->maxLength(2000)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label(__('students::filament.student_name'))
                    ->circular()
                    ->state(fn (StudentProfile $record): ?string => self::avatarUrls()[$record->user_id] ?? null)
                    ->defaultImageUrl(fn (StudentProfile $record): string => app(AvatarQueries::class)
                        ->defaultUrl($record->gender?->value))
                    ->alt(function (StudentProfile $record): string {
                        $name = (string) ($record->registrationApplication?->full_name ?? $record->student_code);

                        return __('identity::avatars.alt', ['name' => $name]);
                    })
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->grow(false),

                TextColumn::make('student_code')
                    ->label(__('students::attributes.student_code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registrationApplication.full_name')
                    ->label(__('students::filament.student_name'))
                    ->searchable(),

                TextColumn::make('registrationApplication.status')
                    ->label(__('students::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (?RegistrationStatus $state): ?string => $state?->label()),

                TextColumn::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?StudentGender $state): ?string => $state?->label())
                    ->sortable(),

                TextColumn::make('city')
                    ->label(__('students::attributes.city'))
                    ->toggleable(),

                TextColumn::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? self::countryOptions()[$state] ?? $state : null)
                    ->toggleable(),

                TextColumn::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? self::regionOptions()[$state] ?? $state : null)
                    ->toggleable(),

                TextColumn::make('joined_at')
                    ->label(__('students::attributes.joined_at'))
                    ->date()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->label(__('students::filament.archived_at'))
                    ->dateTime()
                    ->placeholder(__('students::filament.not_archived'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->label(__('students::attributes.gender'))
                    ->options(collect(StudentGender::cases())
                        ->mapWithKeys(fn (StudentGender $g): array => [$g->value => $g->label()])
                        ->all()),

                SelectFilter::make('country_id')
                    ->label(__('students::registration.filters.country'))
                    ->options(self::countryOptions())
                    ->searchable(),

                SelectFilter::make('region_id')
                    ->label(__('students::registration.filters.region'))
                    ->options(self::regionOptions())
                    ->searchable(),

                SelectFilter::make('registration_status')
                    ->label(__('students::registration.filters.status'))
                    ->options(self::statusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return $query->when(
                            is_string($status) && $status !== '',
                            fn (Builder $studentQuery): Builder => $studentQuery->whereHas(
                                'registrationApplication',
                                fn (Builder $applicationQuery): Builder => $applicationQuery->where('status', $status),
                            ),
                        );
                    }),

                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                self::placementAction(),
            ])
            ->bulkActions([]);
    }

    public static function placementAction(): Action
    {
        return Action::make('place_student')
            ->label(__('students::admin.placement.action'))
            ->icon('heroicon-o-user-plus')
            ->color('info')
            ->authorize(fn (): bool => self::canPlaceStudent())
            ->visible(fn (StudentProfile $record): bool => $record->registrationApplication?->status === RegistrationStatus::WaitingAssignment)
            ->form([
                Select::make('program_id')
                    ->label(__('students::admin.placement.program'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->programOptions(self::organizationId()))
                    ->default(fn (StudentProfile $record): ?string => $record->registrationApplication?->preferred_program_id)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('course_id', null);
                        $set('group_id', null);
                    })
                    ->required(),

                Select::make('course_id')
                    ->label(__('students::admin.placement.course'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->courseOptions(
                        self::organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                    ))
                    ->default(fn (StudentProfile $record): ?string => $record->registrationApplication?->preferred_course_id)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('group_id', null);
                    })
                    ->required(),

                Select::make('group_id')
                    ->label(__('students::admin.placement.group'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->placementGroupOptions(
                        self::organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                        is_string($get('course_id')) ? $get('course_id') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),

                Textarea::make('reason')
                    ->label(__('students::admin.placement.reason'))
                    ->helperText(__('students::admin.placement.reason_help'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (StudentProfile $record, array $data): void {
                try {
                    app(AssignStudentToGroupAction::class)->execute(
                        actorOrganizationId: self::organizationId(),
                        studentProfileId: (string) $record->getKey(),
                        programId: (string) $data['program_id'],
                        groupId: (string) $data['group_id'],
                        courseId: (string) $data['course_id'],
                        actorId: (string) auth()->id(),
                        correlationId: request()->header('X-Correlation-Id'),
                        reason: (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()
                        ->title($violation->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('students::admin.placement.success'))
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentProfiles::route('/'),
            'create' => Pages\CreateStudentProfile::route('/create'),
            'view' => Pages\ViewStudentProfile::route('/{record}'),
            'edit' => Pages\EditStudentProfile::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    public static function countryOptions(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        return collect($geography->countries())
            ->mapWithKeys(fn (CountryData $country): array => [
                $country->id => self::localizedName($country->name),
            ])
            ->all();
    }

    /**
     * خيارات الجنسية — المفتاح رمز ISO 3166-1 alpha-2 كما يخزّنه العمود.
     * $current يُبقي قيمة محفوظة خارج قائمة الدول المفعّلة ظاهرة بدل أن تختفي من الحقل.
     *
     * @return array<string, string>
     */
    public static function nationalityOptions(?string $current = null): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        $options = collect($geography->countries())
            ->mapWithKeys(fn (CountryData $country): array => [
                $country->iso2 => self::nationalityLabel($country),
            ])
            ->all();

        if ($current !== null && $current !== '' && !array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function regionOptions(?string $countryId = null): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $options = [];

        if ($countryId !== null) {
            foreach ($geography->regionsOf($countryId) as $region) {
                $options[$region->id] = self::localizedName($region->name);
            }

            return $options;
        }

        foreach ($geography->countries() as $country) {
            foreach ($geography->regionsOf($country->id) as $region) {
                $options[$region->id] = self::localizedRegionName($country, $region);
            }
        }

        return $options;
    }

    /**
     * روابط صور الطلاب المرفوعة فقط (بلا افتراضيات) — استعلامان للقائمة كاملة.
     *
     * @return array<string, string> معرّف المستخدم ← رابط الصورة
     */
    private static function avatarUrls(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $userIds = self::getEloquentQuery()
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<string, UserSummary> $users */
        $users = app(UserQueryService::class)->summariesByIds($userIds);
        $resolver = app(AvatarQueries::class);

        $urls = [];

        foreach ($users as $summary) {
            if ($summary->avatarPath === null) {
                continue;
            }

            $presentation = $resolver->resolve($summary->avatarPath, null);

            if (!$presentation->isDefault) {
                $urls[$summary->id] = $presentation->url;
            }
        }

        return $cache = $urls;
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    private static function nationalityLabel(CountryData $country): string
    {
        $key = 'organization::nationalities.'.$country->iso2;

        return Lang::has($key) ? (string) __($key) : self::localizedName($country->name);
    }

    /** @param array<string, string> $name */
    private static function localizedName(array $name): string
    {
        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? reset($name) ?: '';
    }

    private static function localizedRegionName(CountryData $country, RegionData $region): string
    {
        return self::localizedName($country->name).' — '.self::localizedName($region->name);
    }

    private static function organizationId(): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }

    private static function canPlaceStudent(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (bool) $user->can('enrollment.create')
            && (bool) $user->can('group.manage');
    }
}
