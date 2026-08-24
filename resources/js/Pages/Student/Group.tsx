import { Head, router } from '@inertiajs/react';

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
import { formatDate, formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps } from '@/types';

interface GroupTeacher {
    id: string;
    name: string;
    courseName?: string | null;
}

interface GroupProgram {
    id: string;
    code: string;
    name: string;
}

interface Classmate {
    id: string;
    name: string;
}

interface NextSession {
    id: string;
    title: string;
    startsAt: string;
    endsAt: string;
    timezone?: string;
}

interface StudentGroup {
    id: string;
    code: string;
    name: string;
    capacity: number;
    membersCount: number;
    status: string;
    timezone: string;
    startsOn?: string | null;
    endsOn?: string | null;
    joinedAt?: string | null;
    teachers: readonly GroupTeacher[];
    programs: readonly GroupProgram[];
    classmates: readonly Classmate[];
    nextSession?: NextSession | null;
}

interface Props extends LoadablePageProps {
    groups?: readonly StudentGroup[];
}

const groupStatusColors: StatusColorMap<string> = {
    planning: 'warning',
    active: 'success',
    completed: 'neutral',
};

export default function Group({
    groups = [],
    loading = false,
    error = null,
}: Props) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <AppLayout role="student">
            <Head title={t('student.group.title')} />
            <PageHeader
                className="mb-6"
                title={t('student.group.title')}
                subtitle={t('student.group.subtitle')}
            />

            {loading ? (
                <LoadingState label={t('student.group.loading')} rows={3} />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : groups.length === 0 ? (
                <EmptyState
                    title={t('student.group.empty_title')}
                    description={t('student.group.empty_description')}
                />
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => (
                        <Card key={group.id}>
                            <CardHeader className="mb-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-mono text-sm text-[var(--brand)]">
                                            {group.code}
                                        </p>
                                        <CardTitle className="mt-1">
                                            {group.name}
                                        </CardTitle>
                                    </div>
                                    <StatusPill
                                        colorMap={groupStatusColors}
                                        status={group.status}
                                    />
                                </div>
                                {group.programs.length > 0 ? (
                                    <CardDescription>
                                        {group.programs
                                            .map((program) => program.name)
                                            .join(' · ')}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>

                            <dl className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('student.group.capacity')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {group.membersCount} / {group.capacity}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('student.group.starts_on')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {group.startsOn
                                            ? formatDate(group.startsOn, locale)
                                            : t('common.not_available')}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('student.group.joined_at')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {group.joinedAt
                                            ? formatDate(group.joinedAt, locale)
                                            : t('common.not_available')}
                                    </dd>
                                </div>
                            </dl>

                            <section className="mt-6">
                                <h3 className="text-sm font-bold text-[var(--ink)]">
                                    {t('student.group.teachers')}
                                </h3>
                                {group.teachers.length === 0 ? (
                                    <p className="mt-2 text-sm text-[var(--ink-muted)]">
                                        {t('student.group.no_teachers')}
                                    </p>
                                ) : (
                                    <ul className="mt-2 space-y-2">
                                        {group.teachers.map((teacher) => (
                                            <li
                                                className="flex flex-wrap items-center gap-2 text-sm"
                                                key={teacher.id}
                                            >
                                                <span className="font-semibold text-[var(--ink)]">
                                                    {teacher.name}
                                                </span>
                                                {teacher.courseName ? (
                                                    <span className="text-[var(--ink-muted)]">
                                                        · {teacher.courseName}
                                                    </span>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="mt-6">
                                <h3 className="text-sm font-bold text-[var(--ink)]">
                                    {t('student.group.classmates')}
                                </h3>
                                {group.classmates.length === 0 ? (
                                    <p className="mt-2 text-sm text-[var(--ink-muted)]">
                                        {t('student.group.no_classmates')}
                                    </p>
                                ) : (
                                    <ul className="mt-2 flex flex-wrap gap-2">
                                        {group.classmates.map((classmate) => (
                                            <li
                                                className="rounded-full bg-[var(--surface-muted)] px-3 py-1 text-sm text-[var(--ink)]"
                                                key={classmate.id}
                                            >
                                                {classmate.name}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            {group.nextSession ? (
                                <section className="mt-6 rounded-lg bg-[var(--surface-muted)] p-4">
                                    <h3 className="text-sm font-bold text-[var(--ink)]">
                                        {t('student.group.next_session')}
                                    </h3>
                                    <p className="mt-1 text-sm text-[var(--ink)]">
                                        {group.nextSession.title}
                                    </p>
                                    <p className="mt-1 text-sm text-[var(--ink-muted)]">
                                        {formatDateTime(
                                            group.nextSession.startsAt,
                                            locale,
                                            group.nextSession.timezone,
                                        )}
                                    </p>
                                </section>
                            ) : null}
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
