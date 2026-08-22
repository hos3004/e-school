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
import PageHeader from '@/Components/PageHeader';
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    LoadablePageProps,
    Session,
    StatusColorMap,
} from '@/types';

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
                (session) =>
                    session.canJoin === undefined && session.canJoinAt,
            )
            .map((session) => Date.parse(session.canJoinAt ?? ''))
            .filter(
                (threshold) =>
                    Number.isFinite(threshold) && threshold > now,
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
                <LoadingState
                    label={t('student.schedule.loading')}
                    rows={5}
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

        if (sessions.length === 0) {
            return (
                <EmptyState
                    description={t(
                        'student.schedule.empty_description',
                    )}
                    title={t('student.schedule.empty_title')}
                />
            );
        }

        return (
            <section
                aria-label={t('student.schedule.upcoming_sessions')}
                className="space-y-4"
            >
                {sessions.map((session) => {
                    const canJoin = joinIsAvailable(session, now);

                    return (
                        <Card as="article" key={session.id}>
                            <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
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

                                <div className="flex shrink-0 flex-wrap gap-3">
                                    <Button
                                        as="link"
                                        href={
                                            '/student/sessions/' +
                                            encodeURIComponent(session.id)
                                        }
                                        variant="secondary"
                                    >
                                        {t(
                                            'student.sessions.view_details',
                                        )}
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

                            <CardContent className="mt-5">
                                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
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

                                    <div>
                                        <dt className="font-semibold text-[var(--ink-muted)]">
                                            {t('student.sessions.teacher')}
                                        </dt>
                                        <dd className="mt-1">
                                            {session.teacher?.name ??
                                                t('common.not_available')}
                                        </dd>
                                    </div>

                                    <div>
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
                                        className="mt-4 rounded-lg bg-[var(--surface-muted)] px-3 py-2 text-sm text-[var(--ink-muted)]"
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

            <PageHeader
                className="mb-6"
                subtitle={t('student.schedule.subtitle')}
                title={t('student.schedule.title')}
            />

            {content}
        </AppLayout>
    );
}
