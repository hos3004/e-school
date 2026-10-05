import { Head, router } from '@inertiajs/react';

import Card, {
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import StatusPill from '@/Components/StatusPill';
import type { StatusColorMap } from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { StudentPageHero } from '@/Pages/Student/Partials/StudentUi';
import { formatDate, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps } from '@/types';

interface StudentProgram {
    id: string;
    code: string;
    title: string;
    description?: string;
    status: string;
    levelName?: string | null;
    appliedAt?: string | null;
    activatedAt?: string | null;
    completedAt?: string | null;
    frozenAt?: string | null;
    frozenReason?: string | null;
    freezeType?: string | null;
    expectedReturnDate?: string | null;
}

interface Props extends LoadablePageProps {
    programs?: readonly StudentProgram[];
}

const enrollmentStatusColors: StatusColorMap<string> = {
    pending: 'warning',
    active: 'success',
    frozen: 'danger',
    paused: 'warning',
    completed: 'neutral',
    withdrawn: 'neutral',
};

export default function Programs({
    programs = [],
    loading = false,
    error = null,
}: Props) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <AppLayout role="student">
            <Head title={t('student.programs.title')} />
            <StudentPageHero
                action={
                    <div className="flex min-h-11 items-center gap-3 rounded-2xl border border-[color:var(--brand)]/20 bg-[var(--surface)]/80 px-4 py-2 shadow-sm">
                        <strong className="text-2xl font-bold tabular-nums text-[var(--ink)]">
                            {new Intl.NumberFormat(locale).format(
                                programs.length,
                            )}
                        </strong>
                        <span className="text-sm font-semibold text-[var(--ink-muted)]">
                            {t('student.programs.title')}
                        </span>
                    </div>
                }
                className="mb-8"
                title={t('student.programs.title')}
                subtitle={t('student.programs.subtitle')}
            />

            {loading ? (
                <LoadingState label={t('student.programs.loading')} rows={3} />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : programs.length === 0 ? (
                <EmptyState
                    title={t('student.programs.empty_title')}
                    description={t('student.programs.empty_description')}
                />
            ) : (
                <section
                    aria-label={t('student.programs.title')}
                    className="grid gap-4 lg:grid-cols-2"
                >
                    {programs.map((program) => (
                        <Card
                            as="article"
                            className="relative overflow-hidden border-[color:var(--ink-muted)]/15 shadow-sm transition-[border-color,box-shadow] duration-150 hover:border-[color:var(--brand)]/35 hover:shadow-md"
                            key={program.id}
                        >
                            <div
                                aria-hidden="true"
                                className="absolute inset-x-0 top-0 h-1 bg-[var(--brand)]"
                            />
                            <CardHeader className="mb-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-mono text-sm text-[var(--brand)]">
                                            {program.code}
                                        </p>
                                        <CardTitle className="mt-1">
                                            {program.title}
                                        </CardTitle>
                                    </div>
                                    <StatusPill
                                        colorMap={enrollmentStatusColors}
                                        status={program.status}
                                    />
                                </div>
                                {program.description ? (
                                    <CardDescription>
                                        {program.description}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>

                            <dl className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl bg-[var(--surface-muted)] px-4 py-3">
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('student.programs.level')}
                                    </dt>
                                    <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                        {program.levelName ||
                                            t('common.not_available')}
                                    </dd>
                                </div>
                                <div className="rounded-xl bg-[var(--surface-muted)] px-4 py-3">
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('student.programs.activated_at')}
                                    </dt>
                                    <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                        {program.activatedAt
                                            ? formatDate(
                                                  program.activatedAt,
                                                  locale,
                                              )
                                            : t('common.not_available')}
                                    </dd>
                                </div>
                                {program.completedAt ? (
                                    <div className="rounded-xl bg-[var(--surface-muted)] px-4 py-3">
                                        <dt className="text-xs text-[var(--ink-muted)]">
                                            {t('student.programs.completed_at')}
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                                            {formatDate(
                                                program.completedAt,
                                                locale,
                                            )}
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>

                            {program.frozenAt ? (
                                <div className="mt-5 rounded-2xl border border-[color:var(--danger)]/35 bg-[color-mix(in_srgb,var(--danger)_8%,var(--surface))] p-4 sm:p-5">
                                    <p className="text-sm font-bold text-[var(--ink)]">
                                        {t('student.programs.frozen_title')}
                                    </p>
                                    <p className="mt-1 text-sm text-[var(--ink)]">
                                        {program.frozenReason ||
                                            t(
                                                'student.programs.frozen_no_reason',
                                            )}
                                    </p>
                                    <p className="mt-2 text-xs text-[var(--ink-muted)]">
                                        {t('student.programs.frozen_at')}:{' '}
                                        {formatDate(program.frozenAt, locale)}
                                        {program.expectedReturnDate
                                            ? ` · ${t('student.programs.expected_return')}: ${formatDate(program.expectedReturnDate, locale)}`
                                            : ''}
                                    </p>
                                </div>
                            ) : null}
                        </Card>
                    ))}
                </section>
            )}
        </AppLayout>
    );
}
