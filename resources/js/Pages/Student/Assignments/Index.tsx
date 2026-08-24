import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import Button from '@/Components/Button';
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
import type { Assignment, LoadablePageProps } from '@/types';

interface StudentAssignmentsIndexProps extends LoadablePageProps {
    assignments?: readonly Assignment[];
}

const submissionStatusColors: StatusColorMap<string> = {
    not_submitted: 'neutral',
    open: 'brand',
    pending: 'warning',
    submitted: 'success',
    graded: 'success',
    returned: 'warning',
    late: 'danger',
    overdue: 'danger',
};

function SubmissionForm({ assignment }: { assignment: Assignment }) {
    const t = useI18n();
    const form = useForm({
        content: assignment.submissionContent ?? '',
    });

    return (
        <form
            className="mt-4 border-t border-[var(--surface-muted)] pt-4"
            onSubmit={(event) => {
                event.preventDefault();

                if (!assignment.submitUrl) {
                    return;
                }

                form.post(assignment.submitUrl, { preserveScroll: true });
            }}
        >
            <label className="block text-sm">
                {t('student.assignments.answer_label')}
                <textarea
                    className="mt-1 w-full rounded-lg border border-[var(--ink-muted)] bg-[var(--surface)] p-3 text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                    onChange={(event) =>
                        form.setData('content', event.target.value)
                    }
                    placeholder={t('student.assignments.answer_placeholder')}
                    rows={5}
                    value={form.data.content}
                />
            </label>

            {form.errors.content ? (
                <p className="mt-1 text-xs text-[var(--danger)]">
                    {form.errors.content}
                </p>
            ) : null}

            <div className="mt-3 flex flex-wrap items-center gap-3">
                <Button disabled={form.processing} type="submit">
                    {form.processing
                        ? t('actions.processing')
                        : t('student.assignments.submit')}
                </Button>

                {assignment.status === 'closed' && assignment.allowsLate ? (
                    <span className="text-xs text-[var(--ink-muted)]">
                        {t('student.assignments.late_warning')}
                        {assignment.latePenaltyPercent
                            ? ` (${assignment.latePenaltyPercent}%)`
                            : ''}
                    </span>
                ) : null}
            </div>
        </form>
    );
}

export default function Index({
    assignments = [],
    loading = false,
    error = null,
}: StudentAssignmentsIndexProps) {
    const t = useI18n();
    const locale = useLocale();
    const [expanded, setExpanded] = useState<string | null>(null);

    return (
        <AppLayout role="student">
            <Head title={t('student.assignments.title')} />

            <PageHeader
                className="mb-6"
                subtitle={t('student.assignments.subtitle')}
                title={t('student.assignments.title')}
            />

            {loading ? (
                <LoadingState
                    label={t('student.assignments.loading')}
                    rows={4}
                />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : assignments.length === 0 ? (
                <EmptyState
                    title={t('student.assignments.empty_title')}
                    description={t('student.assignments.empty_description')}
                />
            ) : (
                <div className="space-y-4">
                    {assignments.map((assignment) => {
                        const isOpen = expanded === assignment.id;

                        return (
                            <Card key={assignment.id}>
                                <CardHeader className="mb-3">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <CardTitle>
                                                {assignment.title}
                                            </CardTitle>
                                            <CardDescription>
                                                {assignment.courseName}
                                            </CardDescription>
                                        </div>
                                        <StatusPill
                                            colorMap={submissionStatusColors}
                                            status={assignment.submissionStatus}
                                        />
                                    </div>
                                </CardHeader>

                                <dl className="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <dt className="text-xs text-[var(--ink-muted)]">
                                            {t('student.assignments.due_at')}
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                            <time dateTime={assignment.dueAt}>
                                                {formatDateTime(
                                                    assignment.dueAt,
                                                    locale,
                                                )}
                                            </time>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-[var(--ink-muted)]">
                                            {t(
                                                'student.assignments.submitted_at',
                                            )}
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                            {assignment.submittedAt ? (
                                                <time
                                                    dateTime={
                                                        assignment.submittedAt
                                                    }
                                                >
                                                    {formatDateTime(
                                                        assignment.submittedAt,
                                                        locale,
                                                    )}
                                                </time>
                                            ) : (
                                                t('common.not_available')
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-[var(--ink-muted)]">
                                            {t('student.assignments.score')}
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                            {assignment.score === null ||
                                            assignment.score === undefined
                                                ? t('common.not_available')
                                                : `${assignment.score} / ${assignment.maxScore ?? 0}`}
                                        </dd>
                                    </div>
                                </dl>

                                {assignment.instructions ? (
                                    <p className="mt-4 whitespace-pre-line text-sm leading-6 text-[var(--ink-muted)]">
                                        {assignment.instructions}
                                    </p>
                                ) : null}

                                {assignment.feedback ? (
                                    <div className="mt-4 rounded-lg bg-[var(--surface-muted)] p-4">
                                        <p className="text-xs font-bold text-[var(--ink)]">
                                            {t(
                                                'student.assignments.teacher_feedback',
                                            )}
                                        </p>
                                        <p className="mt-1 whitespace-pre-line text-sm text-[var(--ink)]">
                                            {assignment.feedback}
                                        </p>
                                    </div>
                                ) : null}

                                {assignment.canSubmit ? (
                                    isOpen ? (
                                        <SubmissionForm
                                            assignment={assignment}
                                        />
                                    ) : (
                                        <div className="mt-4">
                                            <Button
                                                onClick={() =>
                                                    setExpanded(assignment.id)
                                                }
                                                size="sm"
                                                variant="secondary"
                                            >
                                                {assignment.submittedAt
                                                    ? t(
                                                          'student.assignments.resubmit',
                                                      )
                                                    : t(
                                                          'student.assignments.submit',
                                                      )}
                                            </Button>
                                        </div>
                                    )
                                ) : (
                                    <p className="mt-4 text-xs text-[var(--ink-muted)]">
                                        {assignment.submissionStatus ===
                                        'graded'
                                            ? t(
                                                  'student.assignments.closed_graded',
                                              )
                                            : t(
                                                  'student.assignments.closed_late',
                                              )}
                                    </p>
                                )}
                            </Card>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}
