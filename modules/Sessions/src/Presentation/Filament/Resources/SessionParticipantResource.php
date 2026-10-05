<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Application\Queries\SessionOperationsQueryService;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Shared\Concerns\ScopesFilamentToOrganizationVia;

/**
 * مورد مشاركو الحصص في لوحة الإدارة.
 */
final class SessionParticipantResource extends Resource
{
    use ScopesFilamentToOrganizationVia;

    /**
     * الجدول لا يحمل `organization_id`؛ ينتمي عبر أبيه.
     */
    protected static function organizationRelation(): string
    {
        return 'session';
    }

    protected static ?string $model = SessionParticipant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 41;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('sessions::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('sessions::navigation.participant.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sessions::navigation.participant.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('session'))
            ->columns([
                TextColumn::make('session_id')
                    ->label(__('sessions::fields.session'))
                    ->formatStateUsing(fn (mixed $state, SessionParticipant $record): string => self::queries()->sessionLabel(
                        (string) $record->session->organization_id,
                        (string) $state,
                    )),
                TextColumn::make('student_profile_id')
                    ->label(__('sessions::fields.student_profile'))
                    ->formatStateUsing(fn (mixed $state, SessionParticipant $record): string => self::queries()->studentLabel(
                        (string) $record->session->organization_id,
                        (string) $state,
                    )),
                TextColumn::make('invitation_status')
                    ->label(__('sessions::fields.invitation_status'))
                    ->state(fn (SessionParticipant $record): string => $record->revoked_at === null
                        ? __('sessions::fields.invitation_active')
                        : __('sessions::fields.invitation_revoked'))
                    ->badge()
                    ->color(fn (SessionParticipant $record): string => $record->revoked_at === null ? 'success' : 'danger'),
                TextColumn::make('invited_at')
                    ->label(__('sessions::fields.invited_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('first_joined_at')
                    ->label(__('sessions::fields.first_joined_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_left_at')
                    ->label(__('sessions::fields.last_left_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('attended_minutes')
                    ->label(__('sessions::fields.attended_minutes'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->label(__('sessions::fields.revoked_at'))
                    ->dateTime()
                    ->toggleable(),
            ])
            ->defaultSort('created_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => SessionParticipantResource\Pages\ListSessionParticipants::route('/'),
        ];
    }

    private static function queries(): SessionOperationsQueryService
    {
        return app(SessionOperationsQueryService::class);
    }
}
