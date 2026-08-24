import { Head, router } from '@inertiajs/react';

import Card, {
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import type { StatusColorMap } from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps } from '@/types';

interface LedgerEntry {
    id: string;
    sessionId: string | null;
    entryType: string;
    outcomeKey: string;
    amountMinorUnits: number;
    status: string;
    recordedAt: string | null;
}

interface Adjustment {
    id: string;
    type: string;
    amountMinorUnits: number;
    reason: string;
    approvedAt: string | null;
}

interface EarningsPeriod {
    id: string;
    year: number;
    month: number;
    status: string;
    currency: string;
    earningsMinorUnits: number;
    deductionsMinorUnits: number;
    adjustmentsMinorUnits: number;
    netMinorUnits: number;
    sessionsCount: number;
    entries: readonly LedgerEntry[];
    adjustments: readonly Adjustment[];
}

interface Props extends LoadablePageProps {
    periods?: readonly EarningsPeriod[];
    hasProfile?: boolean;
}

const periodStatusColors: StatusColorMap<string> = {
    open: 'brand',
    calculating: 'warning',
    under_review: 'warning',
    approved: 'success',
    paid: 'success',
    locked: 'neutral',
};

/**
 * المبالغ تصل بالوحدة الصغرى (قروش) ولا تُقسَّم على 100 إلا هنا، عند العرض.
 */
function formatMoney(
    minorUnits: number,
    currency: string,
    locale: string,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(minorUnits / 100);
}

function monthLabel(year: number, month: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'long',
    }).format(new Date(Date.UTC(year, month - 1, 1)));
}

export default function Earnings({
    periods = [],
    hasProfile = false,
    loading = false,
    error = null,
}: Props) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.earnings.title')} />
            <PageHeader
                className="mb-6"
                title={t('teacher.earnings.title')}
                subtitle={t('teacher.earnings.subtitle')}
            />

            {loading ? (
                <LoadingState label={t('teacher.earnings.loading')} rows={3} />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : !hasProfile ? (
                <EmptyState
                    title={t('teacher.earnings.no_profile_title')}
                    description={t('teacher.earnings.no_profile_description')}
                />
            ) : periods.length === 0 ? (
                <EmptyState
                    title={t('teacher.earnings.empty_title')}
                    description={t('teacher.earnings.empty_description')}
                />
            ) : (
                <div className="space-y-6">
                    <p className="rounded-lg bg-[var(--surface-muted)] p-4 text-sm leading-6 text-[var(--ink-muted)]">
                        {t('teacher.earnings.disclaimer')}
                    </p>

                    {periods.map((period) => (
                        <Card key={period.id}>
                            <CardHeader className="mb-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <CardTitle>
                                            {monthLabel(
                                                period.year,
                                                period.month,
                                                locale,
                                            )}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('teacher.earnings.sessions_counted')}
                                            : {period.sessionsCount}
                                        </CardDescription>
                                    </div>
                                    <StatusPill
                                        colorMap={periodStatusColors}
                                        status={period.status}
                                    />
                                </div>
                            </CardHeader>

                            <dl className="grid gap-4 sm:grid-cols-4">
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('teacher.earnings.earned')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {formatMoney(
                                            period.earningsMinorUnits,
                                            period.currency,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('teacher.earnings.deducted')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {formatMoney(
                                            period.deductionsMinorUnits,
                                            period.currency,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('teacher.earnings.adjustments')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {formatMoney(
                                            period.adjustmentsMinorUnits,
                                            period.currency,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('teacher.earnings.net')}
                                    </dt>
                                    <dd className="mt-1 text-lg font-bold text-[var(--ink)]">
                                        {formatMoney(
                                            period.netMinorUnits,
                                            period.currency,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                            </dl>

                            {period.adjustments.length > 0 ? (
                                <section className="mt-6">
                                    <h3 className="text-sm font-bold text-[var(--ink)]">
                                        {t('teacher.earnings.adjustments_title')}
                                    </h3>
                                    <ul className="mt-2 divide-y divide-[var(--surface-muted)]">
                                        {period.adjustments.map(
                                            (adjustment) => (
                                                <li
                                                    className="flex flex-wrap items-center justify-between gap-3 py-3"
                                                    key={adjustment.id}
                                                >
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-semibold text-[var(--ink)]">
                                                            {t(
                                                                `payroll_adjustment_types.${adjustment.type}`,
                                                            )}
                                                        </p>
                                                        <p className="mt-1 text-sm text-[var(--ink-muted)]">
                                                            {adjustment.reason}
                                                        </p>
                                                    </div>
                                                    <span className="font-semibold text-[var(--ink)]">
                                                        {formatMoney(
                                                            adjustment.amountMinorUnits,
                                                            period.currency,
                                                            locale,
                                                        )}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </section>
                            ) : null}

                            {period.entries.length > 0 ? (
                                <section className="mt-6">
                                    <h3 className="text-sm font-bold text-[var(--ink)]">
                                        {t('teacher.earnings.entries_title')}
                                    </h3>
                                    <div className="mt-2 overflow-x-auto">
                                        <table className="w-full min-w-[32rem] text-sm">
                                            <caption className="sr-only">
                                                {t(
                                                    'teacher.earnings.entries_caption',
                                                )}
                                            </caption>
                                            <thead>
                                                <tr className="text-start text-xs text-[var(--ink-muted)]">
                                                    <th
                                                        className="py-2 text-start font-normal"
                                                        scope="col"
                                                    >
                                                        {t(
                                                            'teacher.earnings.columns.outcome',
                                                        )}
                                                    </th>
                                                    <th
                                                        className="py-2 text-start font-normal"
                                                        scope="col"
                                                    >
                                                        {t(
                                                            'teacher.earnings.columns.recorded_at',
                                                        )}
                                                    </th>
                                                    <th
                                                        className="py-2 text-end font-normal"
                                                        scope="col"
                                                    >
                                                        {t(
                                                            'teacher.earnings.columns.amount',
                                                        )}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-[var(--surface-muted)]">
                                                {period.entries.map((entry) => (
                                                    <tr key={entry.id}>
                                                        <td className="py-2 text-[var(--ink)]">
                                                            {t(
                                                                `payroll_outcomes.${entry.outcomeKey}`,
                                                            )}
                                                        </td>
                                                        <td className="py-2 text-[var(--ink-muted)]">
                                                            {entry.recordedAt
                                                                ? formatDateTime(
                                                                      entry.recordedAt,
                                                                      locale,
                                                                  )
                                                                : t(
                                                                      'common.not_available',
                                                                  )}
                                                        </td>
                                                        <td className="py-2 text-end font-semibold text-[var(--ink)]">
                                                            {formatMoney(
                                                                entry.amountMinorUnits,
                                                                period.currency,
                                                                locale,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            ) : null}
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
