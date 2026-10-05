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
import StatusPill from '@/Components/StatusPill';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatPercent, useLocale } from '@/lib/format';
import {
    StudentMetaItem,
    StudentPageHero,
} from '@/Pages/Student/Partials/StudentUi';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps, MonthlyReport, StatusColorMap } from '@/types';

interface StudentReportsProps extends LoadablePageProps {
    reports?: readonly MonthlyReport[];
}

const reportStatusColors: StatusColorMap = {
    draft: 'neutral',
    pending: 'warning',
    published: 'success',
    available: 'success',
    archived: 'neutral',
};

export default function Reports({
    reports = [],
    loading = false,
    error = null,
}: StudentReportsProps) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <AppLayout role="student">
            <Head title={t('student.reports.title')} />

            <StudentPageHero
                action={
                    <div className="flex min-h-11 items-center gap-3 rounded-2xl border border-[color:var(--brand)]/20 bg-[var(--surface)]/80 px-4 py-2 shadow-sm">
                        <strong className="text-2xl font-bold tabular-nums text-[var(--ink)]">
                            {new Intl.NumberFormat(locale).format(
                                reports.length,
                            )}
                        </strong>
                        <span className="text-sm font-semibold text-[var(--ink-muted)]">
                            {t('student.reports.report')}
                        </span>
                    </div>
                }
                className="mb-8"
                subtitle={t('student.reports.subtitle')}
                title={t('student.reports.title')}
            />

            {loading ? (
                <LoadingState label={t('student.reports.loading')} rows={4} />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : reports.length === 0 ? (
                <EmptyState
                    description={t('student.reports.empty_description')}
                    title={t('student.reports.empty_title')}
                />
            ) : (
                <section
                    aria-label={t('student.reports.table_caption')}
                    className="grid gap-4 lg:grid-cols-2"
                >
                    {reports.map((report) => {
                        const titleId = `student-report-${report.id}`;

                        return (
                            <Card
                                as="article"
                                className="overflow-hidden border-[color:var(--ink-muted)]/15 shadow-sm transition-[border-color,box-shadow] duration-150 hover:border-[color:var(--brand)]/35 hover:shadow-md"
                                key={report.id}
                                padding="none"
                            >
                                <div className="border-b border-[color:var(--ink-muted)]/10 bg-[linear-gradient(135deg,color-mix(in_srgb,var(--brand)_10%,var(--surface)),var(--surface))] px-5 py-5 sm:px-6">
                                    <CardHeader>
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-bold text-[var(--brand)]">
                                                    {report.month}
                                                </p>
                                                <CardTitle
                                                    as="h2"
                                                    className="mt-1"
                                                    id={titleId}
                                                >
                                                    {report.title}
                                                </CardTitle>
                                            </div>
                                            <StatusPill
                                                colorMap={reportStatusColors}
                                                status={report.status}
                                            />
                                        </div>
                                        {report.summary ? (
                                            <CardDescription className="mt-2 line-clamp-3 leading-6">
                                                {report.summary}
                                            </CardDescription>
                                        ) : null}
                                    </CardHeader>
                                </div>

                                <CardContent className="px-5 py-5 sm:px-6">
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <StudentMetaItem
                                            emphasize
                                            label={t(
                                                'student.reports.attendance_rate',
                                            )}
                                            value={
                                                report.attendanceRate ===
                                                    null ||
                                                report.attendanceRate ===
                                                    undefined
                                                    ? t('common.not_available')
                                                    : formatPercent(
                                                          report.attendanceRate,
                                                          locale,
                                                      )
                                            }
                                        />
                                        <StudentMetaItem
                                            label={t(
                                                'student.reports.issued_at',
                                            )}
                                            value={
                                                report.issuedAt ? (
                                                    <time
                                                        dateTime={
                                                            report.issuedAt
                                                        }
                                                    >
                                                        {formatDate(
                                                            report.issuedAt,
                                                            locale,
                                                        )}
                                                    </time>
                                                ) : (
                                                    t('common.not_available')
                                                )
                                            }
                                        />
                                    </dl>

                                    <div className="mt-5">
                                        {report.downloadUrl ? (
                                            <Button
                                                aria-label={`${t('student.reports.download')} ${report.title}`}
                                                as="link"
                                                fullWidth
                                                href={report.downloadUrl}
                                                rel="noopener noreferrer"
                                                target="_blank"
                                                variant="secondary"
                                            >
                                                {t('student.reports.download')}
                                            </Button>
                                        ) : (
                                            <p className="rounded-xl bg-[var(--surface-muted)] px-4 py-3 text-center text-sm text-[var(--ink-muted)]">
                                                {t(
                                                    'student.reports.not_available',
                                                )}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>
            )}
        </AppLayout>
    );
}
