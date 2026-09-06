import { Head, router, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";

import Button from "@/Components/Button";
import Card, {
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/Card";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import StatusPill from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import { StudentPageHero } from "@/Pages/Student/Partials/StudentUi";
import {
  formatDate,
  formatDateTime,
  formatTime,
  useLocale,
} from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps, Session, StatusColorMap } from "@/types";

interface StudentSessionShowProps extends LoadablePageProps {
  session?: Session | null;
  postponementRequestUrl?: string;
  postponementRequest?: PostponementSummary | null;
  canRequestPostponement?: boolean;
  studentApologyUrl?: string;
  studentApology?: StudentApologySummary | null;
  canSubmitApology?: boolean;
}

interface PostponementSummary {
  id: string;
  status: string;
  reason: string;
  proposedStart: string;
  teacherProposedStart: string | null;
  acceptAlternativeUrl: string;
}

interface StudentApologySummary {
  submittedAt: string;
  reason: string;
}

const sessionStatusColors: StatusColorMap = {
  scheduled: "brand",
  live: "success",
  completed: "neutral",
  postponed: "warning",
  cancelled: "danger",
};

function statusAllowsJoining(status: string): boolean {
  return ["scheduled", "confirmed", "in_progress"].includes(status);
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
  postponementRequestUrl = "",
  postponementRequest = null,
  canRequestPostponement = false,
  loading = false,
  studentApologyUrl = "",
  studentApology = null,
  canSubmitApology = false,
  error = null,
}: StudentSessionShowProps) {
  const t = useI18n();
  const locale = useLocale();
  const now = useJoinClock(session);
  const pageTitle = session?.title ?? t("student.sessions.details_title");
  const postponementForm = useForm({ proposed_start: "", reason: "" });
  const apologyForm = useForm({ reason: "" });
  const [acceptingAlternative, setAcceptingAlternative] = useState(false);
  const submitPostponement = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    postponementForm.post(postponementRequestUrl, { preserveScroll: true });
  };

  const submitApology = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    apologyForm.post(studentApologyUrl, {
      preserveScroll: true,
    });
  };

  const acceptAlternative = () => {
    if (!postponementRequest?.acceptAlternativeUrl) {
      return;
    }

    router.post(postponementRequest.acceptAlternativeUrl, {}, {
      preserveScroll: true,
      onStart: () => setAcceptingAlternative(true),
      onFinish: () => setAcceptingAlternative(false),
    });
  };
  const content = (() => {
    if (loading) {
      return (
        <LoadingState label={t("student.sessions.loading_details")} rows={4} />
      );
    }

    if (error !== null && error !== undefined) {
      return (
        <ErrorState
          message={error || t("states.error.message")}
          onRetry={() => router.reload()}
        />
      );
    }

    if (session === null) {
      return (
        <EmptyState
          action={
            <Button as="link" href="/student/schedule" variant="secondary">
              {t("student.sessions.back_to_schedule")}
            </Button>
          }
          description={t("student.sessions.not_found_description")}
          title={t("student.sessions.not_found")}
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
                <CardTitle as="h2">{t("student.sessions.details")}</CardTitle>
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
                  {t("common.date")}
                </dt>
                <dd className="mt-1 text-[var(--ink)]">
                  <time dateTime={session.startsAt}>
                    {formatDate(session.startsAt, locale, session.timezone)}
                  </time>
                </dd>
              </div>

              <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
                  {t("common.time")}
                </dt>
                <dd className="mt-1 text-[var(--ink)]">
                  <time dateTime={session.startsAt}>
                    {formatTime(session.startsAt, locale, session.timezone)}
                  </time>
                  <span className="sr-only">{t("common.to")}</span>
                  <span
                    aria-hidden="true"
                    className="ps-2 pe-2 text-[var(--ink-muted)]"
                  >
                    {t("common.time_separator")}
                  </span>
                  <time dateTime={session.endsAt}>
                    {formatTime(session.endsAt, locale, session.timezone)}
                  </time>
                </dd>
              </div>

              <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
                  {t("student.sessions.teacher")}
                </dt>
                <dd className="mt-1 text-[var(--ink)]">
                  {session.teacher?.name ?? t("common.not_available")}
                </dd>
              </div>

              <div className="rounded-2xl bg-[var(--surface)]/80 p-4 shadow-sm">
                <dt className="text-sm font-semibold text-[var(--ink-muted)]">
                  {t("student.sessions.location")}
                </dt>
                <dd className="mt-1 text-[var(--ink)]">
                  {session.location ?? t("common.online")}
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
                ? "border-[color:var(--success)]/35 bg-[color:var(--success)]/8 shadow-md"
                : "border-[color:var(--ink-muted)]/15"
            }
          >
            <CardHeader>
              <CardTitle as="h2">
                {t("student.sessions.join_heading")}
              </CardTitle>
              <CardDescription>
                {canJoin
                  ? t("student.sessions.join_ready_description")
                  : t("student.sessions.join_locked_description")}
              </CardDescription>
            </CardHeader>
            <CardContent className="mt-5">
              <Button
                as="link"
                disabled={!canJoin}
                className="min-h-12"
                fullWidth
                href={session.joinUrl ?? "#"}
                rel="noopener noreferrer"
                target="_blank"
              >
                {t("student.sessions.join")}
              </Button>

              {!canJoin && session.canJoinAt ? (
                <p
                  className="mt-3 text-sm leading-6 text-[var(--ink-muted)]"
                  role="status"
                >
                  <span className="font-semibold text-[var(--ink)]">
                    {t("student.sessions.join_available_at")}
                  </span>{" "}
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
            <Card as="section" className="border-[color:var(--brand)]/20">
              <CardHeader>
                <CardTitle as="h2">
                  {t("student.sessions.recording_heading")}
                </CardTitle>
                <CardDescription>
                  {t("student.sessions.recording_description")}
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
                  {t("student.sessions.watch_recording")}
                </Button>
              </CardContent>
            </Card>
          ) : null}

          {studentApology ? (
            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">{t("student.apology.title")}</CardTitle>
                <CardDescription>
                  {t("student.apology.recorded")}
                </CardDescription>
              </CardHeader>
              <CardContent className="mt-4 text-sm text-[var(--ink-muted)]">
                {studentApology.reason}
              </CardContent>
            </Card>
          ) : canSubmitApology && studentApologyUrl ? (
            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">{t("student.apology.title")}</CardTitle>
                <CardDescription>
                  {t("student.apology.description")}
                </CardDescription>
              </CardHeader>
              <CardContent className="mt-5">
                <form className="space-y-4" onSubmit={submitApology}>
                  <label className="block text-sm font-semibold text-[var(--ink)]">
                    {t("student.apology.reason")}
                    <textarea
                      className="mt-2 min-h-24 w-full rounded-lg border border-[var(--line)] bg-[var(--surface)] p-3"
                      disabled={apologyForm.processing}
                      onChange={(event) =>
                        apologyForm.setData("reason", event.target.value)
                      }
                      required
                      value={apologyForm.data.reason}
                    />
                  </label>
                  <Button
                    disabled={apologyForm.processing}
                    fullWidth
                    type="submit"
                    variant="secondary"
                  >
                    {apologyForm.processing
                      ? t("actions.processing")
                      : t("student.apology.submit")}
                  </Button>
                </form>
              </CardContent>
            </Card>
          ) : null}

          {postponementRequest ? (
            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">{t("postponement.request_title")}</CardTitle>
                <CardDescription>
                  {t("common.status")}:{" "}
                  {t("statuses." + postponementRequest.status)}
                </CardDescription>
              </CardHeader>
              <CardContent className="mt-4 text-sm text-[var(--ink-muted)]">
                {postponementRequest.reason}
                {postponementRequest.teacherProposedStart ? (
                  <div className="mt-4 rounded-lg bg-[var(--surface-subtle)] p-4">
                    <p className="font-semibold text-[var(--ink)]">
                      {t("postponement.teacher_alternative")}
                    </p>
                    <time dateTime={postponementRequest.teacherProposedStart}>
                      {formatDateTime(
                        postponementRequest.teacherProposedStart,
                        locale,
                        session.timezone,
                      )}
                    </time>
                  </div>
                ) : null}
                {postponementRequest.acceptAlternativeUrl ? (
                  <Button
                    className="mt-4"
                    disabled={acceptingAlternative}
                    onClick={acceptAlternative}
                    type="button"
                  >
                    {acceptingAlternative
                      ? t("actions.processing")
                      : t("postponement.accept_alternative")}
                  </Button>
                ) : null}
              </CardContent>
            </Card>
          ) : canRequestPostponement && postponementRequestUrl ? (
            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">{t("postponement.request_title")}</CardTitle>
                <CardDescription>
                  {t("postponement.request_description")}
                </CardDescription>
              </CardHeader>
              <CardContent className="mt-5">
                <form className="space-y-4" onSubmit={submitPostponement}>
                  <label className="block text-sm font-semibold text-[var(--ink)]">
                    {t("postponement.proposed_start")}
                    <input
                      className="mt-2 min-h-11 w-full rounded-lg border border-[var(--line)] bg-[var(--surface)] px-3"
                      disabled={postponementForm.processing}
                      onChange={(event) =>
                        postponementForm.setData(
                          "proposed_start",
                          event.target.value,
                        )
                      }
                      required
                      type="datetime-local"
                      value={postponementForm.data.proposed_start}
                    />
                  </label>
                  <label className="block text-sm font-semibold text-[var(--ink)]">
                    {t("postponement.reason")}
                    <textarea
                      className="mt-2 min-h-24 w-full rounded-lg border border-[var(--line)] bg-[var(--surface)] p-3"
                      disabled={postponementForm.processing}
                      onChange={(event) =>
                        postponementForm.setData("reason", event.target.value)
                      }
                      required
                      value={postponementForm.data.reason}
                    />
                  </label>
                  <Button
                    disabled={postponementForm.processing}
                    fullWidth
                    type="submit"
                  >
                    {postponementForm.processing
                      ? t("actions.processing")
                      : t("postponement.submit")}
                  </Button>
                </form>
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
          <Button as="link" href="/student/schedule" variant="secondary">
            {t("student.sessions.back_to_schedule")}
          </Button>
        }
        className="mb-8"
        subtitle={t("student.sessions.details_subtitle")}
        title={pageTitle}
      />

      {content}
    </AppLayout>
  );
}
