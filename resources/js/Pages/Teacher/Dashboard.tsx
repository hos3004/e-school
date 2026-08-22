import { Head, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import Card, {
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    LoadablePageProps,
    Session,
    StatusColorMap,
} from '@/types';

interface TeacherDashboardProps extends LoadablePageProps {
    todaysSessions?: Session[];
    pendingAttendance?: Session[];
    lateReports?: Session[];
    statusColors?: StatusColorMap;
}

interface SessionCardProps {
    actionLabelKey: string;
    session: Session;
    statusColors: StatusColorMap;
}

function SessionCard({
    actionLabelKey,
    session,
    statusColors,
}: SessionCardProps) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <Card as="article" className="flex h-full flex-col" padding="md">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <CardTitle>{session.title}</CardTitle>
                        {session.subject ? (
                            <CardDescription>{session.subject}</CardDescription>
                        ) : null}
                    </div>
                    <StatusPill
                        colorMap={statusColors}
                        status={session.status}
                    />
                </div>
            </CardHeader>

            <CardContent className="mt-4 grow">
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="font-semibold text-[var(--ink-muted)]">
                            {t('common.date')}
                        </dt>
                        <dd className="mt-1 text-[var(--ink)]">
                            <time dateTime={session.startsAt}>
                                {formatDate(
                                    session.startsAt,
                                    locale,
                                    session.timezone,
                                )}
                            </time>
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold text-[var(--ink-muted)]">
                            {t('common.time')}
                        </dt>
                        <dd className="mt-1 text-[var(--ink)]">
                            <time dateTime={session.startsAt}>
                                {formatTime(
                                    session.startsAt,
                                    locale,
                                    session.timezone,
                                )}
                            </time>
                            <span aria-hidden="true">
                                {t('common.time_range_separator')}
                            </span>
                            <span className="sr-only">
                                {t('common.until')}
                            </span>
                            <time dateTime={session.endsAt}>
                                {formatTime(
                                    session.endsAt,
                                    locale,
                                    session.timezone,
                                )}
                            </time>
                        </dd>
                    </div>
                    {session.location ? (
                        <div className="sm:col-span-2">
                            <dt className="font-semibold text-[var(--ink-muted)]">
                                {t('sessions.location')}
                            </dt>
                            <dd className="mt-1 break-words text-[var(--ink)]">
                                {session.location}
                            </dd>
                        </div>
                    ) : null}
                </dl>
            </CardContent>

            <CardFooter className="mt-5">
                <Button
                    as="link"
                    href={'/teacher/sessions/' + session.id}
                    variant="secondary"
                >
                    {t(actionLabelKey)}
                </Button>
            </CardFooter>
        </Card>
    );
}

interface SessionSectionProps {
    actionLabelKey: string;
    descriptionKey: string;
    emptyDescriptionKey: string;
    emptyTitleKey: string;
    sessions: Session[];
    statusColors: StatusColorMap;
    titleKey: string;
}

function SessionSection({
    actionLabelKey,
    descriptionKey,
    emptyDescriptionKey,
    emptyTitleKey,
    sessions,
    statusColors,
    titleKey,
}: SessionSectionProps) {
    const t = useI18n();

    return (
        <section aria-labelledby={titleKey}>
            <div className="mb-4">
                <h2
                    className="text-xl font-bold text-[var(--ink)]"
                    id={titleKey}
                >
                    {t(titleKey)}
                </h2>
                <p className="mt-1 text-sm leading-6 text-[var(--ink-muted)]">
                    {t(descriptionKey)}
                </p>
            </div>

            {sessions.length === 0 ? (
                <EmptyState
                    description={t(emptyDescriptionKey)}
                    title={t(emptyTitleKey)}
                />
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {sessions.map((session) => (
                        <SessionCard
                            actionLabelKey={actionLabelKey}
                            key={session.id}
                            session={session}
                            statusColors={statusColors}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

export default function TeacherDashboard({
    todaysSessions = [],
    pendingAttendance = [],
    lateReports = [],
    loading = false,
    error = null,
    statusColors = {},
}: TeacherDashboardProps) {
    const t = useI18n();
    const retry = () => {
        router.reload({
            only: [
                'todaysSessions',
                'pendingAttendance',
                'lateReports',
                'statusColors',
                'error',
            ],
        });
    };
    const isEmpty =
        todaysSessions.length === 0 &&
        pendingAttendance.length === 0 &&
        lateReports.length === 0;

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.dashboard.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={t('teacher.dashboard.subtitle')}
                    title={t('teacher.dashboard.title')}
                />

                {loading ? (
                    <LoadingState
                        label={t('teacher.dashboard.loading')}
                        rows={3}
                    />
                ) : error !== null && error !== undefined ? (
                    <ErrorState
                        message={error || t('states.error.message')}
                        onRetry={retry}
                    />
                ) : isEmpty ? (
                    <EmptyState
                        description={t('teacher.dashboard.empty.description')}
                        title={t('teacher.dashboard.empty.title')}
                    />
                ) : (
                    <div className="space-y-8">
                        <SessionSection
                            actionLabelKey="teacher.dashboard.actions.open_session"
                            descriptionKey="teacher.dashboard.today.description"
                            emptyDescriptionKey="teacher.dashboard.today.empty.description"
                            emptyTitleKey="teacher.dashboard.today.empty.title"
                            sessions={todaysSessions}
                            statusColors={statusColors}
                            titleKey="teacher.dashboard.today.title"
                        />
                        <SessionSection
                            actionLabelKey="teacher.dashboard.actions.confirm_attendance"
                            descriptionKey="teacher.dashboard.pending_attendance.description"
                            emptyDescriptionKey="teacher.dashboard.pending_attendance.empty.description"
                            emptyTitleKey="teacher.dashboard.pending_attendance.empty.title"
                            sessions={pendingAttendance}
                            statusColors={statusColors}
                            titleKey="teacher.dashboard.pending_attendance.title"
                        />
                        <SessionSection
                            actionLabelKey="teacher.dashboard.actions.submit_report"
                            descriptionKey="teacher.dashboard.late_reports.description"
                            emptyDescriptionKey="teacher.dashboard.late_reports.empty.description"
                            emptyTitleKey="teacher.dashboard.late_reports.empty.title"
                            sessions={lateReports}
                            statusColors={statusColors}
                            titleKey="teacher.dashboard.late_reports.title"
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
