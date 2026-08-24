<?php

declare(strict_types=1);

namespace Modules\Enrollments\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
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

    public static function getNavigationGroup(): ?string
    {
        return __('enrollments::filament.navigation_group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('enrollment.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('enrollment.create') ?? false;
    }

    public static function canDelete($record): bool
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
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_profile_id')
                    ->label(__('enrollments::filament.enrollment.student'))
                    ->searchable(),

                TextColumn::make('program_id')
                    ->label(__('enrollments::filament.enrollment.program'))
                    ->searchable(),

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
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('enrollments::filament.enrollment.status'))
                    ->options(collect(EnrollmentStatus::cases())
                        ->mapWithKeys(fn (EnrollmentStatus $c): array => [
                            $c->value => __('enrollments::status.'.$c->value),
                        ])
                        ->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => EnrollmentResource\Pages\ListEnrollments::route('/'),
        ];
    }
}
