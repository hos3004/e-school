import { Head, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import type { DataTableColumn } from '@/Components/DataTable';
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import {
    formatDate,
    formatPercent,
    useLocale,
} from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    LoadablePageProps,
    MonthlyReport,
    StatusColorMap,
} from '@/types';

interface StudentReportsProps extends LoadablePageProps {
    reports?: readonly MonthlyReport[];
}

const reportStatusColors: StatusColorMap = {
    draft: 'neutral',
    pending: 'warning',
    published: 'success',
    available: 'success',
    archived: 'neutral',
};

export default function Reports({
    reports = [],
    loading = false,
    error = null,
}: StudentReportsProps) {
    const t = useI18n();
    const locale = useLocale();

    const columns: readonly DataTableColumn<MonthlyReport>[] = [
        {
            key: 'report',
            header: t('student.reports.report'),
            render: (report) => (
                <div className="min-w-52">
                    <p className="font-semibold text-[var(--ink)]">
                        {report.title}
                    </p>
                    {report.summary ? (
                        <p className="mt-1 line-clamp-2 text-sm leading-6 text-[var(--ink-muted)]">
                            {report.summary}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'month',
            header: t('student.reports.month'),
            render: (report) => report.month,
        },
        {
            key: 'attendance_rate',
            header: t('student.reports.attendance_rate'),
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
            header: t('common.status'),
            render: (report) => (
                <StatusPill
                    colorMap={reportStatusColors}
                    status={report.status}
                />
            ),
        },
        {
            key: 'issued_at',
            header: t('student.reports.issued_at'),
            render: (report) =>
                report.issuedAt ? (
                    <time dateTime={report.issuedAt}>
                        {formatDate(report.issuedAt, locale)}
                    </time>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
        {
            cellClassName: 'text-end',
            headerClassName: 'text-end',
            key: 'actions',
            header: t('common.actions'),
            render: (report) =>
                report.downloadUrl ? (
                    <Button
                        aria-label={
                            t('student.reports.download') +
                            ' ' +
                            report.title
                        }
                        as="link"
                        href={report.downloadUrl}
                        rel="noopener noreferrer"
                        size="sm"
                        target="_blank"
                        variant="secondary"
                    >
                        {t('student.reports.download')}
                    </Button>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('student.reports.not_available')}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout role="student">
            <Head title={t('student.reports.title')} />

            <PageHeader
                className="mb-6"
                subtitle={t('student.reports.subtitle')}
                title={t('student.reports.title')}
            />

            <DataTable
                caption={t('student.reports.table_caption')}
                columns={columns}
                emptyDescription={t(
                    'student.reports.empty_description',
                )}
                emptyTitle={t('student.reports.empty_title')}
                error={error}
                loading={loading}
                loadingLabel={t('student.reports.loading')}
                onRetry={() => router.reload()}
                rowKey={(report) => report.id}
                rows={reports}
            />
        </AppLayout>
    );
}
