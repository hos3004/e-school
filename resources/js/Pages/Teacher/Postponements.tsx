import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import type { FormEvent } from "react";

import Button from "@/Components/Button";
import Card, {
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/Card";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import StatusPill from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import { formatDateTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type {
  LoadablePageProps,
  PostponementRequest,
  StatusColorMap,
} from "@/types";

import { MetricTile, teacherFieldClasses } from "./Components/TeacherUi";

interface TeacherPostponementsProps extends LoadablePageProps {
  requests?: PostponementRequest[];
  statusColors?: StatusColorMap;
}

interface AlternativeFormData {
  proposed_start_at: string;
  reason: string;
}

interface RejectFormData {
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
  const [approveError, setApproveError] = useState<string | null>(null);
  const [alternativeOpen, setAlternativeOpen] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const alternativeForm = useForm<AlternativeFormData>({
    proposed_start_at: "",
    reason: "",
  });
  const alternativeFormId = "postponement-alternative-" + request.id;
  const rejectForm = useForm<RejectFormData>({
    reason: "",
  });
  const rejectFormId = "postponement-reject-" + request.id;


  const approve = () => {
    setApproveError(null);

    router.post(
      request.approveUrl,
      {},
      {
        preserveScroll: true,
        onStart: () => setApproving(true),
        onError: (errors) => {
          const serverMessage = Object.values(errors)[0];

          setApproveError(
            serverMessage ?? t("teacher.postponements.approve_error"),
          );
        },
        onFinish: () => setApproving(false),
      },
    );
  };

  const toggleAlternative = () => {
    setApproveError(null);
    alternativeForm.clearErrors();
    setRejectOpen(false);
    setAlternativeOpen((current) => !current);
  };

  const proposeAlternative = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    alternativeForm.post(request.proposeAlternativeUrl, {
      preserveScroll: true,
      onSuccess: () => {
        alternativeForm.reset();
        setAlternativeOpen(false);
      },
    });
  };


  const toggleReject = () => {
    setApproveError(null);
    rejectForm.clearErrors();
    setAlternativeOpen(false);
    setRejectOpen((current) => !current);
  };

  const reject = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    rejectForm.post(request.rejectUrl, {
      preserveScroll: true,
      onSuccess: () => {
        rejectForm.reset();
        setRejectOpen(false);
      },
    });
  };
  return (
    <Card
      as="article"
      className="overflow-hidden border-[var(--accent)]/30"
      padding="none"
    >
      <div aria-hidden="true" className="h-1 bg-[var(--accent)]" />
      <div className="p-5 sm:p-6">
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <CardTitle as="h2">{request.session.title}</CardTitle>
              <CardDescription>{request.requestedBy.name}</CardDescription>
            </div>
            <StatusPill colorMap={statusColors} status={request.status} />
          </div>
        </CardHeader>

        <CardContent className="mt-5">
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
              <dt className="font-semibold text-[var(--ink-muted)]">
                {t("teacher.postponements.current_time")}
              </dt>
              <dd className="mt-1 text-[var(--ink)]">
                <time dateTime={request.session.startsAt}>
                  {formatDateTime(
                    request.session.startsAt,
                    locale,
                    request.session.timezone,
                  )}
                </time>
              </dd>
            </div>
            <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
              <dt className="font-semibold text-[var(--ink-muted)]">
                {t("teacher.postponements.requested_time")}
              </dt>
              <dd className="mt-1 text-[var(--ink)]">
                <time dateTime={request.requestedStartAt}>
                  {formatDateTime(
                    request.requestedStartAt,
                    locale,
                    request.session.timezone,
                  )}
                </time>
              </dd>
            </div>
            <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5 sm:col-span-2">
              <dt className="font-semibold text-[var(--ink-muted)]">
                {t("teacher.postponements.request_reason")}
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
          {request.approveUrl ? (
            <Button
              className="w-full sm:w-auto"
              disabled={approving || alternativeForm.processing || rejectForm.processing}
              onClick={approve}
              type="button"
            >
              {approving
                ? t("actions.processing")
                : t("teacher.postponements.actions.approve")}
            </Button>
          ) : null}
          {request.proposeAlternativeUrl ? (
            <Button
              aria-controls={alternativeFormId}
              aria-expanded={alternativeOpen}
              className="w-full sm:w-auto"
              disabled={approving || alternativeForm.processing || rejectForm.processing}
              onClick={toggleAlternative}
              type="button"
              variant="secondary"
            >
              {alternativeOpen
                ? t("actions.cancel")
                : t("teacher.postponements.actions.propose_alternative")}
            </Button>
          ) : null}
          {request.rejectUrl ? (
            <Button
              aria-controls={rejectFormId}
              aria-expanded={rejectOpen}
              className="w-full sm:w-auto"
              disabled={approving || alternativeForm.processing || rejectForm.processing}
              onClick={toggleReject}
              type="button"
              variant="danger"
            >
              {rejectOpen
                ? t("actions.cancel")
                : t("teacher.postponements.actions.reject")}
            </Button>
          ) : null}
        </CardFooter>

        {alternativeOpen && request.proposeAlternativeUrl ? (
          <form
            className="mt-5 space-y-5 border-t border-[var(--ink-muted)]/25 pt-5"
            id={alternativeFormId}
            onSubmit={proposeAlternative}
          >
            <div>
              <label
                className="block text-sm font-semibold text-[var(--ink)]"
                htmlFor={alternativeFormId + "-datetime"}
              >
                {t("teacher.postponements.alternative.datetime_label")}
              </label>
              <input
                aria-describedby={
                  alternativeForm.errors.proposed_start_at
                    ? alternativeFormId + "-datetime-error"
                    : undefined
                }
                aria-invalid={Boolean(alternativeForm.errors.proposed_start_at)}
                className={`${teacherFieldClasses} sm:max-w-md`}
                disabled={alternativeForm.processing}
                id={alternativeFormId + "-datetime"}
                name="proposed_start_at"
                onChange={(event) =>
                  alternativeForm.setData(
                    "proposed_start_at",
                    event.target.value,
                  )
                }
                required
                type="datetime-local"
                value={alternativeForm.data.proposed_start_at}
              />
              <FieldError
                id={alternativeFormId + "-datetime-error"}
                message={alternativeForm.errors.proposed_start_at}
              />
            </div>

            <div>
              <label
                className="block text-sm font-semibold text-[var(--ink)]"
                htmlFor={alternativeFormId + "-reason"}
              >
                {t("teacher.postponements.alternative.reason_label")}
              </label>
              <p
                className="mt-1 text-sm leading-6 text-[var(--ink-muted)]"
                id={alternativeFormId + "-reason-help"}
              >
                {t("teacher.postponements.alternative.reason_help")}
              </p>
              <textarea
                aria-describedby={
                  alternativeForm.errors.reason
                    ? alternativeFormId +
                      "-reason-help " +
                      alternativeFormId +
                      "-reason-error"
                    : alternativeFormId + "-reason-help"
                }
                aria-invalid={Boolean(alternativeForm.errors.reason)}
                className={`${teacherFieldClasses} min-h-28 py-3`}
                disabled={alternativeForm.processing}
                id={alternativeFormId + "-reason"}
                name="reason"
                onChange={(event) =>
                  alternativeForm.setData("reason", event.target.value)
                }
                placeholder={t(
                  "teacher.postponements.alternative.reason_placeholder",
                )}
                required
                value={alternativeForm.data.reason}
              />
              <FieldError
                id={alternativeFormId + "-reason-error"}
                message={alternativeForm.errors.reason}
              />
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <Button disabled={alternativeForm.processing} type="submit">
                {alternativeForm.processing
                  ? t("actions.saving")
                  : t("teacher.postponements.alternative.submit")}
              </Button>
              <Button
                disabled={alternativeForm.processing}
                onClick={toggleAlternative}
                type="button"
                variant="ghost"
              >
                {t("actions.cancel")}
              </Button>
            </div>
          </form>
        ) : null}
        {rejectOpen && request.rejectUrl ? (
          <form
            className="mt-5 space-y-5 border-t border-[var(--ink-muted)]/25 pt-5"
            id={rejectFormId}
            onSubmit={reject}
          >
            <div>
              <label
                className="block text-sm font-semibold text-[var(--ink)]"
                htmlFor={rejectFormId + "-reason"}
              >
                {t("teacher.postponements.reject.reason_label")}
              </label>
              <textarea
                aria-invalid={Boolean(rejectForm.errors.reason)}
                className={teacherFieldClasses + " min-h-28 py-3"}
                disabled={rejectForm.processing}
                id={rejectFormId + "-reason"}
                name="reason"
                onChange={(event) =>
                  rejectForm.setData("reason", event.target.value)
                }
                placeholder={t("teacher.postponements.reject.reason_placeholder")}
                required
                value={rejectForm.data.reason}
              />
              <FieldError
                id={rejectFormId + "-reason-error"}
                message={rejectForm.errors.reason}
              />
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <Button disabled={rejectForm.processing} type="submit" variant="danger">
                {rejectForm.processing
                  ? t("actions.saving")
                  : t("teacher.postponements.reject.submit")}
              </Button>
              <Button
                disabled={rejectForm.processing}
                onClick={toggleReject}
                type="button"
                variant="ghost"
              >
                {t("actions.cancel")}
              </Button>
            </div>
          </form>
        ) : null}
      </div>
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
      only: ["requests", "statusColors", "error"],
    });
  };

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.postponements.title")} />

      <div className="space-y-[var(--space-section)]">
        <PageHeader
          subtitle={t("teacher.postponements.subtitle")}
          title={t("teacher.postponements.title")}
        />

        {!loading && !error ? (
          <section
            aria-label={t("teacher.postponements.list_label")}
            className="max-w-sm"
          >
            <MetricTile
              emphasis={requests.length > 0 ? "attention" : "default"}
              icon="clock"
              label={t("teacher.postponements.list_label")}
              value={requests.length}
            />
          </section>
        ) : null}

        {loading ? (
          <LoadingState label={t("teacher.postponements.loading")} rows={3} />
        ) : error !== null && error !== undefined ? (
          <ErrorState
            message={error || t("states.error.message")}
            onRetry={retry}
          />
        ) : requests.length === 0 ? (
          <EmptyState
            description={t("teacher.postponements.empty.description")}
            title={t("teacher.postponements.empty.title")}
          />
        ) : (
          <section
            aria-label={t("teacher.postponements.list_label")}
            className="grid items-start gap-5 xl:grid-cols-2"
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
