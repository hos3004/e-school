import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type {
    LoadablePageProps,
    PostponementRequest,
    StatusColorMap,
} from '@/types';

interface TeacherPostponementsProps extends LoadablePageProps {
    requests?: PostponementRequest[];
    statusColors?: StatusColorMap;
}

interface AlternativeFormData {
    proposed_start_at: string;
    reason: string;
}

interface RequestCardProps {
    request: PostponementRequest;
    statusColors: StatusColorMap;
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

function RequestCard({ request, statusColors }: RequestCardProps) {
    const t = useI18n();
    const locale = useLocale();
    const [approving, setApproving] = useState(false);
    const [approveError, setApproveError] = useState<string | null>(
        null,
    );
    const [alternativeOpen, setAlternativeOpen] = useState(false);
    const alternativeForm = useForm<AlternativeFormData>({
        proposed_start_at: '',
        reason: '',
    });
    const alternativeFormId =
        'postponement-alternative-' + request.id;

    const approve = () => {
        setApproveError(null);

        router.post(request.approveUrl, {}, {
            preserveScroll: true,
            onStart: () => setApproving(true),
            onError: (errors) => {
                const serverMessage = Object.values(errors)[0];

                setApproveError(
                    serverMessage ??
                        t(
                            'teacher.postponements.approve_error',
                        ),
                );
            },
            onFinish: () => setApproving(false),
        });
    };

    const toggleAlternative = () => {
        setApproveError(null);
        alternativeForm.clearErrors();
        setAlternativeOpen((current) => !current);
    };

    const proposeAlternative = (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        alternativeForm.post(request.proposeAlternativeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                alternativeForm.reset();
                setAlternativeOpen(false);
            },
        });
    };

    return (
        <Card as="article" padding="md">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <CardTitle as="h2">{request.session.title}</CardTitle>
                        <CardDescription>
                            {request.requestedBy.name}
                        </CardDescription>
                    </div>
                    <StatusPill
                        colorMap={statusColors}
                        status={request.status}
                    />
                </div>
            </CardHeader>

            <CardContent className="mt-5">
                <dl className="grid gap-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="font-semibold text-[var(--ink-muted)]">
                            {t(
                                'teacher.postponements.current_time',
                            )}
                        </dt>
                        <dd className="mt-1 text-[var(--ink)]">
                            <time
                                dateTime={
                                    request.session.startsAt
                                }
                            >
                                {formatDateTime(
                                    request.session.startsAt,
                                    locale,
                                    request.session.timezone,
                                )}
                            </time>
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold text-[var(--ink-muted)]">
                            {t(
                                'teacher.postponements.requested_time',
                            )}
                        </dt>
                        <dd className="mt-1 text-[var(--ink)]">
                            <time
                                dateTime={
                                    request.requestedStartAt
                                }
                            >
                                {formatDateTime(
                                    request.requestedStartAt,
                                    locale,
                                    request.session.timezone,
                                )}
                            </time>
                        </dd>
                    </div>
                    <div className="sm:col-span-2">
                        <dt className="font-semibold text-[var(--ink-muted)]">
                            {t(
                                'teacher.postponements.request_reason',
                            )}
                        </dt>
                        <dd className="mt-1 whitespace-pre-wrap break-words leading-6 text-[var(--ink)]">
                            {request.reason}
                        </dd>
                    </div>
                </dl>

                {approveError ? (
                    <p
                        className="mt-5 rounded-lg border border-[var(--danger)]/40 bg-[var(--surface)] ps-3 pe-3 py-3 text-sm font-medium text-[var(--danger)]"
                        role="alert"
                    >
                        {approveError}
                    </p>
                ) : null}
            </CardContent>

            <CardFooter className="mt-5 justify-start sm:justify-end">
                <Button
                    className="w-full sm:w-auto"
                    disabled={
                        approving || alternativeForm.processing
                    }
                    onClick={approve}
                    type="button"
                >
                    {approving
                        ? t('actions.processing')
                        : t(
                              'teacher.postponements.actions.approve',
                          )}
                </Button>
                <Button
                    aria-controls={alternativeFormId}
                    aria-expanded={alternativeOpen}
                    className="w-full sm:w-auto"
                    disabled={
                        approving || alternativeForm.processing
                    }
                    onClick={toggleAlternative}
                    type="button"
                    variant="secondary"
                >
                    {alternativeOpen
                        ? t('actions.cancel')
                        : t(
                              'teacher.postponements.actions.propose_alternative',
                          )}
                </Button>
            </CardFooter>

            {alternativeOpen ? (
                <form
                    className="mt-5 space-y-5 border-t border-[var(--ink-muted)]/25 pt-5"
                    id={alternativeFormId}
                    onSubmit={proposeAlternative}
                >
                    <div>
                        <label
                            className="block text-sm font-semibold text-[var(--ink)]"
                            htmlFor={
                                alternativeFormId + '-datetime'
                            }
                        >
                            {t(
                                'teacher.postponements.alternative.datetime_label',
                            )}
                        </label>
                        <input
                            aria-describedby={
                                alternativeForm.errors
                                    .proposed_start_at
                                    ? alternativeFormId +
                                      '-datetime-error'
                                    : undefined
                            }
                            aria-invalid={Boolean(
                                alternativeForm.errors
                                    .proposed_start_at,
                            )}
                            className="mt-2 min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)] sm:max-w-md"
                            disabled={alternativeForm.processing}
                            id={alternativeFormId + '-datetime'}
                            name="proposed_start_at"
                            onChange={(event) =>
                                alternativeForm.setData(
                                    'proposed_start_at',
                                    event.target.value,
                                )
                            }
                            required
                            type="datetime-local"
                            value={
                                alternativeForm.data
                                    .proposed_start_at
                            }
                        />
                        <FieldError
                            id={
                                alternativeFormId +
                                '-datetime-error'
                            }
                            message={
                                alternativeForm.errors
                                    .proposed_start_at
                            }
                        />
                    </div>

                    <div>
                        <label
                            className="block text-sm font-semibold text-[var(--ink)]"
                            htmlFor={
                                alternativeFormId + '-reason'
                            }
                        >
                            {t(
                                'teacher.postponements.alternative.reason_label',
                            )}
                        </label>
                        <p
                            className="mt-1 text-sm leading-6 text-[var(--ink-muted)]"
                            id={
                                alternativeFormId +
                                '-reason-help'
                            }
                        >
                            {t(
                                'teacher.postponements.alternative.reason_help',
                            )}
                        </p>
                        <textarea
                            aria-describedby={
                                alternativeForm.errors.reason
                                    ? alternativeFormId +
                                      '-reason-help ' +
                                      alternativeFormId +
                                      '-reason-error'
                                    : alternativeFormId +
                                      '-reason-help'
                            }
                            aria-invalid={Boolean(
                                alternativeForm.errors.reason,
                            )}
                            className="mt-2 min-h-28 w-full rounded-lg border border-[var(--ink-muted)]/45 bg-[var(--surface)] ps-3 pe-3 py-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                            disabled={alternativeForm.processing}
                            id={alternativeFormId + '-reason'}
                            name="reason"
                            onChange={(event) =>
                                alternativeForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            placeholder={t(
                                'teacher.postponements.alternative.reason_placeholder',
                            )}
                            required
                            value={alternativeForm.data.reason}
                        />
                        <FieldError
                            id={
                                alternativeFormId +
                                '-reason-error'
                            }
                            message={
                                alternativeForm.errors.reason
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <Button
                            disabled={alternativeForm.processing}
                            type="submit"
                        >
                            {alternativeForm.processing
                                ? t('actions.saving')
                                : t(
                                      'teacher.postponements.alternative.submit',
                                  )}
                        </Button>
                        <Button
                            disabled={alternativeForm.processing}
                            onClick={toggleAlternative}
                            type="button"
                            variant="ghost"
                        >
                            {t('actions.cancel')}
                        </Button>
                    </div>
                </form>
            ) : null}
        </Card>
    );
}

export default function TeacherPostponements({
    requests = [],
    statusColors = {},
    loading = false,
    error = null,
}: TeacherPostponementsProps) {
    const t = useI18n();
    const retry = () => {
        router.reload({
            only: ['requests', 'statusColors', 'error'],
        });
    };

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.postponements.title')} />

            <div className="space-y-6">
                <PageHeader
                    subtitle={t('teacher.postponements.subtitle')}
                    title={t('teacher.postponements.title')}
                />

                {loading ? (
                    <LoadingState
                        label={t('teacher.postponements.loading')}
                        rows={3}
                    />
                ) : error !== null && error !== undefined ? (
                    <ErrorState
                        message={error || t('states.error.message')}
                        onRetry={retry}
                    />
                ) : requests.length === 0 ? (
                    <EmptyState
                        description={t(
                            'teacher.postponements.empty.description',
                        )}
                        title={t(
                            'teacher.postponements.empty.title',
                        )}
                    />
                ) : (
                    <section
                        aria-label={t(
                            'teacher.postponements.list_label',
                        )}
                        className="grid gap-5 xl:grid-cols-2"
                    >
                        {requests.map((request) => (
                            <RequestCard
                                key={request.id}
                                request={request}
                                statusColors={statusColors}
                            />
                        ))}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
