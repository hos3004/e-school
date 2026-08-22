import { Head, router } from '@inertiajs/react';

import DataTable, {
    type DataTableColumn,
} from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusPill, {
    type StatusColorMap,
} from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import {
    formatDateTime,
    formatPercent,
    useLocale,
} from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    Child,
    LoadablePageProps,
    MonthlyReport,
} from '@/types';

interface GuardianReportsProps extends LoadablePageProps {
    selectedChild: Child | null;
    reports: readonly MonthlyReport[];
}

const reportStatusColors: StatusColorMap<string> = {
    published: 'success',
    available: 'success',
    pending: 'warning',
    draft: 'neutral',
    archived: 'neutral',
};

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function GuardianChildReports({
    selectedChild,
    reports,
    loading = false,
    error = null,
}: GuardianReportsProps) {
    const t = useI18n();
    const locale = useLocale();
    const retry = () => router.reload();

    const columns: readonly DataTableColumn<MonthlyReport>[] = [
        {
            key: 'report',
            header: t('guardian.reports.columns.report'),
            render: (report) => (
                <div className="min-w-0">
                    <p className="font-semibold text-[var(--ink)]">
                        {report.title}
                    </p>
                    {report.summary ? (
                        <p className="mt-1 max-w-xl text-sm leading-6 text-[var(--ink-muted)]">
                            {report.summary}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'month',
            header: t('guardian.reports.columns.month'),
            render: (report) => report.month,
        },
        {
            key: 'issued_at',
            header: t('guardian.reports.columns.issued_at'),
            render: (report) =>
                report.issuedAt ? (
                    <time dateTime={report.issuedAt}>
                        {formatDateTime(report.issuedAt, locale)}
                    </time>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
        {
            key: 'attendance_rate',
            header: t('guardian.reports.columns.attendance_rate'),
            render: (report) =>
                report.attendanceRate === null ||
                report.attendanceRate === undefined ? (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ) : (
                    formatPercent(report.attendanceRate, locale)
                ),
        },
        {
            key: 'status',
            header: t('guardian.reports.columns.status'),
            render: (report) => (
                <StatusPill
                    colorMap={reportStatusColors}
                    status={report.status}
                />
            ),
        },
        {
            key: 'actions',
            header: t('common.actions'),
            render: (report) =>
                report.downloadUrl ? (
                    <a
                        className={[
                            'inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--brand)] px-4 py-2 text-sm font-semibold text-[var(--brand)] transition-colors hover:bg-[var(--surface-muted)]',
                            focusRing,
                        ].join(' ')}
                        href={report.downloadUrl}
                    >
                        {t('actions.download')}
                    </a>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout role="guardian">
            <Head title={t('guardian.reports.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={
                        selectedChild?.name ??
                        t('guardian.reports.subtitle')
                    }
                    title={t('guardian.reports.title')}
                />

                {!loading && error === null && selectedChild === null ? (
                    <EmptyState
                        description={t(
                            'guardian.reports.no_child_description',
                        )}
                        title={t('guardian.reports.no_child_title')}
                    />
                ) : (
                    <DataTable
                        caption={t('guardian.reports.table_caption')}
                        columns={columns}
                        emptyDescription={t(
                            'guardian.reports.empty_description',
                        )}
                        emptyTitle={t('guardian.reports.empty_title')}
                        error={error}
                        loading={loading}
                        loadingLabel={t('guardian.reports.loading')}
                        onRetry={retry}
                        rowKey={(report) => report.id}
                        rows={reports}
                    />
                )}
            </div>
        </AppLayout>
    );
}
