<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Recordings\Application\Queries\RecordingOperationsQueryService;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;

/**
 * مورد إدارة التسجيلات في لوحة الإدارة.
 */
final class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?int $navigationSort = 44;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('recordings::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('recordings::navigation.recording.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('recordings::navigation.recording.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        return parent::getEloquentQuery()->when(
            is_string($organizationId) && $organizationId !== '',
            fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_context')
                    ->label(__('recordings::fields.session'))
                    ->state(fn (Recording $record): string => self::operations()->context(
                        (string) $record->organization_id,
                        $record,
                    )['session']),
                TextColumn::make('course_context')
                    ->label(__('recordings::fields.course'))
                    ->state(fn (Recording $record): string => self::operations()->context(
                        (string) $record->organization_id,
                        $record,
                    )['course'])
                    ->toggleable(),
                TextColumn::make('teacher_context')
                    ->label(__('recordings::fields.teacher'))
                    ->state(fn (Recording $record): string => self::operations()->context(
                        (string) $record->organization_id,
                        $record,
                    )['teacher'])
                    ->toggleable(),
                TextColumn::make('provider')
                    ->label(__('recordings::fields.provider'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('recordings::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof RecordingStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof RecordingStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('duration_seconds')
                    ->label(__('recordings::fields.duration'))
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? '—'
                        : __('recordings::messages.duration_minutes', ['minutes' => (int) ceil($state / 60)]))
                    ->sortable(),
                TextColumn::make('active_grants')
                    ->label(__('recordings::fields.active_grants'))
                    ->state(function (Recording $record): int {
                        $data = self::administration()->findForOrganization(
                            (string) $record->organization_id,
                            (string) $record->getKey(),
                        );

                        return $data === null ? 0 : $data->activeGrantCount;
                    })
                    ->badge(),
                TextColumn::make('views')
                    ->label(__('recordings::fields.views'))
                    ->state(function (Recording $record): int {
                        $data = self::administration()->findForOrganization(
                            (string) $record->organization_id,
                            (string) $record->getKey(),
                        );

                        return $data === null ? 0 : $data->viewCount;
                    })
                    ->badge(),
                TextColumn::make('available_from')
                    ->label(__('recordings::fields.available_from'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('recordings::fields.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('id')
                    ->label(__('recordings::fields.id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('recordings::fields.status'))
                    ->options(collect(RecordingStatus::cases())
                        ->mapWithKeys(fn (RecordingStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('recordings::actions.view'))
                    ->icon('heroicon-m-eye')
                    ->url(fn (Recording $record): string => self::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('available_from', direction: 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => RecordingResource\Pages\ListRecordings::route('/'),
            'view' => RecordingResource\Pages\ViewRecording::route('/{record}'),
        ];
    }

    private static function operations(): RecordingOperationsQueryService
    {
        return app(RecordingOperationsQueryService::class);
    }

    private static function administration(): RecordingAdministrationQueries
    {
        return app(RecordingAdministrationQueries::class);
    }
}
