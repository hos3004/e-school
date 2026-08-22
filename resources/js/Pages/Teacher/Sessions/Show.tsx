import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

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
    Attendance,
    LoadablePageProps,
    Session,
    StatusColorMap,
} from '@/types';

interface SessionReport {
    summary?: string | null;
    notes?: string | null;
}

interface TeacherSessionShowProps extends LoadablePageProps {
    session: Session | null;
    attendance?: Attendance[];
    attendanceStatuses?: string[];
    statusColors?: StatusColorMap;
    attendanceUpdateUrl: string;
    reportSubmitUrl: string;
    initialReport?: SessionReport | null;
}

interface AttendanceFormData {
    statuses: Record<string, string>;
    reason: string;
}

interface ReportFormData {
    summary: string;
    notes: string;
}

interface FieldErrorProps {
    id: string;
    message?: string;
}

function FieldError({ id, message }: FieldErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <p
            className="mt-2 text-sm font-medium text-[var(--danger)]"
            id={id}
            role="alert"
        >
            {message}
        </p>
    );
}

function initialStatuses(
    attendance: readonly Attendance[],
): Record<string, string> {
    return attendance.reduce<Record<string, string>>((statuses, record) => {
        statuses[record.studentId] = record.status;

        return statuses;
    }, {});
}

export default function TeacherSessionShow({
    session,
    attendance = [],
    attendanceStatuses = [],
    statusColors = {},
    attendanceUpdateUrl,
    reportSubmitUrl,
    initialReport = null,
    loading = false,
    error = null,
}: TeacherSessionShowProps) {
    const t = useI18n();
    const locale = useLocale();
    const attendanceForm = useForm<AttendanceFormData>({
        statuses: initialStatuses(attendance),
        reason: '',
    });
    const reportForm = useForm<ReportFormData>({
        summary: initialReport?.summary ?? '',
        notes: initialReport?.notes ?? '',
    });
    const availableAttendanceStatuses = Array.from(
        new Set([
            ...attendanceStatuses,
            ...attendance.map((record) => record.status),
        ]),
    );

    const retry = () => {
        router.reload({
            only: [
                'session',
                'attendance',
                'attendanceStatuses',
                'statusColors',
                'initialReport',
                'error',
            ],
        });
    };

    const submitAttendance = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        attendanceForm.put(attendanceUpdateUrl, {
            preserveScroll: true,
        });
    };

    const submitReport = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        reportForm.post(reportSubmitUrl, {
            preserveScroll: true,
        });
    };

    const setAttendanceStatus = (
        studentId: string,
        status: string,
    ) => {
        attendanceForm.setData('statuses', {
            ...attendanceForm.data.statuses,
            [studentId]: status,
        });
    };

    const renderContent = () => {
        if (loading) {
            return (
                <LoadingState
                    label={t('teacher.sessions.show.loading')}
                    rows={4}
                />
            );
        }

        if (error !== null && error !== undefined) {
            return (
                <ErrorState
                    message={error || t('states.error.message')}
                    onRetry={retry}
                />
            );
        }

        if (session === null) {
            return (
                <EmptyState
                    description={t(
                        'teacher.sessions.show.empty.description',
                    )}
                    title={t('teacher.sessions.show.empty.title')}
                />
            );
        }

        return (
            <div className="space-y-6">
                <Card as="section" aria-labelledby="session-details-title">
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <CardTitle
                                    as="h2"
                                    id="session-details-title"
                                >
                                    {t(
                                        'teacher.sessions.show.details.title',
                                    )}
                                </CardTitle>
                                {session.subject ? (
                                    <CardDescription>
                                        {session.subject}
                                    </CardDescription>
                                ) : null}
                            </div>
                            <StatusPill
                                colorMap={statusColors}
                                status={session.status}
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="mt-5">
                        <dl className="grid gap-5 text-sm sm:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <dt className="font-semibold text-[var(--ink-muted)]">
                                    {t('sessions.name')}
                                </dt>
                                <dd className="mt-1 text-[var(--ink)]">
                                    {session.title}
                                </dd>
                            </div>
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
                                        {t(
                                            'common.time_range_separator',
                                        )}
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
                                <div>
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

                    {session.joinUrl ? (
                        <CardFooter className="mt-5">
                            <Button
                                as="link"
                                disabled={session.canJoin === false}
                                href={session.joinUrl}
                                rel="noopener noreferrer"
                                target="_blank"
                            >
                                {t('sessions.join')}
                            </Button>
                        </CardFooter>
                    ) : null}
                </Card>

                <Card
                    as="section"
                    aria-labelledby="attendance-roster-title"
                >
                    <CardHeader>
                        <CardTitle
                            as="h2"
                            id="attendance-roster-title"
                        >
                            {t(
                                'teacher.sessions.show.attendance.title',
                            )}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'teacher.sessions.show.attendance.description',
                            )}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="mt-5">
                        {attendance.length === 0 ? (
                            <EmptyState
                                description={t(
                                    'teacher.sessions.show.attendance.empty.description',
                                )}
                                title={t(
                                    'teacher.sessions.show.attendance.empty.title',
                                )}
                            />
                        ) : (
                            <form
                                className="space-y-6"
                                onSubmit={submitAttendance}
                            >
                                <div className="overflow-hidden rounded-xl border border-[var(--ink-muted)]/30">
                                    <ul
                                        aria-label={t(
                                            'teacher.sessions.show.attendance.roster_label',
                                        )}
                                        className="divide-y divide-[var(--ink-muted)]/20"
                                    >
                                        {attendance.map((record) => {
                                            const selectId =
                                                'attendance-status-' +
                                                record.id;

                                            return (
                                                <li
                                                    className="grid gap-3 bg-[var(--surface)] p-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)] sm:items-center"
                                                    key={record.id}
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-semibold text-[var(--ink)]">
                                                            {
                                                                record.studentName
                                                            }
                                                        </p>
                                                        <div className="mt-2">
                                                            <StatusPill
                                                                colorMap={
                                                                    statusColors
                                                                }
                                                                status={
                                                                    record.status
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label
                                                            className="mb-1.5 block text-sm font-semibold text-[var(--ink)]"
                                                            htmlFor={
                                                                selectId
                                                            }
                                                        >
                                                            {t(
                                                                'teacher.sessions.show.attendance.status_label',
                                                            )}
                                                        </label>
                                                        <select
                                                            aria-describedby={
                                                                attendanceForm
                                                                    .errors
                                                                    .statuses
                                                                    ? 'attendance-statuses-error'
                                                                    : undefined
                                                            }
                                                            aria-invalid={
                                                                Boolean(
                                                                    attendanceForm
                                                                        .errors
                                                                        .statuses,
                                                                )
                                                            }
                                                            className="min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                                                            disabled={
                                                                attendanceForm.processing
                                                            }
                                                            id={selectId}
                                                            name={
                                                                'statuses[' +
                                                                record.studentId +
                                                                ']'
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                setAttendanceStatus(
                                                                    record.studentId,
                                                                    event
                                                                        .target
                                                                        .value,
                                                                )
                                                            }
                                                            value={
                                                                attendanceForm
                                                                    .data
                                                                    .statuses[
                                                                    record
                                                                        .studentId
                                                                ] ??
                                                                record.status
                                                            }
                                                        >
                                                            {availableAttendanceStatuses.map(
                                                                (
                                                                    status,
                                                                ) => (
                                                                    <option
                                                                        key={
                                                                            status
                                                                        }
                                                                        value={
                                                                            status
                                                                        }
                                                                    >
                                                                        {t(
                                                                            'attendance.statuses.' +
                                                                                status,
                                                                        )}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>

                                <FieldError
                                    id="attendance-statuses-error"
                                    message={
                                        attendanceForm.errors.statuses
                                    }
                                />

                                <div>
                                    <label
                                        className="block text-sm font-semibold text-[var(--ink)]"
                                        htmlFor="attendance-change-reason"
                                    >
                                        {t(
                                            'teacher.sessions.show.attendance.reason_label',
                                        )}
                                    </label>
                                    <p
                                        className="mt-1 text-sm leading-6 text-[var(--ink-muted)]"
                                        id="attendance-change-reason-help"
                                    >
                                        {t(
                                            'teacher.sessions.show.attendance.reason_help',
                                        )}
                                    </p>
                                    <textarea
                                        aria-describedby={
                                            attendanceForm.errors.reason
                                                ? 'attendance-change-reason-help attendance-change-reason-error'
                                                : 'attendance-change-reason-help'
                                        }
                                        aria-invalid={Boolean(
                                            attendanceForm.errors.reason,
                                        )}
                                        className="mt-2 min-h-28 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 py-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                                        disabled={
                                            attendanceForm.processing
                                        }
                                        id="attendance-change-reason"
                                        name="reason"
                                        onChange={(event) =>
                                            attendanceForm.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'teacher.sessions.show.attendance.reason_placeholder',
                                        )}
                                        required
                                        value={
                                            attendanceForm.data.reason
                                        }
                                    />
                                    <FieldError
                                        id="attendance-change-reason-error"
                                        message={
                                            attendanceForm.errors.reason
                                        }
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        disabled={
                                            attendanceForm.processing
                                        }
                                        type="submit"
                                    >
                                        {attendanceForm.processing
                                            ? t('actions.saving')
                                            : t(
                                                  'teacher.sessions.show.attendance.save',
                                              )}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card as="section" aria-labelledby="session-report-title">
                    <CardHeader>
                        <CardTitle as="h2" id="session-report-title">
                            {t(
                                'teacher.sessions.show.report.title',
                            )}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'teacher.sessions.show.report.description',
                            )}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="mt-5">
                        <form
                            className="space-y-5"
                            onSubmit={submitReport}
                        >
                            <div>
                                <label
                                    className="block text-sm font-semibold text-[var(--ink)]"
                                    htmlFor="session-report-summary"
                                >
                                    {t(
                                        'teacher.sessions.show.report.summary_label',
                                    )}
                                </label>
                                <textarea
                                    aria-describedby={
                                        reportForm.errors.summary
                                            ? 'session-report-summary-error'
                                            : undefined
                                    }
                                    aria-invalid={Boolean(
                                        reportForm.errors.summary,
                                    )}
                                    className="mt-2 min-h-32 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 py-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                                    disabled={reportForm.processing}
                                    id="session-report-summary"
                                    name="summary"
                                    onChange={(event) =>
                                        reportForm.setData(
                                            'summary',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'teacher.sessions.show.report.summary_placeholder',
                                    )}
                                    required
                                    value={reportForm.data.summary}
                                />
                                <FieldError
                                    id="session-report-summary-error"
                                    message={reportForm.errors.summary}
                                />
                            </div>

                            <div>
                                <label
                                    className="block text-sm font-semibold text-[var(--ink)]"
                                    htmlFor="session-report-notes"
                                >
                                    {t(
                                        'teacher.sessions.show.report.notes_label',
                                    )}
                                </label>
                                <textarea
                                    aria-describedby={
                                        reportForm.errors.notes
                                            ? 'session-report-notes-error'
                                            : undefined
                                    }
                                    aria-invalid={Boolean(
                                        reportForm.errors.notes,
                                    )}
                                    className="mt-2 min-h-28 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 py-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                                    disabled={reportForm.processing}
                                    id="session-report-notes"
                                    name="notes"
                                    onChange={(event) =>
                                        reportForm.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'teacher.sessions.show.report.notes_placeholder',
                                    )}
                                    value={reportForm.data.notes}
                                />
                                <FieldError
                                    id="session-report-notes-error"
                                    message={reportForm.errors.notes}
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    disabled={reportForm.processing}
                                    type="submit"
                                >
                                    {reportForm.processing
                                        ? t('actions.saving')
                                        : t(
                                              'teacher.sessions.show.report.submit',
                                          )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        );
    };

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.sessions.show.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={t('teacher.sessions.show.subtitle')}
                    title={
                        session?.title ??
                        t('teacher.sessions.show.title')
                    }
                />

                {renderContent()}
            </div>
        </AppLayout>
    );
}
