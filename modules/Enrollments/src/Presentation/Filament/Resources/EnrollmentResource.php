<?php

declare(strict_types=1);

namespace Modules\Enrollments\Presentation\Filament\Resources;

use App\Application\Queries\ProfileAdministrationQueryService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Enrollments\Application\Queries\EnrollmentOperationsQueryService;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * قيود الطلاب في البرامج.
 *
 * قاعدتان حاكمتان تظهران هنا:
 *  - لا يوجد إجراء ينقل القيد من frozen إلى active مباشرة. المسار الوحيد
 *    يمر بطلب فك تجميد ثم تقييم، ولذلك لا زر هنا يفعلها.
 *  - الحساب لا يُحذف أبدًا؛ لا زر حذف في هذا المورد.
 */
final class EnrollmentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 22;

    public static function getNavigationGroup(): string
    {
        return __('enrollments::filament.navigation_group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Enrollment::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Enrollment::class) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        // قرار العميل: لا حذف لبيانات إنسان — التعليق فقط.
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('enrollments::filament.enrollment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('enrollments::filament.enrollment.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('student_profile_id')
                ->label(__('enrollments::filament.enrollment.student'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => app(ProfileAdministrationQueryService::class)
                    ->studentOptions(self::organizationId(), $search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                    ? app(ProfileAdministrationQueryService::class)->studentOptionLabel(self::organizationId(), $value)
                    : null)
                ->required(),

            Select::make('program_id')
                ->label(__('enrollments::filament.enrollment.program'))
                ->options(fn (): array => app(EnrollmentOperationsQueryService::class)->programOptions(self::organizationId()))
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('current_level_id', null))
                ->required(),

            Select::make('current_level_id')
                ->label(__('enrollments::filament.enrollment.current_level'))
                ->options(fn (Get $get): array => app(EnrollmentOperationsQueryService::class)->levelOptions(
                    self::organizationId(),
                    is_string($get('program_id')) ? $get('program_id') : null,
                ))
                ->searchable()
                ->preload(),

            Textarea::make('reason')
                ->label(__('enrollments::filament.enrollment.reason'))
                ->required()
                ->maxLength((int) config('enrollments.reason_max_length'))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_profile_id')
                    ->label(__('enrollments::filament.enrollment.student'))
                    ->formatStateUsing(fn (mixed $state, Enrollment $record): string => app(EnrollmentOperationsQueryService::class)
                        ->studentLabel((string) $record->organization_id, (string) $state))
                    ->searchable(),

                TextColumn::make('program_id')
                    ->label(__('enrollments::filament.enrollment.program'))
                    ->formatStateUsing(fn (mixed $state, Enrollment $record): string => app(EnrollmentOperationsQueryService::class)
                        ->programLabel((string) $record->organization_id, (string) $state))
                    ->searchable(),

                TextColumn::make('current_level_id')
                    ->label(__('enrollments::filament.enrollment.current_level'))
                    ->formatStateUsing(fn (mixed $state, Enrollment $record): string => app(EnrollmentOperationsQueryService::class)
                        ->levelLabel((string) $record->organization_id, is_string($state) ? $state : null))
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('enrollments::filament.enrollment.status'))
                    ->badge()
                    ->formatStateUsing(
                        fn (EnrollmentStatus $state): string => __('enrollments::status.'.$state->value),
                    )
                    ->color(fn (EnrollmentStatus $state): string => match ($state) {
                        EnrollmentStatus::Active => 'success',
                        // التجميد التأديبي أحمر، والإيقاف الاختياري كهرماني — تمييز مقصود.
                        EnrollmentStatus::Frozen => 'danger',
                        EnrollmentStatus::Paused => 'warning',
                        EnrollmentStatus::ReactivationRequested,
                        EnrollmentStatus::UnderAssessment => 'info',
                        EnrollmentStatus::Completed => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('freeze_type')
                    ->label(__('enrollments::filament.enrollment.freeze_type'))
                    ->toggleable(),

                TextColumn::make('expected_return_date')
                    ->label(__('enrollments::filament.enrollment.expected_return'))
                    ->date()
                    ->toggleable(),

                TextColumn::make('activated_at')
                    ->label(__('enrollments::filament.enrollment.activated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('applied_at')
                    ->label(__('enrollments::filament.enrollment.applied_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('enrollments::filament.enrollment.status'))
                    ->options(collect(EnrollmentStatus::cases())
                        ->mapWithKeys(fn (EnrollmentStatus $c): array => [
                            $c->value => __('enrollments::status.'.$c->value),
                        ])
                        ->all()),

                SelectFilter::make('program_id')
                    ->label(__('enrollments::filament.enrollment.program'))
                    ->options(fn (): array => app(EnrollmentOperationsQueryService::class)->programOptions(self::organizationId()))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (Enrollment $record): bool => auth()->user()?->can('view', $record) ?? false),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'create' => Pages\CreateEnrollment::route('/create'),
            'view' => Pages\ViewEnrollment::route('/{record}'),
        ];
    }

    private static function organizationId(): string
    {
        return (string) auth()->user()?->getAttribute('organization_id');
    }
}
