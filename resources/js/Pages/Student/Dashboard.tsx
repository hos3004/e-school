import { Head, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import Card, {
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import {
    formatDateTime,
    formatPercent,
    useLocale,
} from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    Assignment,
    LoadablePageProps,
    Session,
    StatusColorMap,
} from '@/types';

interface StudentDashboardProps extends LoadablePageProps {
    nextSession?: Session | null;
    weekSessions?: readonly Session[];
    attendanceRate?: number | null;
    openAssignments?: readonly Assignment[];
}

const sessionStatusColors: StatusColorMap = {
    scheduled: 'brand',
    live: 'success',
    completed: 'neutral',
    postponed: 'warning',
    cancelled: 'danger',
};

const assignmentStatusColors: StatusColorMap = {
    open: 'brand',
    pending: 'warning',
    submitted: 'success',
    graded: 'success',
    overdue: 'danger',
    closed: 'neutral',
};

export default function Dashboard({
    nextSession = null,
    weekSessions = [],
    attendanceRate = null,
    openAssignments = [],
    loading = false,
    error = null,
}: StudentDashboardProps) {
    const t = useI18n();
    const locale = useLocale();
    const hasContent =
        nextSession !== null ||
        weekSessions.length > 0 ||
        openAssignments.length > 0 ||
        attendanceRate !== null;

    const content = (() => {
        if (loading) {
            return (
                <LoadingState
                    label={t('student.dashboard.loading')}
                    rows={4}
                />
            );
        }

        if (error !== null && error !== undefined) {
            return (
                <ErrorState
                    message={error || t('states.error.message')}
                    onRetry={() => router.reload()}
                />
            );
        }

        if (!hasContent) {
            return (
                <EmptyState
                    description={t('student.dashboard.empty_description')}
                    title={t('student.dashboard.empty_title')}
                />
            );
        }

        return (
            <div className="space-y-6">
                <section
                    aria-labelledby="student-next-session-heading"
                    className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]"
                >
                    {nextSession ? (
                        <Card as="article" padding="lg">
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-[var(--brand)]">
                                            {t('student.dashboard.next_session')}
                                        </p>
                                        <CardTitle
                                            as="h2"
                                            className="mt-1"
                                            id="student-next-session-heading"
                                        >
                                            {nextSession.title}
                                        </CardTitle>
                                    </div>
                                    <StatusPill
                                        colorMap={sessionStatusColors}
                                        status={nextSession.status}
                                    />
                                </div>
                                {nextSession.subject ? (
                                    <CardDescription>
                                        {nextSession.subject}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>

                            <CardContent className="mt-5">
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('common.date_and_time')}
                                        </dt>
                                        <dd className="mt-1 text-[var(--ink)]">
                                            {formatDateTime(
                                                nextSession.startsAt,
                                                locale,
                                                nextSession.timezone,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('student.sessions.teacher')}
                                        </dt>
                                        <dd className="mt-1 text-[var(--ink)]">
                                            {nextSession.teacher?.name ??
                                                t('common.not_available')}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-6 flex flex-wrap gap-3">
                                    <Button
                                        as="link"
                                        href={
                                            '/student/sessions/' +
                                            encodeURIComponent(nextSession.id)
                                        }
                                    >
                                        {t('student.sessions.view_details')}
                                    </Button>
                                    <Button
                                        as="link"
                                        href="/student/schedule"
                                        variant="secondary"
                                    >
                                        {t('student.dashboard.view_schedule')}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card as="section" padding="lg">
                            <CardHeader>
                                <CardTitle
                                    as="h2"
                                    id="student-next-session-heading"
                                >
                                    {t('student.dashboard.next_session')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="mt-4">
                                <EmptyState
                                    className="border-0 px-0 py-6 shadow-none"
                                    description={t(
                                        'student.dashboard.no_next_session_description',
                                    )}
                                    title={t(
                                        'student.dashboard.no_next_session',
                                    )}
                                />
                            </CardContent>
                        </Card>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <Card as="article">
                            <CardHeader>
                                <CardDescription>
                                    {t('student.dashboard.attendance_rate')}
                                </CardDescription>
                                <CardTitle as="h2" className="text-3xl">
                                    {attendanceRate === null
                                        ? t('common.not_available')
                                        : formatPercent(attendanceRate, locale)}
                                </CardTitle>
                            </CardHeader>
                        </Card>

                        <Card as="article">
                            <CardHeader>
                                <CardDescription>
                                    {t('student.dashboard.open_assignments')}
                                </CardDescription>
                                <CardTitle as="h2" className="text-3xl">
                                    {new Intl.NumberFormat(locale).format(
                                        openAssignments.length,
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="mt-4">
                                <Button
                                    as="link"
                                    fullWidth
                                    href="/student/assignments"
                                    variant="secondary"
                                >
                                    {t('student.dashboard.view_assignments')}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section aria-labelledby="student-week-sessions-heading">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2
                                className="text-xl font-bold text-[var(--ink)]"
                                id="student-week-sessions-heading"
                            >
                                {t('student.dashboard.week_sessions')}
                            </h2>
                            <p className="mt-1 text-sm text-[var(--ink-muted)]">
                                {t(
                                    'student.dashboard.week_sessions_description',
                                )}
                            </p>
                        </div>
                        <Button
                            as="link"
                            href="/student/schedule"
                            size="sm"
                            variant="ghost"
                        >
                            {t('actions.view_all')}
                        </Button>
                    </div>

                    {weekSessions.length === 0 ? (
                        <EmptyState
                            description={t(
                                'student.dashboard.no_week_sessions_description',
                            )}
                            title={t(
                                'student.dashboard.no_week_sessions',
                            )}
                        />
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {weekSessions.map((session) => (
                                <Card
                                    as="article"
                                    key={session.id}
                                    padding="sm"
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <CardTitle
                                                as="h3"
                                                className="text-base"
                                            >
                                                {session.title}
                                            </CardTitle>
                                            <StatusPill
                                                colorMap={
                                                    sessionStatusColors
                                                }
                                                status={session.status}
                                            />
                                        </div>
                                        <CardDescription>
                                            {formatDateTime(
                                                session.startsAt,
                                                locale,
                                                session.timezone,
                                            )}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="mt-4">
                                        <Button
                                            aria-label={
                                                t(
                                                    'student.sessions.view_details',
                                                ) +
                                                ' ' +
                                                session.title
                                            }
                                            as="link"
                                            fullWidth
                                            href={
                                                '/student/sessions/' +
                                                encodeURIComponent(session.id)
                                            }
                                            size="sm"
                                            variant="secondary"
                                        >
                                            {t(
                                                'student.sessions.view_details',
                                            )}
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>

                <section aria-labelledby="student-open-assignments-heading">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2
                                className="text-xl font-bold text-[var(--ink)]"
                                id="student-open-assignments-heading"
                            >
                                {t('student.dashboard.open_assignments')}
                            </h2>
                            <p className="mt-1 text-sm text-[var(--ink-muted)]">
                                {t(
                                    'student.dashboard.open_assignments_description',
                                )}
                            </p>
                        </div>
                        <Button
                            as="link"
                            href="/student/assignments"
                            size="sm"
                            variant="ghost"
                        >
                            {t('actions.view_all')}
                        </Button>
                    </div>

                    {openAssignments.length === 0 ? (
                        <EmptyState
                            description={t(
                                'student.dashboard.no_open_assignments_description',
                            )}
                            title={t(
                                'student.dashboard.no_open_assignments',
                            )}
                        />
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {openAssignments.slice(0, 4).map((assignment) => (
                                <Card
                                    as="article"
                                    key={assignment.id}
                                    padding="sm"
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <CardTitle
                                                as="h3"
                                                className="text-base"
                                            >
                                                {assignment.title}
                                            </CardTitle>
                                            <StatusPill
                                                colorMap={
                                                    assignmentStatusColors
                                                }
                                                status={
                                                    assignment.submissionStatus
                                                }
                                            />
                                        </div>
                                        <CardDescription>
                                            {assignment.courseName}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="mt-4">
                                        <dl className="text-sm">
                                            <div>
                                                <dt className="font-semibold text-[var(--ink-muted)]">
                                                    {t(
                                                        'student.assignments.due_at',
                                                    )}
                                                </dt>
                                                <dd className="mt-1">
                                                    {formatDateTime(
                                                        assignment.dueAt,
                                                        locale,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        );
    })();

    return (
        <AppLayout role="student">
            <Head title={t('student.dashboard.title')} />

            <PageHeader
                className="mb-6"
                subtitle={t('student.dashboard.subtitle')}
                title={t('student.dashboard.title')}
            />

            {content}
        </AppLayout>
    );
}
