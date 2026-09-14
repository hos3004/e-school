<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Events\PayrollDeferredEntriesReleased;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Shared\Support\Transaction;

/**
 * تسوية الزوج (حصة مؤجَّلة + حصتها التعويضية) بدفعة واحدة.
 *
 * الخلل الذي يغلقه هذا الإجراء: `config/payroll.php` يقول عن
 * `makeup_completed` إنها `teacher: full` **و** `releases_deferred: true`،
 * ولم يكن أحد يقرأ `releases_deferred` إطلاقًا. فاعتماد التعويضية كان يُنشئ
 * قيدة كاملة جديدة، وتبقى قيدة الأصلية مؤجَّلة؛ فإذا حرّرها أحد من لوحة
 * Filament أو الـAPI صارت الحالتان `released` و`recorded` موجبتين معًا،
 * وكلتاهما تقع في دلو `entryEarnings` في `TeacherDuesQueryService`. النتيجة
 * دفعتان عن حصة واحدة دُرِّست. القيد الفريد لا يمنعها لأن معرّف الحصة مختلف.
 *
 * القاعدة المطبَّقة: **دفعة واحدة عن الزوج، بسعر الأصلية**.
 *   - وُجدت قيدة مؤجَّلة → تُحرَّر بمبلغها المخزَّن وقت التأجيل، ولا تُنشأ ثانية.
 *   - سبق تحريرها      → لا شيء. هذا ما يجعل الإجراء idempotent أمام اعتماد
 *                        مزدوج أو تسابق `sessions:finalize-due` مع زر الشاشة.
 *   - لا قيدة أصلًا     → على المستدعي إنشاء قيدة واحدة بسعر الأصلية موسومة
 *                        `fallback`. هذه حالة التأجيلات التي سبقت هذا الإصلاح.
 *
 * القفل `lockForUpdate` على صفوف الزوج هو ما يجعل الفحص والتحرير ذرّيين:
 * المعاملة الثانية تنتظر الأولى ثم تقرأ `released` فتنصرف.
 *
 * الدفتر append-only: لا تعديل لمبلغ ولا حذف لقيدة. الانتقال الوحيد المسموح
 * هو `Deferred → Released` عبر `canTransitionTo`.
 */
final readonly class SettleMakeupSessionAction
{
    /** حُرِّرت قيدة الأصلية الآن — لا تُنشأ قيدة للتعويضية. */
    public const RELEASED = 'released';

    /** سبق تسوية الزوج — لا تُنشأ قيدة ولا يُعاد التحرير. */
    public const ALREADY_SETTLED = 'already_settled';

    /** لا قيدة مؤجَّلة للأصلية — على المستدعي إنشاء قيدة fallback واحدة. */
    public const FALLBACK_REQUIRED = 'fallback_required';

    /**
     * القيدة المؤجَّلة تخص معلمًا آخر غير من أدّى التعويضية (بديل).
     *
     * من أدّى العمل يستحق أجره، ومن أُجّلت حصته لم يعد يستحقه — لكن أيّهما
     * ولأي مبلغ قرار إداري في `config/payroll.php → substitution`، لا استنتاج
     * آلي. فلا يُحرَّر شيء ولا تُنشأ قيدة: تُترك التسوية لإنسان. البديل بلا
     * هذا التمييز كان يأخذ قيدة كاملة بينما تبقى قيدة الأصلي قابلة للتحرير،
     * أي دفعتان عن حصة واحدة ولشخصين مختلفين.
     */
    public const MANUAL_REQUIRED = 'manual_required';

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return self::RELEASED|self::ALREADY_SETTLED|self::FALLBACK_REQUIRED|self::MANUAL_REQUIRED
     */
    public function execute(
        string $organizationId,
        string $makeupSessionId,
        string $staffProfileId,
        ?string $actorId,
        string $reason,
    ): string {
        [$outcome, $releasedIds, $periodId] = $this->transaction->run(function () use (
            $organizationId,
            $makeupSessionId,
            $staffProfileId,
            $actorId,
            $reason,
        ): array {
            /*
             * البحث بالمفتاح `deferred_until_session_id` وحده بلا ترشيح
             * بالمعلم: القيدة المؤجَّلة تخص من أُجّلت حصته، وقد يكون غير من
             * أدّى التعويضية عند وجود بديل. ترشيحها بمعلم التعويضية كان
             * يُخفي قيدة الأصلي فتُنشأ للبديل قيدة كاملة وتبقى قيدة الأصلي
             * قابلة للتحرير — دفعتان عن حصة واحدة.
             *
             * @var Collection<int, PayrollEntry> $entries
             */
            $entries = PayrollEntry::query()
                ->forOrganization($organizationId)
                ->where('deferred_until_session_id', $makeupSessionId)
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                return [self::FALLBACK_REQUIRED, [], null];
            }

            $otherTeacher = $entries->contains(
                static fn (PayrollEntry $entry): bool => (string) $entry->staff_profile_id !== $staffProfileId,
            );

            if ($otherTeacher) {
                return [self::MANUAL_REQUIRED, [], null];
            }

            $deferred = $entries->filter(
                static fn (PayrollEntry $entry): bool => $entry->status === PayrollEntryStatus::Deferred
                    && $entry->status->canTransitionTo(PayrollEntryStatus::Released),
            );

            if ($deferred->isEmpty()) {
                return [self::ALREADY_SETTLED, [], null];
            }

            $ids = [];
            foreach ($deferred as $entry) {
                $entry->forceFill(['status' => PayrollEntryStatus::Released])->save();
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'system',
                    action: 'payroll.entry.released',
                    auditableType: 'payroll_entries',
                    auditableId: (string) $entry->getKey(),
                    oldValues: ['status' => PayrollEntryStatus::Deferred->value],
                    newValues: [
                        'status' => PayrollEntryStatus::Released->value,
                        'makeup_session_id' => $makeupSessionId,
                    ],
                    reason: trim($reason),
                );
                $ids[] = (string) $entry->getKey();
            }

            return [self::RELEASED, $ids, (string) $deferred->first()->payroll_period_id];
        });

        if ($outcome === self::RELEASED && $periodId !== null) {
            $this->events->dispatch(new PayrollDeferredEntriesReleased(
                entryIds: $releasedIds,
                organizationId: $organizationId,
                payrollPeriodId: $periodId,
                staffProfileId: $staffProfileId,
                makeupSessionId: $makeupSessionId,
            ));
        }

        return $outcome;
    }
}
