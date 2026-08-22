import { Head, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import type { DataTableColumn } from '@/Components/DataTable';
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    Assignment,
    LoadablePageProps,
    StatusColorMap,
} from '@/types';

interface StudentAssignmentsIndexProps extends LoadablePageProps {
    assignments?: readonly Assignment[];
}

const submissionStatusColors: StatusColorMap = {
    not_submitted: 'neutral',
    open: 'brand',
    pending: 'warning',
    submitted: 'success',
    graded: 'success',
    returned: 'warning',
    late: 'danger',
    overdue: 'danger',
};

export default function Index({
    assignments = [],
    loading = false,
    error = null,
}: StudentAssignmentsIndexProps) {
    const t = useI18n();
    const locale = useLocale();

    const columns: readonly DataTableColumn<Assignment>[] = [
        {
            key: 'assignment',
            header: t('student.assignments.assignment'),
            render: (assignment) => (
                <div className="min-w-48">
                    <p className="font-semibold text-[var(--ink)]">
                        {assignment.title}
                    </p>
                    <p className="mt-1 text-sm text-[var(--ink-muted)]">
                        {assignment.courseName}
                    </p>
                </div>
            ),
        },
        {
            key: 'due_at',
            header: t('student.assignments.due_at'),
            render: (assignment) => (
                <time dateTime={assignment.dueAt}>
                    {formatDateTime(assignment.dueAt, locale)}
                </time>
            ),
        },
        {
            key: 'submission_status',
            header: t('student.assignments.submission_status'),
            render: (assignment) => (
                <StatusPill
                    colorMap={submissionStatusColors}
                    status={assignment.submissionStatus}
                />
            ),
        },
        {
            key: 'submitted_at',
            header: t('student.assignments.submitted_at'),
            render: (assignment) =>
                assignment.submittedAt ? (
                    <time dateTime={assignment.submittedAt}>
                        {formatDateTime(assignment.submittedAt, locale)}
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
            render: (assignment) =>
                assignment.url ? (
                    <Button
                        aria-label={
                            t('student.assignments.open') +
                            ' ' +
                            assignment.title
                        }
                        as="link"
                        href={assignment.url}
                        size="sm"
                        variant="secondary"
                    >
                        {t('student.assignments.open')}
                    </Button>
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout role="student">
            <Head title={t('student.assignments.title')} />

            <PageHeader
                className="mb-6"
                subtitle={t('student.assignments.subtitle')}
                title={t('student.assignments.title')}
            />

            <DataTable
                caption={t('student.assignments.table_caption')}
                columns={columns}
                emptyDescription={t(
                    'student.assignments.empty_description',
                )}
                emptyTitle={t('student.assignments.empty_title')}
                error={error}
                loading={loading}
                loadingLabel={t('student.assignments.loading')}
                onRetry={() => router.reload()}
                rowKey={(assignment) => assignment.id}
                rows={assignments}
            />
        </AppLayout>
    );
}
