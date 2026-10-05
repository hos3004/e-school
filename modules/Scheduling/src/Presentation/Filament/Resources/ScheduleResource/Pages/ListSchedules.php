<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Scheduling\Application\Actions\CreateExtraSessionAction;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;

final class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('individual_quran_placement')
                ->label(__('scheduling::filament.schedule.actions.individual_quran_placement'))
                ->icon('heroicon-o-users')
                ->visible(static function (): bool {
                    $user = auth()->user();

                    return $user !== null
                        && (bool) $user->can('schedule.manage')
                        && (bool) $user->can('student.view.any');
                })
                ->url(route('filament.admin.resources.students.individual-quran')),
            self::extraSessionAction(),
            CreateAction::make(),
        ];
    }

    /**
     * حصة إضافية خارج أي جدول دائم — لمجموعة أو لطالب فردي (كالقرآن الفردي)
     * أو لأي كورس آخر. تُدرَج مباشرة دون أي موافقة، عبر
     * `CreateExtraSessionAction` الذي يحمي التعارض والحجز المزدوج.
     *
     * القرار المالي جزء من النموذج نفسه: هل توجد مستحقات أصلًا، وإن وُجدت
     * فبأي سعر — الافتراضي أو سعر يُكتب هنا خصيصًا لهذه الحصة.
     */
    private static function extraSessionAction(): Action
    {
        return Action::make('extra_session')
            ->label(__('scheduling::filament.extra_session.action'))
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->modalHeading(__('scheduling::filament.extra_session.modal_heading'))
            ->modalSubmitActionLabel(__('scheduling::filament.extra_session.modal_submit'))
            ->authorize(fn (): bool => auth()->user()?->can('session.create') ?? false)
            ->form([
                Select::make('target_type')
                    ->label(__('scheduling::filament.extra_session.fields.target_type'))
                    ->options([
                        'group' => __('scheduling::filament.extra_session.fields.target_group'),
                        'student' => __('scheduling::filament.extra_session.fields.target_student'),
                    ])
                    ->default('group')
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('group_id', null);
                        $set('student_profile_id', null);
                        $set('course_id', null);
                        $set('staff_profile_id', null);
                    })
                    ->required(),
                Select::make('group_id')
                    ->label(__('scheduling::filament.extra_session.fields.group'))
                    ->options(fn (): array => self::queries()->groupOptions(self::organizationId()))
                    ->visible(fn (Get $get): bool => $get('target_type') === 'group')
                    ->required(fn (Get $get): bool => $get('target_type') === 'group')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('course_id', null);
                        $set('staff_profile_id', null);
                    }),
                Select::make('course_id')
                    ->label(__('scheduling::filament.extra_session.fields.course'))
                    ->options(fn (Get $get): array => self::queries()->courseOptions(
                        self::organizationId(),
                        $get('target_type') === 'group' && is_string($get('group_id')) ? $get('group_id') : null,
                        is_string($get('target_type')) ? $get('target_type') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('staff_profile_id', null);
                        $set('student_profile_id', null);
                    })
                    ->required(),
                Select::make('student_profile_id')
                    ->label(__('scheduling::filament.extra_session.fields.student'))
                    ->options(fn (Get $get): array => self::queries()->studentOptions(
                        self::organizationId(),
                        is_string($get('course_id')) ? $get('course_id') : null,
                    ))
                    ->visible(fn (Get $get): bool => $get('target_type') === 'student')
                    ->required(fn (Get $get): bool => $get('target_type') === 'student')
                    ->searchable()
                    ->preload(),
                Select::make('staff_profile_id')
                    ->label(__('scheduling::filament.extra_session.fields.teacher'))
                    ->options(fn (Get $get): array => self::queries()->teacherOptions(
                        self::organizationId(),
                        $get('target_type') === 'group' && is_string($get('group_id')) ? $get('group_id') : null,
                        is_string($get('course_id')) ? $get('course_id') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->label(__('scheduling::filament.extra_session.fields.starts_at'))
                    ->native(false)
                    ->seconds(false)
                    ->timezone('UTC')
                    ->minDate(now('UTC'))
                    ->required(),
                TextInput::make('duration_minutes')
                    ->label(__('scheduling::filament.extra_session.fields.duration_minutes'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(600)
                    ->default(60)
                    ->required(),
                Toggle::make('has_payroll')
                    ->label(__('scheduling::filament.extra_session.fields.has_payroll'))
                    ->helperText(__('scheduling::filament.extra_session.help.has_payroll'))
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if ($state !== true) {
                            $set('same_price', true);
                            $set('amount_major', null);
                        }
                    }),
                Toggle::make('same_price')
                    ->label(__('scheduling::filament.extra_session.fields.same_price'))
                    ->helperText(__('scheduling::filament.extra_session.help.same_price'))
                    ->default(true)
                    ->live()
                    ->visible(fn (Get $get): bool => $get('has_payroll') === true)
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if ($state === true) {
                            $set('amount_major', null);
                        }
                    }),
                TextInput::make('amount_major')
                    ->label(__('scheduling::filament.extra_session.fields.custom_price'))
                    ->helperText(__('scheduling::filament.extra_session.help.custom_price'))
                    ->inputMode('decimal')
                    ->rule('decimal:0,2')
                    ->minValue(0.01)
                    ->suffix((string) config('payroll.currency'))
                    ->visible(fn (Get $get): bool => $get('has_payroll') === true && $get('same_price') !== true)
                    ->required(fn (Get $get): bool => $get('has_payroll') === true && $get('same_price') !== true),
                Textarea::make('reason')
                    ->label(__('scheduling::filament.extra_session.fields.reason'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $hasPayroll = $data['has_payroll'] === true;
                $samePrice = $data['same_price'] ?? true;
                $override = null;

                if ($hasPayroll && $samePrice !== true && filled($data['amount_major'] ?? null)) {
                    $override = Money::fromMajor(
                        (string) $data['amount_major'],
                        (string) config('payroll.currency'),
                    )->minorUnits;
                }

                try {
                    app(CreateExtraSessionAction::class)->execute(
                        organizationId: self::organizationId(),
                        groupId: $data['target_type'] === 'group' ? (string) $data['group_id'] : null,
                        studentProfileId: $data['target_type'] === 'student' ? (string) $data['student_profile_id'] : null,
                        courseId: (string) $data['course_id'],
                        staffProfileId: (string) $data['staff_profile_id'],
                        startsAt: CarbonImmutable::parse((string) $data['starts_at'], 'UTC'),
                        durationMinutes: (int) $data['duration_minutes'],
                        payrollExempt: !$hasPayroll,
                        payrollRateOverrideMinorUnits: $override,
                        actorId: (string) auth()->id(),
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
                    ->title(__('scheduling::filament.extra_session.created'))
                    ->success()
                    ->send();
            });
    }

    private static function queries(): SchedulingAdministrationQueryService
    {
        return app(SchedulingAdministrationQueryService::class);
    }

    private static function organizationId(): string
    {
        $id = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }
}
