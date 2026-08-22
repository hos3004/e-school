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
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    Attendance,
    Child,
    LoadablePageProps,
    Session,
} from '@/types';

interface ChildAttendance extends Attendance {
    session: Session;
}

interface GuardianAttendanceProps extends LoadablePageProps {
    selectedChild: Child | null;
    attendances: readonly ChildAttendance[];
}

const attendanceStatusColors: StatusColorMap<string> = {
    present: 'success',
    absent: 'danger',
    late: 'warning',
    excused: 'brand',
    pending: 'neutral',
};

export default function GuardianChildAttendance({
    selectedChild,
    attendances,
    loading = false,
    error = null,
}: GuardianAttendanceProps) {
    const t = useI18n();
    const locale = useLocale();
    const retry = () => router.reload({ preserveScroll: true });

    const columns: readonly DataTableColumn<ChildAttendance>[] = [
        {
            key: 'session',
            header: t('guardian.attendance.columns.session'),
            render: (attendance) => (
                <div className="min-w-0">
                    <p className="font-semibold text-[var(--ink)]">
                        {attendance.session.title}
                    </p>
                    {attendance.session.subject ? (
                        <p className="mt-1 text-sm text-[var(--ink-muted)]">
                            {attendance.session.subject}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'date',
            header: t('guardian.attendance.columns.date'),
            render: (attendance) => (
                <time dateTime={attendance.session.startsAt}>
                    {formatDateTime(
                        attendance.session.startsAt,
                        locale,
                        attendance.session.timezone,
                    )}
                </time>
            ),
        },
        {
            key: 'status',
            header: t('guardian.attendance.columns.status'),
            render: (attendance) => (
                <StatusPill
                    colorMap={attendanceStatusColors}
                    status={attendance.status}
                />
            ),
        },
        {
            key: 'recorded_at',
            header: t('guardian.attendance.columns.recorded_at'),
            render: (attendance) =>
                attendance.recordedAt ? (
                    <time dateTime={attendance.recordedAt}>
                        {formatDateTime(attendance.recordedAt, locale)}
                    </time>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
        {
            key: 'note',
            header: t('guardian.attendance.columns.note'),
            render: (attendance) => (
                <span
                    className={
                        attendance.note
                            ? 'text-[var(--ink)]'
                            : 'text-[var(--ink-muted)]'
                    }
                >
                    {attendance.note ?? t('common.not_available')}
                </span>
            ),
        },
    ];

    return (
        <AppLayout role="guardian">
            <Head title={t('guardian.attendance.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={
                        selectedChild?.name ??
                        t('guardian.attendance.subtitle')
                    }
                    title={t('guardian.attendance.title')}
                />

                {!loading && error === null && selectedChild === null ? (
                    <EmptyState
                        description={t(
                            'guardian.attendance.no_child_description',
                        )}
                        title={t('guardian.attendance.no_child_title')}
                    />
                ) : (
                    <DataTable
                        caption={t('guardian.attendance.table_caption')}
                        columns={columns}
                        emptyDescription={t(
                            'guardian.attendance.empty_description',
                        )}
                        emptyTitle={t('guardian.attendance.empty_title')}
                        error={error}
                        loading={loading}
                        loadingLabel={t('guardian.attendance.loading')}
                        onRetry={retry}
                        rowKey={(attendance) => attendance.id}
                        rows={attendances}
                    />
                )}
            </div>
        </AppLayout>
    );
}
