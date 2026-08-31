<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources;

use App\Application\Queries\ProfileAdministrationQueryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Groups\Application\Actions\WithdrawStudentAction;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Presentation\Filament\Resources\GroupMembershipResource\Pages;
use Shared\Support\BusinessRuleViolation;

/**
 * تسكين الطلاب في المجموعات — إدارة كاملة عبر أفعال الدومين السليمة
 * (EnrollStudentAction / WithdrawStudentAction) لا كتابة خام: تُفحَص السعة
 * وحالة المجموعة والتكرار، ويُنشَر الحدث فتصل الإشعارات، ويُسجَّل السبب.
 */
final class GroupMembershipResource extends Resource
{
    protected static ?string $model = GroupMembership::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = 'placements';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string
    {
        return __('groups::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('groups::membership.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('groups::membership.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('groups::membership.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('group.view');
    }

    // التسكين (الإنشاء) يمر بمنسّق app‑level ذي الفحص الكامل عبر صفحة «تسكين
    // طالب»؛ هذا المورد للعرض والسحب فقط كي لا يوجد مسار تسكين ناقص ثانٍ.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group.name')
                    ->label(__('groups::membership.group'))
                    ->state(fn (GroupMembership $record): string => self::localized($record->group?->name))
                    ->searchable(),
                TextColumn::make('student_name')
                    ->label(__('groups::membership.student'))
                    ->state(fn (GroupMembership $record): string => self::studentName($record->student_profile_id)),
                TextColumn::make('student_profile_id')
                    ->label(__('groups::membership.student_code'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('groups::membership.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof MembershipStatus
                        ? $state->label()
                        : (MembershipStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn ($state): string => ($state instanceof MembershipStatus ? $state : MembershipStatus::tryFrom((string) $state)) === MembershipStatus::Active
                        ? 'success'
                        : 'gray'),
                TextColumn::make('joined_at')
                    ->label(__('groups::membership.joined_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('left_at')
                    ->label(__('groups::membership.left_at'))
                    ->dateTime()
                    ->placeholder(__('groups::membership.still_active'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('groups::membership.status'))
                    ->options(collect(MembershipStatus::cases())
                        ->mapWithKeys(fn (MembershipStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->actions([
                self::withdrawAction(),
            ])
            ->defaultSort('joined_at', 'desc');
    }

    /**
     * قيود مجموعات مؤسسة المستخدم فقط؛ غياب المؤسسة يغلق الاستعلام.
     *
     * @return Builder<GroupMembership>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<GroupMembership> $query */
        $query = parent::getEloquentQuery();
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('group', fn (Builder $groupQuery): Builder => $groupQuery->where('organization_id', $organizationId));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroupMemberships::route('/'),
        ];
    }

    /**
     * سحب الطالب من المجموعة عبر فعل الدومين — يُسجَّل السبب ويُنشَر الحدث.
     */
    private static function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label(__('groups::membership.withdraw'))
            ->icon('heroicon-m-user-minus')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(fn (): bool => auth()->user()?->can('group.manage') ?? false)
            ->visible(fn (GroupMembership $record): bool => $record->left_at === null)
            ->form([
                Textarea::make('reason')
                    ->label(__('groups::membership.withdraw_reason'))
                    ->required(),
            ])
            ->action(function (GroupMembership $record, array $data): void {
                try {
                    app(WithdrawStudentAction::class)->execute(
                        $record,
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()
                        ->title($violation->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('groups::membership.withdrawn'))
                    ->success()
                    ->send();
            });
    }

    private static function studentName(string $studentProfileId): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $studentProfileId;
        }

        return app(ProfileAdministrationQueryService::class)->studentOptionLabel(
            $organizationId,
            $studentProfileId,
        ) ?? $studentProfileId;
    }

    /**
     * @param array<string, string>|string|null $name
     */
    private static function localized(array|string|null $name): string
    {
        if (is_string($name)) {
            return $name;
        }

        if ($name === null) {
            return '';
        }

        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? (string) (reset($name) ?: '');
    }
}
