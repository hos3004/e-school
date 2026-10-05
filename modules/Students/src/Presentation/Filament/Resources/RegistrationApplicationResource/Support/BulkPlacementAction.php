<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Support;

use App\Application\Actions\BulkAssignStudentsToGroupAction;
use App\Application\DTO\BulkPlacementCandidate;
use App\Application\DTO\BulkPlacementPreflight;
use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action as NotificationAction;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

/**
 * «تسكين الطلاب في مجموعة» — الإجراء الجماعي في شاشة طلبات التسجيل.
 *
 * مسؤولية هذه الطبقة ثلاثة أشياء فقط: عرض الخطوات، جمع الاختيارات، واستدعاء
 * `BulkAssignStudentsToGroupAction` مرة واحدة. لا سعة ولا تعارض ولا تسجيل
 * هنا — كلها في الأوركستريتر وفي الموديولات المالكة.
 *
 * المعرّفات المحددة لا يُوثق بها: الأوركستريتر يعيد قراءتها محصورة بمؤسسة
 * المنفّذ، ويعيد التحقق منها كلها داخل المعاملة.
 */
final class BulkPlacementAction
{
    private const TARGET_EXISTING = 'existing';

    private const TARGET_NEW = 'new';

    public static function make(): BulkAction
    {
        return self::build(fromStudents: false);
    }

    public static function forStudents(): BulkAction
    {
        return self::build(fromStudents: true);
    }

    private static function build(bool $fromStudents): BulkAction
    {
        $action = BulkAction::make('assignToGroup')
            ->label(__('students::admin.bulk_placement.action'))
            ->icon('heroicon-m-user-group')
            ->color('primary')
            ->modalHeading(__('students::admin.bulk_placement.heading'))
            ->modalSubmitActionLabel(__('students::admin.bulk_placement.confirm'))
            ->modalWidth('4xl')
            /*
             * تعبئة الحالة الابتدائية. الوجهة تُذكر هنا صراحةً لأن `fillForm`
             * تحل محل قيم `default()` في المكوّنات، فلولا ذلك بقيت الوجهة
             * فارغة ولم يظهر أي من فرعَي الخطوة الثانية.
             */
            ->fillForm(fn (Collection $records): array => [
                'target' => self::TARGET_EXISTING,
                'application_ids' => self::applicationIds($records),
            ])
            ->deselectRecordsAfterCompletion()
            ->steps([
                self::targetStep(),
                self::detailsStep(),
                self::reviewStep(),
            ])
            ->action(self::handler(...));

        return $fromStudents
            ? $action
                ->visible(static fn (): bool => self::canBulkPlaceStudents())
                ->authorizeIndividualRecords('view')
            : $action
                ->authorize('assignAny')
                ->authorizeIndividualRecords('assign');
    }

    /** الخطوة ١: مجموعة موجودة أم مجموعة جديدة. */
    private static function targetStep(): Step
    {
        return Step::make(__('students::admin.bulk_placement.steps.target'))
            ->description(__('students::admin.bulk_placement.steps.target_description'))
            ->schema([
                Radio::make('target')
                    ->label(__('students::admin.bulk_placement.target'))
                    ->options([
                        self::TARGET_EXISTING => __('students::admin.bulk_placement.target_existing'),
                        self::TARGET_NEW => __('students::admin.bulk_placement.target_new'),
                    ])
                    ->default(self::TARGET_EXISTING)
                    ->live()
                    ->afterStateUpdated(static function (Set $set): void {
                        $set('group_id', null);
                        $set('name_ar', null);
                        $set('name_en', null);
                        $set('name_fr', null);
                    })
                    ->required(),

                Select::make('program_id')
                    ->label(__('students::admin.placement.program'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)
                        ->programOptions(self::organizationId()))
                    ->live()
                    ->afterStateUpdated(static function (Set $set): void {
                        $set('course_id', null);
                        $set('group_id', null);
                    })
                    ->searchable()
                    ->required(),

                Select::make('course_id')
                    ->label(__('students::admin.placement.course'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->courseOptions(
                        self::organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                    ))
                    ->live()
                    ->afterStateUpdated(static fn (Set $set) => $set('group_id', null))
                    ->searchable()
                    ->required(),
            ])
            ->columns(1);
    }

    /** الخطوة ٢: اختيار المجموعة القائمة أو تسمية المسودة الجديدة. */
    private static function detailsStep(): Step
    {
        return Step::make(__('students::admin.bulk_placement.steps.details'))
            ->description(__('students::admin.bulk_placement.steps.details_description'))
            ->schema([
                Select::make('group_id')
                    ->label(__('students::admin.placement.group'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)
                        ->bulkPlacementGroupOptions(
                            self::organizationId(),
                            is_string($get('program_id')) ? $get('program_id') : null,
                            is_string($get('course_id')) ? $get('course_id') : null,
                        ))
                    ->helperText(__('students::admin.bulk_placement.group_help'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Get $get): bool => $get('target') === self::TARGET_EXISTING)
                    ->required(fn (Get $get): bool => $get('target') === self::TARGET_EXISTING),

                /*
                 * المجموعة الجديدة تُنشأ مسودة بالاسم والبرنامج فقط. المعلم
                 * والمواعيد والسعة وتواريخ البداية تبقى مؤجَّلة عمدًا ولا
                 * تُملأ بقيم وهمية — تُستوفى قبل التفعيل.
                 */
                TextInput::make('name_ar')
                    ->label(__('groups::filament.fields.name_ar'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('target') === self::TARGET_NEW)
                    ->required(fn (Get $get): bool => $get('target') === self::TARGET_NEW),

                TextInput::make('name_en')
                    ->label(__('groups::filament.fields.name_en'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('target') === self::TARGET_NEW),

                TextInput::make('name_fr')
                    ->label(__('groups::filament.fields.name_fr'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('target') === self::TARGET_NEW
                        && Locales::isSupported('fr')),

                Textarea::make('reason')
                    ->label(__('students::admin.placement.reason'))
                    ->helperText(__('students::admin.placement.reason_help'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->columns(1);
    }

    /** الخطوة ٣: المراجعة — من سيُسكَّن ومن لا ولماذا. */
    private static function reviewStep(): Step
    {
        return Step::make(__('students::admin.bulk_placement.steps.review'))
            ->description(__('students::admin.bulk_placement.steps.review_description'))
            ->schema([
                /*
                 * معرّفات الطلبات تُملأ عند فتح الإجراء (`fillForm`) لأن
                 * `$records` لا يصل إلى مكوّنات الـSchema — فهو متاح لمغلفات
                 * الإجراء نفسه فقط. القيمة هنا للعرض؛ التنفيذ يعتمد على
                 * السجلات المفوَّضة التي يمررها Filament إلى المعالج.
                 */
                Hidden::make('application_ids'),

                Placeholder::make('preflight')
                    ->label(__('students::admin.bulk_placement.steps.review'))
                    ->content(fn (Get $get): HtmlString => self::reviewSummary(
                        array_values(array_filter(
                            (array) $get('application_ids'),
                            static fn (mixed $id): bool => is_string($id) && $id !== '',
                        )),
                        is_string($get('group_id')) && $get('target') === self::TARGET_EXISTING
                            ? $get('group_id')
                            : null,
                        $get('target') === self::TARGET_NEW,
                    )),
            ]);
    }

    /** @param list<string> $applicationIds */
    private static function reviewSummary(
        array $applicationIds,
        ?string $groupId,
        bool $targetIsNewGroup,
    ): HtmlString {
        $preflight = app(BulkAssignStudentsToGroupAction::class)->preflight(
            self::organizationId(),
            $applicationIds,
            $groupId,
        );

        /*
         * تنبيه المسودة يظهر حين تكون النتيجة مسودة فعلًا: مجموعة جديدة، أو
         * مجموعة قائمة اختيرت وهي قيد التخطيط. قبل اختيار أي وجهة لا تنبيه.
         */
        $isDraft = $targetIsNewGroup || ($groupId !== null && $preflight->groupIsDraft);

        return new HtmlString(self::renderSummary($preflight, $isDraft));
    }

    private static function renderSummary(BulkPlacementPreflight $preflight, bool $isDraft): string
    {
        $lines = [];

        $lines[] = self::summaryLine(
            __('students::admin.bulk_placement.review.selected'),
            (string) $preflight->selectedCount(),
        );
        $lines[] = self::summaryLine(
            __('students::admin.bulk_placement.review.eligible'),
            (string) $preflight->eligibleCount(),
        );

        if ($preflight->remainingSeats !== null) {
            $lines[] = self::summaryLine(
                __('students::admin.bulk_placement.review.remaining_seats'),
                (string) $preflight->remainingSeats,
            );
        }

        if ($isDraft) {
            $lines[] = '<p class="fi-color-warning text-sm">'
                .e(__('students::admin.bulk_placement.review.draft_notice')).'</p>';
        }

        if ($preflight->capacityWarning !== null) {
            $lines[] = '<p class="fi-color-danger text-sm">'.e($preflight->capacityWarning).'</p>';
        }

        $blocked = $preflight->blocked();

        if ($blocked !== []) {
            $items = implode('', array_map(
                static fn (BulkPlacementCandidate $candidate): string => '<li>'
                    .e($candidate->name.' · '.$candidate->code.' — '.(string) $candidate->reason)
                    .'</li>',
                $blocked,
            ));

            $lines[] = '<p class="text-sm font-medium">'
                .e(__('students::admin.bulk_placement.review.blocked')).'</p>'
                ."<ul class=\"list-disc ps-5 text-sm\">{$items}</ul>";
        }

        return implode('', $lines);
    }

    private static function summaryLine(string $label, string $value): string
    {
        return '<p class="text-sm"><span class="font-medium">'.e($label).':</span> '.e($value).'</p>';
    }

    /**
     * @param Collection<int, RegistrationApplication|StudentProfile> $records الطلاب بعد تصفية التفويض الفردي
     * @param array<string, mixed> $data
     */
    private static function handler(Collection $records, array $data): void
    {
        $target = (string) ($data['target'] ?? self::TARGET_EXISTING);

        try {
            $result = app(BulkAssignStudentsToGroupAction::class)->execute(
                actorOrganizationId: self::organizationId(),
                applicationIds: self::applicationIds($records),
                programId: (string) $data['program_id'],
                courseId: is_string($data['course_id'] ?? null) ? $data['course_id'] : null,
                groupId: $target === self::TARGET_EXISTING ? (string) $data['group_id'] : null,
                newGroupName: $target === self::TARGET_NEW ? self::groupName($data) : null,
                timezone: self::timezone(),
                reason: (string) ($data['reason'] ?? ''),
                actorId: (string) auth()->id(),
                correlationId: request()->header('X-Correlation-Id'),
            );
        } catch (BusinessRuleViolation $violation) {
            Notification::make()
                ->title(__('students::admin.bulk_placement.failed'))
                ->body($violation->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $notification = Notification::make()
            ->title(__('students::admin.bulk_placement.succeeded', [
                'count' => $result->placedCount(),
                'group' => $result->groupLabel,
            ]))
            ->success();

        if ($result->skippedExistingCount > 0) {
            $notification->body(__('students::admin.bulk_placement.skipped_existing', [
                'count' => $result->skippedExistingCount,
            ]));
        }

        if ($result->groupIsDraft) {
            $notification->body(__('students::admin.bulk_placement.draft_reminder'));
            $notification->warning();
        }

        $notification->actions([
            NotificationAction::make('openGroup')
                ->label($result->groupIsDraft
                    ? __('students::admin.bulk_placement.complete_group')
                    : __('students::admin.bulk_placement.open_group'))
                ->url(self::groupUrl($result->groupId, $result->groupIsDraft))
                ->openUrlInNewTab(),
        ]);

        $notification->send();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function groupName(array $data): array
    {
        $name = [];

        foreach (['ar', 'en', 'fr'] as $locale) {
            $value = $data['name_'.$locale] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $name[$locale] = trim($value);
            }
        }

        return $name;
    }

    /**
     * @param Collection<int, RegistrationApplication|StudentProfile> $records
     * @return list<string>
     */
    private static function applicationIds(Collection $records): array
    {
        return $records
            ->map(static fn (RegistrationApplication|StudentProfile $record): ?string => $record instanceof RegistrationApplication
                ? (string) $record->getKey()
                : ($record->registrationApplication === null
                    ? null
                    : (string) $record->registrationApplication->getKey()))
            ->filter(static fn (?string $id): bool => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function canBulkPlaceStudents(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (bool) $user->can('student.view.any')
            && (bool) $user->can('enrollment.create')
            && (bool) $user->can('group.manage');
    }

    private static function groupUrl(string $groupId, bool $isDraft): string
    {
        return $isDraft
            ? route('filament.admin.resources.groups.edit', ['record' => $groupId])
            : route('filament.admin.resources.groups.view', ['record' => $groupId]);
    }

    private static function organizationId(): string
    {
        return (string) data_get(auth()->user(), 'organization_id');
    }

    private static function timezone(): string
    {
        $timezone = data_get(auth()->user(), 'timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : (string) config('app.timezone');
    }
}
