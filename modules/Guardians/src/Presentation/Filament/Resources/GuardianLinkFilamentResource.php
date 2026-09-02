<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Guardians\Application\Actions\SetPrimaryGuardianLink;
use Modules\Guardians\Application\Actions\UnlinkStudentFromGuardian;
use Modules\Guardians\Application\Actions\VerifyGuardianLink;
use Modules\Guardians\Application\Queries\GuardianAdministrationQueryService;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Presentation\Filament\Resources\Pages\ManageGuardianLinks;
use Shared\Concerns\ScopesFilamentToOrganizationVia;

final class GuardianLinkFilamentResource extends Resource
{
    use ScopesFilamentToOrganizationVia;

    protected static ?string $model = GuardianLink::class;

    protected static ?string $slug = 'guardian-links';

    protected static ?int $navigationSort = 31;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    protected static function organizationRelation(): string
    {
        return 'guardian';
    }

    public static function getNavigationGroup(): string
    {
        return __('guardians::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('guardians::filament.link.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guardians::filament.link.plural_label');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('relationship')
                ->label(__('guardians::filament.link.fields.relationship'))
                ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                    fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                )->all())
                ->required(),
            Checkbox::make('is_primary')
                ->label(__('guardians::filament.link.fields.is_primary')),
            Checkbox::make('can_act_for')
                ->label(__('guardians::filament.link.fields.can_act_for')),
            TagsInput::make('visible_sections')
                ->label(__('guardians::filament.link.fields.visible_sections'))
                ->suggestions((array) config('guardians.links.allowed_visible_sections', [])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guardian_name')
                    ->label(__('guardians::filament.link.fields.guardian'))
                    ->state(fn (GuardianLink $record): string => app(GuardianAdministrationQueryService::class)->accountName($record->guardian)),
                TextColumn::make('student_name')
                    ->label(__('guardians::filament.link.fields.student'))
                    ->state(fn (GuardianLink $record): string => app(ProfileAdministrationQueryService::class)->studentOptionLabel(
                        (string) $record->guardian->organization_id,
                        (string) $record->student_profile_id,
                    ) ?? (string) $record->student_profile_id),
                TextColumn::make('relationship')
                    ->label(__('guardians::filament.link.fields.relationship'))
                    ->badge()
                    ->formatStateUsing(fn (GuardianRelationship $state): string => $state->label()),
                IconColumn::make('is_primary')
                    ->label(__('guardians::filament.link.fields.is_primary'))
                    ->boolean(),
                IconColumn::make('can_act_for')
                    ->label(__('guardians::filament.link.fields.can_act_for'))
                    ->boolean(),
                TextColumn::make('verified_at')
                    ->label(__('guardians::filament.link.fields.verified_at'))
                    ->dateTime()
                    ->placeholder(__('guardians::filament.link.unverified')),
            ])
            ->filters([
                TernaryFilter::make('verified')
                    ->label(__('guardians::filament.link.filters.verified'))
                    ->queries(
                        true: fn ($query) => $query->verified(),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),
                SelectFilter::make('relationship')
                    ->label(__('guardians::filament.link.fields.relationship'))
                    ->options(collect(GuardianRelationship::cases())->mapWithKeys(
                        fn (GuardianRelationship $relationship): array => [$relationship->value => $relationship->label()],
                    )->all()),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label(__('guardians::admin.actions.verify'))
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (GuardianLink $record): bool => $record->verified_at === null
                        && (auth()->user()?->can('verify', $record) ?? false))
                    ->schema([self::reasonField()])
                    ->action(function (GuardianLink $record, array $data): void {
                        app(VerifyGuardianLink::class)->execute(
                            (string) $record->getKey(),
                            (string) auth()->id(),
                            (string) $data['reason'],
                        );
                        self::success(__('guardians::admin.actions.verified'));
                    }),
                Action::make('set_primary')
                    ->label(__('guardians::admin.actions.set_primary'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (GuardianLink $record): bool => !$record->is_primary
                        && (auth()->user()?->can('setPrimary', $record) ?? false))
                    ->schema([self::reasonField()])
                    ->action(function (GuardianLink $record, array $data): void {
                        app(SetPrimaryGuardianLink::class)->execute(
                            (string) $record->getKey(),
                            (string) auth()->id(),
                            (string) $data['reason'],
                        );
                        self::success(__('guardians::admin.actions.primary_set'));
                    }),
                Action::make('unlink')
                    ->label(__('guardians::admin.actions.unlink'))
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GuardianLink $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->schema([self::reasonField()])
                    ->action(function (GuardianLink $record, array $data): void {
                        app(UnlinkStudentFromGuardian::class)->execute(
                            (string) $record->getKey(),
                            (string) $data['reason'],
                            (string) auth()->id(),
                        );
                        self::success(__('guardians::admin.actions.unlinked'));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGuardianLinks::route('/'),
        ];
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('guardians::admin.fields.reason'))
            ->maxLength(2000)
            ->required();
    }

    private static function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
