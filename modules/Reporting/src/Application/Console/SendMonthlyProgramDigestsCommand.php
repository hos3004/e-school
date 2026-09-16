<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Organization\Domain\Contracts\OrganizationDirectoryQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Reporting\Application\Services\MonthlyProgramDigestSender;
use Throwable;

/**
 * يرسل التقرير الشهري المجمَّع لكل برنامج له تقارير حصص خلال الفترة.
 *
 * بلا `--from`/`--to`: الشهر الذي انتهى للتو بتوقيت كل مؤسسة (الاستخدام
 * التلقائي آخر يوم من الشهر الساعة 23:50). بوجودهما: أي نطاق تاريخ مخصَّص
 * — الاستخدام اليدوي الذي طلبه العميل صراحة بلا التقيّد بالدورة الشهرية.
 */
final class SendMonthlyProgramDigestsCommand extends Command
{
    protected $signature = 'reporting:send-monthly-program-digests
        {--from= : تاريخ البداية (Y-m-d) بتوقيت المؤسسة المحلي}
        {--to= : تاريخ النهاية الشامل (Y-m-d) بتوقيت المؤسسة المحلي}
        {--organization= : اقتصار التنفيذ على مؤسسة واحدة}';

    protected $description = 'Send the monthly per-program session-report digest email';

    public function handle(
        OrganizationDirectoryQueries $organizations,
        SchoolClockQueries $clock,
        MonthlyProgramDigestSender $sender,
    ): int {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');
        $onlyOrganization = $this->option('organization');

        $organizationIds = is_string($onlyOrganization) && $onlyOrganization !== ''
            ? [$onlyOrganization]
            : $organizations->activeOrganizationIds();

        $totalSent = 0;

        foreach ($organizationIds as $organizationId) {
            $timezone = (string) $clock->forOrganization($organizationId)['timezone'];

            [$fromLocal, $toLocalExclusive] = $this->resolveRange($fromOption, $toOption, $timezone);

            try {
                $sent = $sender->send(
                    organizationId: $organizationId,
                    fromUtc: $fromLocal->setTimezone('UTC'),
                    untilUtcExclusive: $toLocalExclusive->setTimezone('UTC'),
                    periodFromLabel: $fromLocal->translatedFormat('Y-m-d'),
                    periodToLabel: $toLocalExclusive->subDay()->translatedFormat('Y-m-d'),
                    locale: (string) config('app.locale', 'ar'),
                );
                $totalSent += $sent;
                $this->components->info(__('reporting::messages_digest.monthly_digest_summary', [
                    'organization' => $organizationId,
                    'count' => $sent,
                ]));
            } catch (Throwable $exception) {
                Log::error('reporting.monthly_program_digest_failed', [
                    'organization_id' => $organizationId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $this->components->error(__('reporting::messages_digest.monthly_digest_failed', [
                    'organization' => $organizationId,
                ]));
            }
        }

        $this->components->info(__('reporting::messages_digest.monthly_digest_total', ['count' => $totalSent]));

        return self::SUCCESS;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} local from (inclusive) and to (exclusive, start of day after "to") */
    private function resolveRange(mixed $fromOption, mixed $toOption, string $timezone): array
    {
        if (is_string($fromOption) && $fromOption !== '' && is_string($toOption) && $toOption !== '') {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $fromOption, $timezone)->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', $toOption, $timezone)->startOfDay()->addDay();

            return [$from, $to];
        }

        // الافتراضي: الشهر الذي انتهى للتو بتوقيت المؤسسة (تشغيل آخر يوم في الشهر الساعة 23:50).
        $now = CarbonImmutable::now($timezone);
        $endOfThisMonth = $now->endOfMonth()->startOfDay()->addDay();
        $startOfThisMonth = $now->startOfMonth()->startOfDay();

        return [$startOfThisMonth, $endOfThisMonth];
    }
}
