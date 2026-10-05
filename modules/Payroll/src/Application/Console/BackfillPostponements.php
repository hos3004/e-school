<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Events\SessionPostponed;
use Throwable;

/**
 * معالجة التأجيلات التي مضت بلا قيدة مؤجَّلة.
 *
 * سببها خللان أُغلقا في هذا الفرع: `SessionPostponed` كان يُطلق من مسار
 * الإدارة وحده فلم يكن لتأجيل المعلم المعتمد أي أثر مالي، وحتى حين كان
 * يُطلق كانت القيدة تُعلَّق على معرّف الأصلية لا على تعويضيتها فلا يجدها
 * مسار التحرير. الحصص التي مرّت في تلك الفترة بقيت بلا مستحق مؤجَّل.
 *
 * لا إدخال مباشر في الدفتر: الأمر يعيد إطلاق حدث التأجيل نفسه، فيمر بنفس
 * المستمع ونفس محلّل السعر ونفس `RecordPayrollEntryAction`. أي قاعدة تتغير
 * غدًا في `config/payroll.php` تنطبق على المعالجة كما تنطبق على التأجيل الحي.
 *
 * `--dry-run` هو الافتراضي عمدًا: هذا أمر يكتب في دفتر لا يُعدَّل ولا يُحذف.
 *
 * ترتيب التشغيل عند النشر: **قبل** أول تشغيل لـ`sessions:finalize-due`، حتى
 * تجد الحصص التعويضية قيودَها المؤجَّلة فتحرّرها بدل أن تُنشئ قيدة fallback.
 */
final class BackfillPostponements extends Command
{
    protected $signature = 'payroll:backfill-postponements {--execute : اكتب القيود فعلًا بدل عرضها}';

    protected $description = 'Create the missing deferred payroll entries for sessions postponed before the fix';

    public function handle(
        SessionFactsQueries $sessions,
        Dispatcher $events,
        AuditRecorder $audit,
    ): int {
        $execute = (bool) $this->option('execute');
        $batchSize = max(1, (int) config('payroll.backfill.batch_size'));

        $planned = 0;
        $skipped = 0;
        $written = 0;
        $failed = 0;
        $cursor = null;

        /*
         * التقدّم بمؤشر لا بـLIMIT وحده: الحصص المعالَجة تبقى `postponed` إلى
         * الأبد، فاستعلام «أول 200 مؤجَّلة» يعيدها نفسها كل مرة ولا يصل أبدًا
         * إلى الحصة رقم 201. المؤشر يمشي على الدفعات حتى تنتهي.
         */
        while (true) {
            $pairs = $sessions->postponedPairs($batchSize, $cursor);

            if ($pairs === []) {
                break;
            }

            $cursor = $pairs[count($pairs) - 1]['original_session_id'];

            foreach ($pairs as $pair) {
                /*
                 * الحماية من التكرار تفحص **الزوج كله** لا الأصلية وحدها.
                 *
                 * فحص `session_id = الأصلية` وحده كان ثغرة دفع مزدوج حقيقية: إذا
                 * سبق `sessions:finalize-due` هذه المعالجة، تكون التعويضية قد
                 * أنشأت قيدة fallback بـ`session_id = التعويضية`، فلا يراها هذا
                 * الفحص، فتُضاف قيدة مؤجَّلة ثانية عن نفس العمل. الترتيب الصحيح
                 * للتشغيل موثَّق، لكن التوثيق ليس حارسًا.
                 */
                if (PayrollEntry::query()
                    ->forOrganization($pair['organization_id'])
                    ->where(static fn ($query) => $query
                        ->where('session_id', $pair['original_session_id'])
                        ->orWhere('session_id', $pair['makeup_session_id'])
                        ->orWhere('deferred_until_session_id', $pair['makeup_session_id']))
                    ->exists()
                ) {
                    $skipped++;

                    continue;
                }

                $planned++;

                $this->line(sprintf(
                    '%s  %s → %s',
                    $execute ? '[write]' : '[dry-run]',
                    $pair['original_session_id'],
                    $pair['makeup_session_id'],
                ));

                if (!$execute) {
                    continue;
                }

                try {
                    $events->dispatch(new SessionPostponed(
                        sessionId: $pair['original_session_id'],
                        organizationId: $pair['organization_id'],
                        courseId: $pair['course_id'],
                        staffProfileId: $pair['staff_profile_id'],
                        makeupSessionId: $pair['makeup_session_id'],
                        makeupStart: $pair['makeup_start'],
                        makeupEnd: $pair['makeup_end'],
                        reason: (string) __('payroll::actions.backfill_postponements.reason'),
                    ));

                    /*
                     * المستمع يبتلع أخطاء القواعد ليحمي إقفال الحصة، فنجاح
                     * الإطلاق ليس دليل كتابة. الدليل هو وجود القيدة بعده.
                     */
                    $recorded = PayrollEntry::query()
                        ->forOrganization($pair['organization_id'])
                        ->where('session_id', $pair['original_session_id'])
                        ->count();

                    if ($recorded === 0) {
                        $failed++;
                        $this->warn('  '.__('payroll::actions.backfill_postponements.not_recorded'));

                        continue;
                    }

                    $written++;
                    $audit->record(
                        organizationId: $pair['organization_id'],
                        actorId: null,
                        actorType: 'system',
                        action: 'payroll.postponement_backfilled',
                        auditableType: 'sessions',
                        auditableId: $pair['original_session_id'],
                        oldValues: null,
                        newValues: ['makeup_session_id' => $pair['makeup_session_id']],
                        reason: (string) __('payroll::actions.backfill_postponements.reason'),
                    );
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn('  '.$exception::class);
                }
            }

            if (count($pairs) < $batchSize) {
                break;
            }
        }

        $this->components->info(__('payroll::actions.backfill_postponements.summary', [
            'planned' => $planned,
            'written' => $written,
            'skipped' => $skipped,
            'failed' => $failed,
        ]));

        if (!$execute && $planned > 0) {
            $this->components->warn(__('payroll::actions.backfill_postponements.dry_run'));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
