import { Head, router, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
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
import { formatDate, formatTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type {
  Attendance,
  LoadablePageProps,
  Session,
  StatusColorMap,
} from "@/types";

import { MetricTile, teacherFieldClasses } from "../Components/TeacherUi";

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
  postponementRequestUrl?: string;
  postponementRequest?: PostponementSummary | null;
  canRequestPostponement?: boolean;
  canSubmitReport?: boolean;
}

interface PostponementSummary {
  id: string;
  status: string;
  reason: string;
  proposedStart: string;
}

interface AttendanceFormData {
  statuses: Record<string, string>;
  reason: string;
}

interface ReportFormData {
  summary: string;
  notes: string;
  students: Array<{
    student_profile_id: string;
    participation: number;
    performance: number;
    commitment: number;
  }>;
}

interface PostponementFormData {
  proposed_start: string;
  reason: string;
}

interface FieldErrorProps {
  id: string;
  message?: string;
}

function joinIsAvailable(session: Session, now: number): boolean {
  if (
    !session.joinUrl ||
    !["scheduled", "confirmed", "in_progress"].includes(session.status)
  ) {
    return false;
  }

  if (session.canJoin === true) {
    return true;
  }

  const opensAt = session.canJoinAt
    ? Date.parse(session.canJoinAt)
    : Number.POSITIVE_INFINITY;
  const closesAt = session.canJoinUntil
    ? Date.parse(session.canJoinUntil)
    : Number.POSITIVE_INFINITY;

  return (
    Number.isFinite(opensAt) &&
    now >= opensAt &&
    (!Number.isFinite(closesAt) || now <= closesAt)
  );
}

function useJoinClock(session: Session | null): number {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!session?.canJoinAt || session.canJoin === true) {
      return;
    }

    const opensAt = Date.parse(session.canJoinAt);

    if (!Number.isFinite(opensAt) || opensAt <= now) {
      return;
    }

    const timer = window.setTimeout(
      () => setNow(Date.now()),
      Math.min(Math.max(opensAt - Date.now(), 0) + 50, 2_147_000_000),
    );

    return () => window.clearTimeout(timer);
  }, [now, session]);

  return now;
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
  postponementRequestUrl = "",
  postponementRequest = null,
  canRequestPostponement = false,
  canSubmitReport = false,
  loading = false,
  error = null,
}: TeacherSessionShowProps) {
  const t = useI18n();
  const locale = useLocale();
  const joinNow = useJoinClock(session);
  const attendanceForm = useForm<AttendanceFormData>({
    statuses: initialStatuses(attendance),
    reason: "",
  });
  const reportForm = useForm<ReportFormData>({
    summary: initialReport?.summary ?? "",
    notes: initialReport?.notes ?? "",
    students: attendance.map((record) => ({
      student_profile_id: record.studentId,
      participation: 3,
      performance: 3,
      commitment: 3,
    })),
  });
  const postponementForm = useForm<PostponementFormData>({
    proposed_start: "",
    reason: "",
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
        "session",
        "attendance",
        "attendanceStatuses",
        "statusColors",
        "initialReport",
        "error",
      ],
    });
  };

  const submitAttendance = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    attendanceForm.post(attendanceUpdateUrl, {
      preserveScroll: true,
    });
  };

  const submitReport = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    reportForm.post(reportSubmitUrl, {
      preserveScroll: true,
    });
  };

  const submitPostponement = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    postponementForm.post(postponementRequestUrl, {
      preserveScroll: true,
      onSuccess: () => postponementForm.reset(),
    });
  };

  const setReportScore = (
    index: number,
    field: "participation" | "performance" | "commitment",
    value: number,
  ) => {
    const students = [...reportForm.data.students];
    const current = students[index];
    if (!current) {
      return;
    }
    students[index] = { ...current, [field]: value };
    reportForm.setData("students", students);
  };

  const setAttendanceStatus = (studentId: string, status: string) => {
    attendanceForm.setData("statuses", {
      ...attendanceForm.data.statuses,
      [studentId]: status,
    });
  };

  const renderContent = () => {
    if (loading) {
      return (
        <LoadingState label={t("teacher.sessions.show.loading")} rows={4} />
      );
    }

    if (error !== null && error !== undefined) {
      return (
        <ErrorState
          message={error || t("states.error.message")}
          onRetry={retry}
        />
      );
    }

    if (session === null) {
      return (
        <EmptyState
          description={t("teacher.sessions.show.empty.description")}
          title={t("teacher.sessions.show.empty.title")}
        />
      );
    }

    return (
      <div className="space-y-[var(--space-section)]">
        <section
          aria-label={t("teacher.sessions.show.details.title")}
          className="grid gap-3 sm:grid-cols-3"
        >
          <MetricTile
            icon="calendar"
            label={t("common.date")}
            value={formatDate(session.startsAt, locale, session.timezone)}
            emphasis="brand"
          />
          <MetricTile
            icon="clock"
            label={t("common.time")}
            value={
              <span dir="ltr">
                {formatTime(session.startsAt, locale, session.timezone)}
              </span>
            }
          />
          <MetricTile
            icon="group"
            label={t("teacher.sessions.show.attendance.title")}
            value={attendance.length}
          />
        </section>

        <Card
          as="section"
          aria-labelledby="session-details-title"
          className="overflow-hidden border-[var(--brand)]/35"
          padding="none"
        >
          <div aria-hidden="true" className="h-1 bg-[var(--brand)]" />
          <div className="p-5 sm:p-6">
            <CardHeader>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <CardTitle as="h2" id="session-details-title">
                    {t("teacher.sessions.show.details.title")}
                  </CardTitle>
                  {session.subject ? (
                    <CardDescription>{session.subject}</CardDescription>
                  ) : null}
                </div>
                <StatusPill colorMap={statusColors} status={session.status} />
              </div>
            </CardHeader>

            <CardContent className="mt-5">
              <dl className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
                  <dt className="font-semibold text-[var(--ink-muted)]">
                    {t("sessions.name")}
                  </dt>
                  <dd className="mt-1 text-[var(--ink)]">{session.title}</dd>
                </div>
                <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
                  <dt className="font-semibold text-[var(--ink-muted)]">
                    {t("common.date")}
                  </dt>
                  <dd className="mt-1 text-[var(--ink)]">
                    <time dateTime={session.startsAt}>
                      {formatDate(session.startsAt, locale, session.timezone)}
                    </time>
                  </dd>
                </div>
                <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
                  <dt className="font-semibold text-[var(--ink-muted)]">
                    {t("common.time")}
                  </dt>
                  <dd className="mt-1 text-[var(--ink)]">
                    <time dateTime={session.startsAt}>
                      {formatTime(session.startsAt, locale, session.timezone)}
                    </time>
                    <span aria-hidden="true">
                      {t("common.time_range_separator")}
                    </span>
                    <span className="sr-only">{t("common.until")}</span>
                    <time dateTime={session.endsAt}>
                      {formatTime(session.endsAt, locale, session.timezone)}
                    </time>
                  </dd>
                </div>
                {session.location ? (
                  <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3.5">
                    <dt className="font-semibold text-[var(--ink-muted)]">
                      {t("sessions.location")}
                    </dt>
                    <dd className="mt-1 break-words text-[var(--ink)]">
                      {session.location}
                    </dd>
                  </div>
                ) : null}
              </dl>
            </CardContent>

            {session.joinUrl || session.recordingUrl ? (
              <CardFooter className="mt-5 flex flex-wrap gap-3">
                {session.joinUrl ? (
                  <Button
                    as="link"
                    disabled={!joinIsAvailable(session, joinNow)}
                    href={session.joinUrl}
                    rel="noopener noreferrer"
                    target="_blank"
                  >
                    {t("sessions.join")}
                  </Button>
                ) : null}
                {session.recordingUrl ? (
                  <Button
                    as="link"
                    href={session.recordingUrl}
                    rel="noopener noreferrer"
                    target="_blank"
                    variant="secondary"
                  >
                    {t("teacher.sessions.show.watch_recording")}
                  </Button>
                ) : null}
              </CardFooter>
            ) : null}
          </div>
        </Card>

        {postponementRequest ? (
          <Card as="section" aria-labelledby="teacher-postponement-status">
            <CardHeader>
              <CardTitle as="h2" id="teacher-postponement-status">
                {t("postponement.request_title")}
              </CardTitle>
              <CardDescription>
                {t("common.status")}:{" "}
                {t("statuses." + postponementRequest.status)}
              </CardDescription>
            </CardHeader>
            <CardContent className="mt-4 text-sm text-[var(--ink-muted)]">
              {postponementRequest.reason}
            </CardContent>
          </Card>
        ) : canRequestPostponement && postponementRequestUrl ? (
          <Card as="section" aria-labelledby="teacher-postponement-request">
            <CardHeader>
              <CardTitle as="h2" id="teacher-postponement-request">
                {t("postponement.request_title")}
              </CardTitle>
              <CardDescription>
                {t("postponement.request_description")}
              </CardDescription>
            </CardHeader>
            <CardContent className="mt-5">
              <form
                className="grid gap-4 md:grid-cols-2"
                onSubmit={submitPostponement}
              >
                <label className="text-sm font-semibold text-[var(--ink)]">
                  {t("postponement.proposed_start")}
                  <input
                    className={`${teacherFieldClasses} mt-2`}
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
                <label className="text-sm font-semibold text-[var(--ink)]">
                  {t("postponement.reason")}
                  <textarea
                    className={`${teacherFieldClasses} mt-2 min-h-24 py-3`}
                    disabled={postponementForm.processing}
                    onChange={(event) =>
                      postponementForm.setData("reason", event.target.value)
                    }
                    required
                    value={postponementForm.data.reason}
                  />
                </label>
                <Button
                  className="md:col-span-2 md:justify-self-end"
                  disabled={postponementForm.processing}
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

        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
          <Card
            as="section"
            aria-labelledby="attendance-roster-title"
            className="min-w-0"
          >
            <CardHeader>
              <CardTitle as="h2" id="attendance-roster-title">
                {t("teacher.sessions.show.attendance.title")}
              </CardTitle>
              <CardDescription>
                {t("teacher.sessions.show.attendance.description")}
              </CardDescription>
            </CardHeader>

            <CardContent className="mt-5">
              {attendance.length === 0 ? (
                <EmptyState
                  description={t(
                    "teacher.sessions.show.attendance.empty.description",
                  )}
                  title={t("teacher.sessions.show.attendance.empty.title")}
                />
              ) : (
                <form className="space-y-6" onSubmit={submitAttendance}>
                  <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)]">
                    <ul
                      aria-label={t(
                        "teacher.sessions.show.attendance.roster_label",
                      )}
                      className="divide-y divide-[var(--line)]"
                    >
                      {attendance.map((record) => {
                        const selectId = "attendance-status-" + record.id;

                        return (
                          <li
                            className="grid gap-4 bg-[var(--surface)] p-4 transition-colors duration-150 hover:bg-[var(--surface-subtle)] sm:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)] sm:items-center"
                            key={record.id}
                          >
                            <div className="min-w-0">
                              <p className="truncate font-semibold text-[var(--ink)]">
                                {record.studentName}
                              </p>
                              <div className="mt-2">
                                <StatusPill
                                  colorMap={statusColors}
                                  status={record.status}
                                />
                              </div>
                            </div>

                            <div>
                              <label
                                className="mb-1.5 block text-sm font-semibold text-[var(--ink)]"
                                htmlFor={selectId}
                              >
                                {t(
                                  "teacher.sessions.show.attendance.status_label",
                                )}
                              </label>
                              <select
                                aria-describedby={
                                  attendanceForm.errors.statuses
                                    ? "attendance-statuses-error"
                                    : undefined
                                }
                                aria-invalid={Boolean(
                                  attendanceForm.errors.statuses,
                                )}
                                className={teacherFieldClasses}
                                disabled={attendanceForm.processing}
                                id={selectId}
                                name={"statuses[" + record.studentId + "]"}
                                onChange={(event) =>
                                  setAttendanceStatus(
                                    record.studentId,
                                    event.target.value,
                                  )
                                }
                                value={
                                  attendanceForm.data.statuses[
                                    record.studentId
                                  ] ?? record.status
                                }
                              >
                                {availableAttendanceStatuses.map((status) => (
                                  <option key={status} value={status}>
                                    {t("attendance.statuses." + status)}
                                  </option>
                                ))}
                              </select>
                            </div>
                          </li>
                        );
                      })}
                    </ul>
                  </div>

                  <FieldError
                    id="attendance-statuses-error"
                    message={attendanceForm.errors.statuses}
                  />

                  <div>
                    <label
                      className="block text-sm font-semibold text-[var(--ink)]"
                      htmlFor="attendance-change-reason"
                    >
                      {t("teacher.sessions.show.attendance.reason_label")}
                    </label>
                    <p
                      className="mt-1 text-sm leading-6 text-[var(--ink-muted)]"
                      id="attendance-change-reason-help"
                    >
                      {t("teacher.sessions.show.attendance.reason_help")}
                    </p>
                    <textarea
                      aria-describedby={
                        attendanceForm.errors.reason
                          ? "attendance-change-reason-help attendance-change-reason-error"
                          : "attendance-change-reason-help"
                      }
                      aria-invalid={Boolean(attendanceForm.errors.reason)}
                      className={`${teacherFieldClasses} min-h-28 py-3`}
                      disabled={attendanceForm.processing}
                      id="attendance-change-reason"
                      name="reason"
                      onChange={(event) =>
                        attendanceForm.setData("reason", event.target.value)
                      }
                      placeholder={t(
                        "teacher.sessions.show.attendance.reason_placeholder",
                      )}
                      required
                      value={attendanceForm.data.reason}
                    />
                    <FieldError
                      id="attendance-change-reason-error"
                      message={attendanceForm.errors.reason}
                    />
                  </div>

                  <div className="flex justify-end">
                    <Button
                      className="w-full sm:w-auto"
                      disabled={attendanceForm.processing}
                      type="submit"
                    >
                      {attendanceForm.processing
                        ? t("actions.saving")
                        : t("teacher.sessions.show.attendance.save")}
                    </Button>
                  </div>
                </form>
              )}
            </CardContent>
          </Card>

          <Card
            as="section"
            aria-labelledby="session-report-title"
            className="xl:sticky xl:top-24"
          >
            <CardHeader>
              <CardTitle as="h2" id="session-report-title">
                {t("teacher.sessions.show.report.title")}
              </CardTitle>
              <CardDescription>
                {t("teacher.sessions.show.report.description")}
              </CardDescription>
            </CardHeader>

            <CardContent className="mt-5">
              {initialReport ? (
                <p className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-4 text-sm text-[var(--ink-muted)]">
                  {t("teacher.sessions.show.report.submitted")}
                </p>
              ) : (
                <form className="space-y-5" onSubmit={submitReport}>
                  <fieldset
                    className="space-y-3"
                    disabled={reportForm.processing || !canSubmitReport}
                  >
                    <legend className="text-sm font-semibold text-[var(--ink)]">
                      {t("teacher.sessions.show.report.student_scores")}
                    </legend>
                    {attendance.map((record, index) => (
                      <div
                        className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3"
                        key={record.studentId}
                      >
                        <p className="mb-2 text-sm font-semibold text-[var(--ink)]">
                          {record.studentName}
                        </p>
                        <div className="grid grid-cols-3 gap-2">
                          {(
                            [
                              "participation",
                              "performance",
                              "commitment",
                            ] as const
                          ).map((field) => (
                            <label
                              className="text-xs text-[var(--ink-muted)]"
                              key={field}
                            >
                              {t("teacher.sessions.show.report." + field)}
                              <select
                                className={`${teacherFieldClasses} mt-1`}
                                onChange={(event) =>
                                  setReportScore(
                                    index,
                                    field,
                                    Number(event.target.value),
                                  )
                                }
                                value={
                                  reportForm.data.students[index]?.[field] ?? 3
                                }
                              >
                                {[1, 2, 3, 4, 5].map((score) => (
                                  <option key={score} value={score}>
                                    {score}
                                  </option>
                                ))}
                              </select>
                            </label>
                          ))}
                        </div>
                      </div>
                    ))}
                  </fieldset>
                  <div>
                    <label
                      className="block text-sm font-semibold text-[var(--ink)]"
                      htmlFor="session-report-summary"
                    >
                      {t("teacher.sessions.show.report.summary_label")}
                    </label>
                    <textarea
                      aria-describedby={
                        reportForm.errors.summary
                          ? "session-report-summary-error"
                          : undefined
                      }
                      aria-invalid={Boolean(reportForm.errors.summary)}
                      className={`${teacherFieldClasses} min-h-32 py-3`}
                      disabled={reportForm.processing}
                      id="session-report-summary"
                      name="summary"
                      onChange={(event) =>
                        reportForm.setData("summary", event.target.value)
                      }
                      placeholder={t(
                        "teacher.sessions.show.report.summary_placeholder",
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
                      {t("teacher.sessions.show.report.notes_label")}
                    </label>
                    <textarea
                      aria-describedby={
                        reportForm.errors.notes
                          ? "session-report-notes-error"
                          : undefined
                      }
                      aria-invalid={Boolean(reportForm.errors.notes)}
                      className={`${teacherFieldClasses} min-h-28 py-3`}
                      disabled={reportForm.processing}
                      id="session-report-notes"
                      name="notes"
                      onChange={(event) =>
                        reportForm.setData("notes", event.target.value)
                      }
                      placeholder={t(
                        "teacher.sessions.show.report.notes_placeholder",
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
                      className="w-full sm:w-auto"
                      disabled={
                        reportForm.processing ||
                        !canSubmitReport ||
                        attendance.length === 0
                      }
                      type="submit"
                    >
                      {reportForm.processing
                        ? t("actions.saving")
                        : t("teacher.sessions.show.report.submit")}
                    </Button>
                  </div>
                </form>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    );
  };

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.sessions.show.title")} />

      <div className="space-y-[var(--space-section)]">
        <PageHeader
          subtitle={t("teacher.sessions.show.subtitle")}
          title={session?.title ?? t("teacher.sessions.show.title")}
        />

        {renderContent()}
      </div>
    </AppLayout>
  );
}
