import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { StudentPageHero } from '@/Pages/Student/Partials/StudentUi';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps, Session, StatusColorMap } from '@/types';

interface StudentScheduleProps extends LoadablePageProps {
    sessions?: readonly Session[];
}

const sessionStatusColors: StatusColorMap = {
    scheduled: 'brand',
    live: 'success',
    completed: 'neutral',
    postponed: 'warning',
    cancelled: 'danger',
};

function joinIsAvailable(session: Session, now: number): boolean {
    if (!session.joinUrl) {
        return false;
    }

    if (typeof session.canJoin === 'boolean') {
        return session.canJoin;
    }

    if (!session.canJoinAt) {
        return false;
    }

    const threshold = Date.parse(session.canJoinAt);

    return Number.isFinite(threshold) && now >= threshold;
}

function useJoinClock(sessions: readonly Session[]): number {
    const [now, setNow] = useState(() => Date.now());

    const nextThreshold = useMemo(() => {
        const futureThresholds = sessions
            .filter(
                (session) => session.canJoin === undefined && session.canJoinAt,
            )
            .map((session) => Date.parse(session.canJoinAt ?? ''))
            .filter(
                (threshold) => Number.isFinite(threshold) && threshold > now,
            );

        return futureThresholds.length > 0
            ? Math.min(...futureThresholds)
            : null;
    }, [now, sessions]);

    useEffect(() => {
        if (nextThreshold === null) {
            return;
        }

        const maximumDelay = 2_147_000_000;
        const delay = Math.min(
            Math.max(nextThreshold - Date.now(), 0) + 50,
            maximumDelay,
        );
        const timer = window.setTimeout(() => setNow(Date.now()), delay);

        return () => window.clearTimeout(timer);
    }, [nextThreshold]);

    return now;
}

export default function Schedule({
    sessions = [],
    loading = false,
    error = null,
}: StudentScheduleProps) {
    const t = useI18n();
    const locale = useLocale();
    const now = useJoinClock(sessions);

    const content = (() => {
        if (loading) {
            return (
                <LoadingState label={t('student.schedule.loading')} rows={5} />
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

        if (sessions.length === 0) {
            return (
                <EmptyState
                    description={t('student.schedule.empty_description')}
                    title={t('student.schedule.empty_title')}
                />
            );
        }

        return (
            <section
                aria-label={t('student.schedule.upcoming_sessions')}
                className="grid gap-4 xl:grid-cols-2"
            >
                {sessions.map((session) => {
                    const canJoin = joinIsAvailable(session, now);

                    return (
                        <Card
                            as="article"
                            className="relative overflow-hidden border-[color:var(--ink-muted)]/15 shadow-sm transition-[border-color,box-shadow] duration-150 hover:border-[color:var(--brand)]/40 hover:shadow-md"
                            key={session.id}
                        >
                            <div
                                aria-hidden="true"
                                className="absolute inset-y-0 start-0 w-1 bg-[var(--brand)]"
                            />
                            <div className="flex flex-col gap-5 ps-2">
                                <CardHeader className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-start gap-3">
                                        <CardTitle as="h2">
                                            {session.title}
                                        </CardTitle>
                                        <StatusPill
                                            colorMap={sessionStatusColors}
                                            status={session.status}
                                        />
                                    </div>
                                    {session.subject ? (
                                        <CardDescription>
                                            {session.subject}
                                        </CardDescription>
                                    ) : null}
                                </CardHeader>

                                <div className="grid w-full grid-cols-2 gap-3 sm:flex sm:flex-wrap">
                                    <Button
                                        as="link"
                                        href={
                                            '/student/sessions/' +
                                            encodeURIComponent(session.id)
                                        }
                                        variant="secondary"
                                    >
                                        {t('student.sessions.view_details')}
                                    </Button>
                                    <Button
                                        aria-label={
                                            t('student.sessions.join') +
                                            ' ' +
                                            session.title
                                        }
                                        as="link"
                                        disabled={!canJoin}
                                        href={session.joinUrl ?? '#'}
                                        rel="noopener noreferrer"
                                        target="_blank"
                                    >
                                        {t('student.sessions.join')}
                                    </Button>
                                </div>
                            </div>

                            <CardContent className="mt-5 ps-2">
                                <dl className="grid gap-3 text-sm sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                                    <div className="rounded-xl bg-[var(--surface-muted)] px-3 py-3">
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('common.date_and_time')}
                                        </dt>
                                        <dd className="mt-1">
                                            <time dateTime={session.startsAt}>
                                                {formatDateTime(
                                                    session.startsAt,
                                                    locale,
                                                    session.timezone,
                                                )}
                                            </time>
                                        </dd>
                                    </div>

                                    <div className="rounded-xl bg-[var(--surface-muted)] px-3 py-3">
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('student.sessions.teacher')}
                                        </dt>
                                        <dd className="mt-1">
                                            {session.teacher?.name ??
                                                t('common.not_available')}
                                        </dd>
                                    </div>

                                    <div className="rounded-xl bg-[var(--surface-muted)] px-3 py-3">
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('student.sessions.location')}
                                        </dt>
                                        <dd className="mt-1">
                                            {session.location ??
                                                t('common.online')}
                                        </dd>
                                    </div>
                                </dl>

                                {!canJoin && session.canJoinAt ? (
                                    <p
                                        className="mt-4 rounded-xl border border-[color:var(--warning)]/25 bg-[color:var(--warning)]/8 px-4 py-3 text-sm leading-6 text-[var(--ink-muted)]"
                                        role="status"
                                    >
                                        <span className="font-semibold text-[var(--ink)]">
                                            {t(
                                                'student.sessions.join_available_at',
                                            )}
                                        </span>{' '}
                                        <time dateTime={session.canJoinAt}>
                                            {formatDateTime(
                                                session.canJoinAt,
                                                locale,
                                                session.timezone,
                                            )}
                                        </time>
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    );
                })}
            </section>
        );
    })();

    return (
        <AppLayout role="student">
            <Head title={t('student.schedule.title')} />

            <StudentPageHero
                action={
                    <div className="flex min-h-11 items-center gap-3 rounded-2xl border border-[color:var(--brand)]/20 bg-[var(--surface)]/80 px-4 py-2 shadow-sm">
                        <strong className="text-2xl font-bold tabular-nums text-[var(--ink)]">
                            {new Intl.NumberFormat(locale).format(
                                sessions.length,
                            )}
                        </strong>
                        <span className="max-w-32 text-sm font-semibold leading-5 text-[var(--ink-muted)]">
                            {t('student.schedule.upcoming_sessions')}
                        </span>
                    </div>
                }
                className="mb-8"
                subtitle={t('student.schedule.subtitle')}
                title={t('student.schedule.title')}
            />

            {content}
        </AppLayout>
    );
}
