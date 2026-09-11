import { Link, useForm, usePage } from "@inertiajs/react";
import { useEffect, useState, type FormEvent } from "react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps, Attendance, Session as Lesson } from "@/types";
import { date, Empty, Errors, Section, ServiceLink } from "./shared";
import {
  attendanceChanges,
  clearSessionDraft,
  rememberSessionDraft,
  restoreSessionDraft,
} from "./session-drafts";

import SessionRequests, { type SessionRequestProps } from "./SessionRequests";

interface Props extends SessionRequestProps {
  kind: LearningKind;
  timezone: string;
  session: Lesson;
  attendance?: (Attendance & { confirmedAt?: string | null })[];
  attendanceStatuses?: string[];
  attendanceUpdateUrl?: string;
  reportSubmitUrl?: string;
  initialReport?: { summary?: string; notes?: string } | null;
  canSubmitReport?: boolean;
  canViewStudents?: boolean;
  studentJoinLinks?: Record<string, string>;
  reportScoreMin: number;
  reportScoreMax: number;
}
export default function Session({
  kind,
  timezone,
  session,
  attendance = [],
  attendanceStatuses = [],
  attendanceUpdateUrl,
  reportSubmitUrl,
  initialReport,
  canSubmitReport = false,
  canViewStudents = false,
  studentJoinLinks = {},
  reportScoreMin,
  reportScoreMax,
  ...requestProps
}: Props) {
  const t = useI18n();
  const locale = "ar";
  const [now, setNow] = useState(Date.now);
  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 30000);
    return () => window.clearInterval(timer);
  }, []);
  // العائد من الفصل يصل ومعه focus=report، فننقله إلى النموذج جاهزًا للكتابة
  // بدل أن يهبط أعلى الصفحة ويبحث عنه — والتقرير هو أول ما يُطلب منه بعد الحصة.
  useEffect(() => {
    if (kind !== "teacher") return;
    const params = new URLSearchParams(window.location.search);
    if (params.get("focus") !== "report") return;
    const section = document.getElementById("report");
    if (!section) return;
    section.scrollIntoView({ block: "start" });
    section
      .querySelector<HTMLSelectElement | HTMLTextAreaElement>(
        "select:not(:disabled), textarea:not(:disabled)",
      )
      ?.focus({ preventScroll: true });
  }, [kind]);
  const userId = usePage<AppPageProps>().props.auth.user?.id ?? "";
  const draft = restoreSessionDraft(
    userId,
    session.id,
    attendance,
    initialReport,
  );
  const attendanceForm = useForm(
    `learning-attendance-${userId}-${session.id}`,
    draft.attendance,
  );
  const report = useForm(
    `learning-report-${userId}-${session.id}`,
    draft.report,
  );
  const attendanceUpdates = attendanceChanges(
    attendance,
    attendanceForm.data.statuses,
  );
  const requiresReason = attendance.some(
    (row) =>
      row.recordedAt &&
      attendanceForm.data.statuses[row.studentId] !== row.status,
  );
  const joinAvailable =
    Boolean(session.joinUrl) &&
    ["scheduled", "confirmed", "in_progress"].includes(session.status) &&
    now >= Date.parse(session.canJoinAt ?? session.startsAt) &&
    now <= Date.parse(session.canJoinUntil ?? session.endsAt);
  const scoreOptions = Array.from(
    { length: Math.max(0, reportScoreMax - reportScoreMin + 1) },
    (_, index) => index + reportScoreMin,
  );
  const [copiedStudentId, setCopiedStudentId] = useState<string | null>(null);
  const [copyFailedStudentId, setCopyFailedStudentId] = useState<string | null>(
    null,
  );
  async function copyStudentLink(studentId: string, link: string) {
    setCopiedStudentId(null);
    setCopyFailedStudentId(null);
    try {
      await navigator.clipboard.writeText(link);
      setCopiedStudentId(studentId);
    } catch {
      setCopyFailedStudentId(studentId);
    }
  }
  function saveAttendance(event: FormEvent) {
    event.preventDefault();
    if (attendanceUpdateUrl) {
      attendanceForm.transform((data) => ({
        ...data,
        statuses: attendanceChanges(attendance, data.statuses),
      }));
      attendanceForm.post(attendanceUpdateUrl, {
        preserveScroll: true,
        onSuccess: () => {
          clearSessionDraft(userId, session.id, "attendance");
          attendanceForm.setDefaults();
        },
      });
    }
  }
  function submitReport(event: FormEvent) {
    event.preventDefault();
    if (reportSubmitUrl)
      report.post(reportSubmitUrl, {
        preserveScroll: true,
        onSuccess: () => {
          clearSessionDraft(userId, session.id, "report");
          report.setDefaults();
        },
      });
  }
  return (
    <LearningLayout
      kind={kind}
      title={session.title || t("learning.lesson_details")}
    >
      <div className="learning-page-actions">
        <Link className="learning-text" href={`/learn/${kind}#schedule`}>
          {t("learning.back_portal")}
        </Link>
        <span>
          {t("learning.times_in")} <b dir="ltr">{timezone}</b>
        </span>
      </div>
      <section className="learning-next">
        <p className="learning-eyebrow">{t("learning.lesson_details")}</p>
        <div className="learning-next-content">
          <div>
            <h1>{session.title || session.subject}</h1>
            <p>
              {session.subject} · {session.teacher?.name}
            </p>
            <span className="learning-tag">
              {t(`statuses.${session.status}`)}
            </span>
          </div>
          <div className="learning-clock">
            <b>{date(session.startsAt, locale, timezone, true)}</b>
            <small>{date(session.startsAt, locale, timezone)}</small>
          </div>
        </div>
        <div className="learning-actions">
          {joinAvailable ? (
            <ServiceLink
              className="learning-button"
              href={session.joinUrl ?? ""}
            >
              {t("learning.join_lesson")}
            </ServiceLink>
          ) : (
            <p className="learning-help">
              {t("learning.join_window")}
              {session.canJoinAt
                ? ` ${date(session.canJoinAt, locale, timezone, true)}`
                : ""}
            </p>
          )}
          {session.recordingUrl && (
            <ServiceLink
              href={session.recordingUrl}
              className="learning-button secondary"
            >
              {t("learning.watch_recording")}
            </ServiceLink>
          )}
        </div>
      </section>
      <SessionRequests
        {...requestProps}
        kind={kind}
        timezone={timezone}
        sessionId={session.id}
      />
      {kind === "teacher" && (
        <div className="learning-session-columns">
          <Section
            id="attendance"
            title={t("learning.attendance")}
            subtitle={t("learning.attendance_help")}
          >
            {attendance.length ? (
              <form
                className="learning-card learning-form"
                onSubmit={saveAttendance}
              >
                <Errors errors={attendanceForm.errors} />
                {Object.keys(studentJoinLinks).length > 0 && (
                  <p className="learning-help">
                    {t("learning.student_link_help")}
                  </p>
                )}
                {attendance.map((row) => (
                  <div className="learning-attendance-row" key={row.id}>
                    <div>
                      {canViewStudents ? (
                        <Link
                          href={`/learn/teacher/students/${row.studentId}?from=${session.id}`}
                          onBefore={() =>
                            rememberSessionDraft(
                              userId,
                              session.id,
                              attendanceForm.data,
                              report.data,
                            )
                          }
                          className="learning-text"
                        >
                          {row.studentName}
                        </Link>
                      ) : (
                        <b>{row.studentName}</b>
                      )}
                      <small>
                        {row.recordedAt
                          ? t("learning.recorded")
                          : t("learning.not_recorded")}
                      </small>
                      {studentJoinLinks[row.studentId] && (
                        <div className="learning-student-link">
                          <button
                            type="button"
                            className="learning-button secondary small"
                            onClick={() =>
                              copyStudentLink(
                                row.studentId,
                                studentJoinLinks[row.studentId] ?? "",
                              )
                            }
                          >
                            {t("learning.copy_student_link")}
                          </button>
                          <details>
                            <summary>{t("learning.show_student_link")}</summary>
                            <input
                              readOnly
                              dir="ltr"
                              aria-label={`${t("learning.copy_student_link")} ${row.studentName}`}
                              value={studentJoinLinks[row.studentId] ?? ""}
                              onFocus={(event) => event.target.select()}
                            />
                          </details>
                          {copiedStudentId === row.studentId && (
                            <small role="status">
                              {t("learning.copy_student_link_done")}
                            </small>
                          )}
                          {copyFailedStudentId === row.studentId && (
                            <small role="status">
                              {t("learning.copy_student_link_failed")}
                            </small>
                          )}
                        </div>
                      )}
                    </div>
                    <label>
                      <span className="learning-sr-only">
                        {t("learning.attendance")} {row.studentName}
                      </span>
                      <select
                        value={attendanceForm.data.statuses[row.studentId]}
                        onChange={(event) =>
                          attendanceForm.setData("statuses", {
                            ...attendanceForm.data.statuses,
                            [row.studentId]: event.target.value,
                          })
                        }
                      >
                        <option
                          value="pending"
                          disabled={Boolean(row.recordedAt)}
                        >
                          {t("learning.choose_status")}
                        </option>
                        {attendanceStatuses.map((status) => (
                          <option key={status} value={status}>
                            {t(`attendance.statuses.${status}`)}
                          </option>
                        ))}
                      </select>
                    </label>
                  </div>
                ))}
                {requiresReason && (
                  <label>
                    {t("learning.correction_note")}
                    <textarea
                      required
                      value={attendanceForm.data.reason}
                      onChange={(event) =>
                        attendanceForm.setData("reason", event.target.value)
                      }
                    />
                    <small>{t("learning.correction_help")}</small>
                  </label>
                )}
                <button
                  type="submit"
                  className="learning-button"
                  disabled={
                    attendanceForm.processing ||
                    Object.keys(attendanceUpdates).length === 0
                  }
                >
                  {t(
                    attendanceForm.processing
                      ? "learning.saving"
                      : "learning.save_attendance",
                  )}
                </button>
              </form>
            ) : (
              <Empty>{t("learning.no_students")}</Empty>
            )}
          </Section>
          <Section
            id="report"
            title={t("learning.lesson_report")}
            subtitle={t("learning.report_help")}
          >
            {initialReport ? (
              <div className="learning-card">
                <span className="learning-tag">
                  {t("learning.report_submitted")}
                </span>
                <p>{initialReport.summary}</p>
                {initialReport.notes && <p>{initialReport.notes}</p>}
              </div>
            ) : (
              <form
                className="learning-card learning-form"
                onSubmit={submitReport}
              >
                <Errors errors={report.errors} />
                <fieldset disabled={report.processing || !canSubmitReport}>
                  <legend>{t("learning.student_assessment")}</legend>
                  {attendance.map((row, index) => (
                    <div className="learning-assessment" key={row.studentId}>
                      <b>{row.studentName}</b>
                      <div>
                        {(
                          [
                            "participation",
                            "performance",
                            "commitment",
                          ] as const
                        ).map((field) => (
                          <label key={field}>
                            {t(`teacher.sessions.show.report.${field}`)}
                            <select
                              required
                              value={report.data.students[index]?.[field] ?? ""}
                              onChange={(event) =>
                                report.setData(
                                  "students",
                                  report.data.students.map(
                                    (student, position) =>
                                      position === index
                                        ? {
                                            ...student,
                                            [field]: event.target.value,
                                          }
                                        : student,
                                  ),
                                )
                              }
                            >
                              <option value="">
                                {t("learning.choose_score")}
                              </option>
                              {scoreOptions.map((score) => (
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
                <label>
                  {t("learning.covered_topics")}
                  <textarea
                    required
                    maxLength={5000}
                    value={report.data.summary}
                    onChange={(event) =>
                      report.setData("summary", event.target.value)
                    }
                  />
                </label>
                <label>
                  {t("learning.optional_notes")}
                  <textarea
                    maxLength={5000}
                    value={report.data.notes}
                    onChange={(event) =>
                      report.setData("notes", event.target.value)
                    }
                  />
                </label>
                {!canSubmitReport && (
                  <p className="learning-help">
                    {t("learning.report_not_ready")}
                  </p>
                )}
                <button
                  className="learning-button"
                  type="submit"
                  disabled={
                    report.processing ||
                    !canSubmitReport ||
                    attendance.length === 0
                  }
                >
                  {t(
                    report.processing
                      ? "learning.saving"
                      : "learning.submit_report",
                  )}
                </button>
              </form>
            )}
          </Section>
        </div>
      )}
    </LearningLayout>
  );
}
