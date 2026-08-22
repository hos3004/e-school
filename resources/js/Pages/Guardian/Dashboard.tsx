import { Head, router, usePage } from '@inertiajs/react';
import type { ChangeEvent } from 'react';

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
    Assignment,
    Child,
    LoadablePageProps,
    MonthlyReport,
    Session,
} from '@/types';

interface GuardianDashboardProps extends LoadablePageProps {
    children: readonly Child[];
    selectedChild: Child | null;
    nextSession: Session | null;
    attendanceRate: number | null;
    openAssignments: readonly Assignment[];
    reports: readonly MonthlyReport[];
}

const sessionStatusColors: StatusColorMap<string> = {
    scheduled: 'brand',
    live: 'success',
    completed: 'neutral',
    postponed: 'warning',
    cancelled: 'danger',
};

const reportStatusColors: StatusColorMap<string> = {
    published: 'success',
    available: 'success',
    draft: 'neutral',
    pending: 'warning',
    archived: 'neutral',
};

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function GuardianDashboard({
    children,
    selectedChild,
    nextSession,
    attendanceRate,
    openAssignments,
    reports,
    loading = false,
    error = null,
}: GuardianDashboardProps) {
    const t = useI18n();
    const locale = useLocale();
    const page = usePage();
    const retry = () => router.reload({ preserveScroll: true });

    const changeChild = (event: ChangeEvent<HTMLSelectElement>) => {
        const childId = event.target.value;

        if (childId === '' || childId === selectedChild?.id) {
            return;
        }

        const currentPath = page.url.split(/[?#]/, 1)[0] ?? page.url;

        router.get(
            currentPath,
            { child: childId },
            { replace: true, preserveScroll: true },
        );
    };

    const renderDashboard = () => {
        if (loading) {
            return (
                <LoadingState
                    label={t('guardian.dashboard.loading')}
                    rows={4}
                />
            );
        }

        if (error !== null) {
            return (
                <ErrorState
                    message={error || t('guardian.dashboard.error')}
                    onRetry={retry}
                />
            );
        }

        if (children.length === 0 || selectedChild === null) {
            return (
                <EmptyState
                    description={t('guardian.dashboard.empty_description')}
                    title={t('guardian.dashboard.empty_title')}
                />
            );
        }

        const nextAssignment = openAssignments[0] ?? null;
        const latestReport = reports[0] ?? null;

        return (
            <div className="space-y-6" key={selectedChild.id}>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card as="section" variant="outlined">
                        <CardHeader>
                            <CardDescription>
                                {t('guardian.dashboard.attendance_rate')}
                            </CardDescription>
                            <CardTitle as="h2">
                                {attendanceRate === null
                                    ? t('common.not_available')
                                    : formatPercent(attendanceRate, locale)}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card as="section" variant="outlined">
                        <CardHeader>
                            <CardDescription>
                                {t('guardian.dashboard.open_assignments')}
                            </CardDescription>
                            <CardTitle as="h2">
                                {new Intl.NumberFormat(locale).format(
                                    openAssignments.length,
                                )}
                            </CardTitle>
                        </CardHeader>
                        {nextAssignment !== null ? (
                            <CardContent className="mt-3">
                                <p className="truncate text-sm font-semibold text-[var(--ink)]">
                                    {nextAssignment.title}
                                </p>
                                <time
                                    className="mt-1 block text-sm text-[var(--ink-muted)]"
                                    dateTime={nextAssignment.dueAt}
                                >
                                    {formatDateTime(
                                        nextAssignment.dueAt,
                                        locale,
                                    )}
                                </time>
                            </CardContent>
                        ) : null}
                    </Card>

                    <Card as="section" variant="outlined">
                        <CardHeader>
                            <CardDescription>
                                {t('guardian.dashboard.reports')}
                            </CardDescription>
                            <CardTitle as="h2">
                                {new Intl.NumberFormat(locale).format(
                                    reports.length,
                                )}
                            </CardTitle>
                        </CardHeader>
                        {latestReport !== null ? (
                            <CardContent className="mt-3 flex min-w-0 items-center justify-between gap-3">
                                <span className="min-w-0 truncate text-sm font-semibold">
                                    {latestReport.title}
                                </span>
                                <StatusPill
                                    colorMap={reportStatusColors}
                                    status={latestReport.status}
                                />
                            </CardContent>
                        ) : null}
                    </Card>

                    <Card as="section" variant="outlined">
                        <CardHeader>
                            <CardDescription>
                                {t('guardian.dashboard.child_status')}
                            </CardDescription>
                            <CardTitle as="h2">
                                {selectedChild.name}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="mt-3">
                            <StatusPill
                                colorMap={{
                                    active: 'success',
                                    suspended: 'warning',
                                    inactive: 'neutral',
                                }}
                                status={selectedChild.status}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card as="section" variant="outlined">
                    <CardHeader>
                        <CardTitle as="h2">
                            {t('guardian.dashboard.next_session')}
                        </CardTitle>
                    </CardHeader>

                    {nextSession === null ? (
                        <CardContent className="mt-4">
                            <p className="text-sm text-[var(--ink-muted)]">
                                {t(
                                    'guardian.dashboard.no_upcoming_session',
                                )}
                            </p>
                        </CardContent>
                    ) : (
                        <CardContent className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div className="min-w-0">
                                <p className="truncate text-base font-bold text-[var(--ink)]">
                                    {nextSession.title}
                                </p>
                                {nextSession.subject ? (
                                    <p className="mt-1 truncate text-sm text-[var(--ink-muted)]">
                                        {nextSession.subject}
                                    </p>
                                ) : null}
                                <time
                                    className="mt-2 block text-sm font-medium text-[var(--ink)]"
                                    dateTime={nextSession.startsAt}
                                >
                                    {formatDateTime(
                                        nextSession.startsAt,
                                        locale,
                                        nextSession.timezone,
                                    )}
                                </time>
                            </div>
                            <StatusPill
                                colorMap={sessionStatusColors}
                                status={nextSession.status}
                            />
                        </CardContent>
                    )}
                </Card>

                <Card as="section" variant="outlined">
                    <CardHeader>
                        <CardTitle as="h2">
                            {t('guardian.dashboard.recent_reports')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="mt-4">
                        {reports.length === 0 ? (
                            <p className="text-sm text-[var(--ink-muted)]">
                                {t('guardian.dashboard.no_reports')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-[var(--ink-muted)]/20">
                                {reports.slice(0, 3).map((report) => (
                                    <li
                                        className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                        key={report.id}
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-[var(--ink)]">
                                                {report.title}
                                            </p>
                                            {report.issuedAt ? (
                                                <time
                                                    className="mt-1 block text-sm text-[var(--ink-muted)]"
                                                    dateTime={report.issuedAt}
                                                >
                                                    {formatDateTime(
                                                        report.issuedAt,
                                                        locale,
                                                    )}
                                                </time>
                                            ) : null}
                                        </div>
                                        <StatusPill
                                            colorMap={reportStatusColors}
                                            status={report.status}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        );
    };

    return (
        <AppLayout role="guardian">
            <Head title={t('guardian.dashboard.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={
                        selectedChild?.name ??
                        t('guardian.dashboard.subtitle')
                    }
                    title={t('guardian.dashboard.title')}
                />

                <Card
                    as="section"
                    aria-labelledby="guardian-child-switcher-label"
                    padding="sm"
                    variant="outlined"
                >
                    <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(14rem,22rem)] sm:items-center">
                        <div>
                            <label
                                className="block text-sm font-bold text-[var(--ink)]"
                                htmlFor="guardian-child-switcher"
                                id="guardian-child-switcher-label"
                            >
                                {t('guardian.dashboard.choose_child')}
                            </label>
                            <p className="mt-1 text-sm text-[var(--ink-muted)]">
                                {t(
                                    'guardian.dashboard.choose_child_description',
                                )}
                            </p>
                        </div>
                        <select
                            aria-label={t(
                                'guardian.dashboard.choose_child',
                            )}
                            className={[
                                'min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/40 bg-[var(--surface)] px-3 py-2 text-sm font-semibold text-[var(--ink)]',
                                focusRing,
                            ].join(' ')}
                            disabled={children.length === 0 || loading}
                            id="guardian-child-switcher"
                            onChange={changeChild}
                            value={selectedChild?.id ?? ''}
                        >
                            <option disabled value="">
                                {t(
                                    'guardian.dashboard.select_child_placeholder',
                                )}
                            </option>
                            {children.map((child) => (
                                <option key={child.id} value={child.id}>
                                    {child.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </Card>

                {renderDashboard()}
            </div>
        </AppLayout>
    );
}
