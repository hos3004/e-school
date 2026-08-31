import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
import {
    formatDate,
    formatDateTime,
    formatTime,
    useLocale,
} from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps, Session, StatusColorMap } from '@/types';

interface StudentSessionShowProps extends LoadablePageProps {
    session?: Session | null;
}

const sessionStatusColors: StatusColorMap = {
    scheduled: 'brand',
    live: 'success',
    completed: 'neutral',
    postponed: 'warning',
    cancelled: 'danger',
};

function statusAllowsJoining(status: string): boolean {
    return ['scheduled', 'confirmed', 'in_progress'].includes(status);
}

function joinIsAvailable(session: Session, now: number): boolean {
    if (!session.joinUrl || !statusAllowsJoining(session.status)) {
        return false;
    }

    if (session.canJoin === true) {
        return session.canJoin;
    }

    if (!session.canJoinAt) {
        return false;
    }

    const threshold = Date.parse(session.canJoinAt);

    const closesAt = session.canJoinUntil
        ? Date.parse(session.canJoinUntil)
        : Number.POSITIVE_INFINITY;

    return (
        Number.isFinite(threshold) &&
        now >= threshold &&
        (!Number.isFinite(closesAt) || now <= closesAt)
    );
}

function useJoinClock(session: Session | null): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!session?.canJoinAt || session.canJoin === true) {
            return;
        }

        const threshold = Date.parse(session.canJoinAt);

        if (!Number.isFinite(threshold) || threshold <= now) {
            return;
        }

        const maximumDelay = 2_147_000_000;
        const delay = Math.min(
            Math.max(threshold - Date.now(), 0) + 50,
            maximumDelay,
        );
        const timer = window.setTimeout(() => setNow(Date.now()), delay);

        return () => window.clearTimeout(timer);
    }, [now, session]);

    return now;
}

export default function Show({
    session = null,
    loading = false,
    error = null,
}: StudentSessionShowProps) {
    const t = useI18n();
    const locale = useLocale();
    const now = useJoinClock(session);
    const pageTitle = session?.title ?? t('student.sessions.details_title');

    const content = (() => {
        if (loading) {
            return (
                <LoadingState
                    label={t('student.sessions.loading_details')}
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

        if (session === null) {
            return (
                <EmptyState
                    action={
                        <Button
                            as="link"
                            href="/student/schedule"
                            variant="secondary"
                        >
                            {t('student.sessions.back_to_schedule')}
                        </Button>
                    }
                    description={t('student.sessions.not_found_description')}
                    title={t('student.sessions.not_found')}
                />
            );
        }

        const canJoin = joinIsAvailable(session, now);

        return (
            <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
                <Card
                    as="section"
                    className="border-[color:var(--brand)]/25 bg-[linear-gradient(145deg,color-mix(in_srgb,var(--brand)_7%,var(--surface)),var(--surface)_55%)] shadow-md"
                    padding="lg"
                >
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <CardTitle as="h2">
                                    {t('student.sessions.details')}
                                </CardTitle>
                                {session.subject ? (
                                    <CardDescription className="mt-1">
                                        {session.subject}
                                    </CardDescription>
                                ) : null}
                            </div>
                            <StatusPill
                                colorMap={sessionStatusColors}
                                status={session.status}
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="mt-6">
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
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

                            <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
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
                                    <span className="sr-only">
                                        {t('common.to')}
                                    </span>
                                    <span
                                        aria-hidden="true"
                                        className="ps-2 pe-2 text-[var(--ink-muted)]"
                                    >
                                        {t('common.time_separator')}
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

                            <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
                                    {t('student.sessions.teacher')}
                                </dt>
                                <dd className="mt-1 text-[var(--ink)]">
                                    {session.teacher?.name ??
                                        t('common.not_available')}
                                </dd>
                            </div>

                            <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
                                    {t('student.sessions.location')}
                                </dt>
                                <dd className="mt-1 text-[var(--ink)]">
                                    {session.location ?? t('common.online')}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card
                        as="section"
                        className={
                            canJoin
                                ? 'border-[color:var(--success)]/35 bg-[color:var(--success)]/8 shadow-md'
                                : 'border-[color:var(--ink-muted)]/15'
                        }
                    >
                        <CardHeader>
                            <CardTitle as="h2">
                                {t('student.sessions.join_heading')}
                            </CardTitle>
                            <CardDescription>
                                {canJoin
                                    ? t(
                                          'student.sessions.join_ready_description',
                                      )
                                    : t(
                                          'student.sessions.join_locked_description',
                                      )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="mt-5">
                            <Button
                                as="link"
                                disabled={!canJoin}
                                className="min-h-12"
                                fullWidth
                                href={session.joinUrl ?? '#'}
                                rel="noopener noreferrer"
                                target="_blank"
                            >
                                {t('student.sessions.join')}
                            </Button>

                            {!canJoin && session.canJoinAt ? (
                                <p
                                    className="mt-3 text-sm leading-6 text-[var(--ink-muted)]"
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

                    {session.recordingUrl ? (
                        <Card
                            as="section"
                            className="border-[color:var(--brand)]/20"
                        >
                            <CardHeader>
                                <CardTitle as="h2">
                                    {t('student.sessions.recording_heading')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'student.sessions.recording_description',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="mt-5">
                                <Button
                                    as="link"
                                    fullWidth
                                    href={session.recordingUrl}
                                    rel="noopener noreferrer"
                                    target="_blank"
                                    variant="secondary"
                                >
                                    {t('student.sessions.watch_recording')}
                                </Button>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </div>
        );
    })();

    return (
        <AppLayout role="student">
            <Head title={pageTitle} />

            <StudentPageHero
                action={
                    <Button
                        as="link"
                        href="/student/schedule"
                        variant="secondary"
                    >
                        {t('student.sessions.back_to_schedule')}
                    </Button>
                }
                className="mb-8"
                subtitle={t('student.sessions.details_subtitle')}
                title={pageTitle}
            />

            {content}
        </AppLayout>
    );
}
