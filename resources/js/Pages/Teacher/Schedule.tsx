import { Head, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import type { DataTableColumn } from '@/Components/DataTable';
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

interface TeacherScheduleProps extends LoadablePageProps {
    sessions?: Session[];
    statusColors?: StatusColorMap;
}

export default function TeacherSchedule({
    sessions = [],
    loading = false,
    error = null,
    statusColors = {},
}: TeacherScheduleProps) {
    const t = useI18n();
    const locale = useLocale();
    const retry = () => {
        router.reload({
            only: ['sessions', 'statusColors', 'error'],
        });
    };

    const columns: readonly DataTableColumn<Session>[] = [
        {
            key: 'session',
            header: t('teacher.schedule.columns.session'),
            render: (session) => (
                <div className="min-w-48">
                    <p className="font-semibold text-[var(--ink)]">
                        {session.title}
                    </p>
                    {session.subject ? (
                        <p className="mt-1 text-sm text-[var(--ink-muted)]">
                            {session.subject}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'date',
            header: t('teacher.schedule.columns.date'),
            render: (session) => (
                <time dateTime={session.startsAt}>
                    {formatDate(
                        session.startsAt,
                        locale,
                        session.timezone,
                    )}
                </time>
            ),
        },
        {
            key: 'time',
            header: t('teacher.schedule.columns.time'),
            render: (session) => (
                <div className="whitespace-nowrap">
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
                    <span className="sr-only">{t('common.until')}</span>
                    <time dateTime={session.endsAt}>
                        {formatTime(
                            session.endsAt,
                            locale,
                            session.timezone,
                        )}
                    </time>
                </div>
            ),
        },
        {
            key: 'location',
            header: t('teacher.schedule.columns.location'),
            render: (session) => (
                <span className="break-words">
                    {session.location ?? t('common.not_available')}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('teacher.schedule.columns.status'),
            render: (session) => (
                <StatusPill
                    colorMap={statusColors}
                    status={session.status}
                />
            ),
        },
        {
            key: 'actions',
            header: t('teacher.schedule.columns.actions'),
            render: (session) => (
                <Button
                    as="link"
                    href={'/teacher/sessions/' + session.id}
                    size="sm"
                    variant="secondary"
                >
                    {t('teacher.schedule.actions.view')}
                </Button>
            ),
        },
    ];

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.schedule.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={t('teacher.schedule.subtitle')}
                    title={t('teacher.schedule.title')}
                />

                <DataTable
                    caption={t('teacher.schedule.table_caption')}
                    columns={columns}
                    emptyDescription={t(
                        'teacher.schedule.empty.description',
                    )}
                    emptyTitle={t('teacher.schedule.empty.title')}
                    error={error}
                    loading={loading}
                    loadingLabel={t('teacher.schedule.loading')}
                    onRetry={retry}
                    rowKey={(session) => session.id}
                    rows={sessions}
                />
            </div>
        </AppLayout>
    );
}
