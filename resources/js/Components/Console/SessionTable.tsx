import { useEffect, useRef, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber, formatTime } from "@/lib/console-format";
import type { AppPageProps } from "@/types";

export type ReportRow = {
  id: string;
  title: string;
  course: string;
  course_id: string;
  report_status: string;
  original_teacher: string;
  original_teacher_id: string;
  has_substitute: boolean;
  cancellation_reason: string | null;
  group: string;
  group_id: string;
  actual_teacher: string;
  actual_teacher_id: string;
  scheduled_start: string;
  scheduled_end: string;
  status: string;
  status_label: string;
  report_status_label: string;
  duration_minutes: number;
  actual_duration_minutes: number | null;
  session_type_label: string;
  attendance_summary: string;
  students: {
    id: string;
    name: string;
    attendance_label: string;
    attended_minutes: number;
  }[];
};

type SessionReport = {
  session: {
    actual_start: string | null;
    actual_end: string | null;
    finalized_at: string | null;
  };
  recordings: {
    id: string;
    status: string;
    status_label: string;
    duration_minutes: number | null;
    expires_at: string | null;
    preview_url: string | null;
  }[];
};
export default function SessionTable({
  rows,
  timezone,
  compact = false,
}: {
  rows: ReportRow[];
  timezone: string;
  compact?: boolean;
}) {
  const t = useI18n();
  const { locale = "ar" } = usePage<AppPageProps>().props;
  const [detail, setDetail] = useState<ReportRow | null>(null);
  if (!rows.length)
    return (
      <div className="console-empty">
        <h2>{t("console.no_sessions")}</h2>
        <p>{t("console.no_sessions_description")}</p>
      </div>
    );
  if (compact)
    return (
      <>
        <div className="console-table-wrap">
          <table className="console-table dashboard-daily-table">
            <thead>
              <tr>
                {[
                  "console.columns.time",
                  "console_dashboard.session",
                  "console.columns.teacher",
                  "console_dashboard.status",
                  "console_dashboard.open_session",
                ].map((key) => (
                  <th key={key}>{t(key)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <strong>
                      <bdi>{formatTime(row.scheduled_start, timezone)}</bdi>
                    </strong>
                    <small>
                      {formatNumber(row.duration_minutes)}{" "}
                      {t("console.minutes")}
                    </small>
                  </td>
                  <td>
                    <button
                      className="console-link"
                      onClick={() => setDetail(row)}
                    >
                      {row.group || row.title || row.course}
                    </button>
                    <small>
                      {row.course} · {row.session_type_label}
                    </small>
                  </td>
                  <td>
                    {row.actual_teacher || t("console.unassigned")}
                    <small>
                      {formatNumber(row.students.length)}{" "}
                      {t("console_dashboard.student")}
                    </small>
                  </td>
                  <td>
                    <span
                      className={
                        "console-status " +
                        (row.status === "completed" ||
                        row.status === "in_progress"
                          ? "success"
                          : row.status === "awaiting_review"
                            ? "warning"
                            : "")
                      }
                    >
                      {row.status_label}
                    </span>
                  </td>
                  <td>
                    <button
                      className="console-link dashboard-row-open"
                      onClick={() => setDetail(row)}
                      aria-label={
                        t("console_dashboard.open_session") +
                        ": " +
                        (row.title || row.course)
                      }
                    >
                      ↖
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {detail && (
          <SessionDetail
            row={detail}
            timezone={timezone}
            onClose={() => setDetail(null)}
          />
        )}
      </>
    );
  return (
    <>
      <div className="console-table-wrap">
        <table className="console-table">
          <thead>
            <tr>
              {["session", "time", "teacher", "attendance", "report"].map(
                (key) => (
                  <th key={key} scope="col">
                    {t("console.columns." + key)}
                  </th>
                ),
              )}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td>
                  <button
                    className="console-link"
                    onClick={() => setDetail(row)}
                  >
                    {row.title || row.course}
                  </button>
                  <small>{row.group || row.session_type_label}</small>
                  <span
                    className={
                      "console-status " +
                      (row.status === "completed" ? "success" : "")
                    }
                  >
                    {row.status_label}
                  </span>
                </td>
                <td>
                  <strong>
                    <bdi>
                      {formatTime(row.scheduled_start, timezone, locale)} –{" "}
                      {formatTime(row.scheduled_end, timezone, locale)}
                    </bdi>
                  </strong>
                  <small>
                    <bdi>
                      {formatDate(row.scheduled_start, timezone, locale)}
                    </bdi>
                  </small>
                  <small>
                    {formatNumber(row.duration_minutes, locale)}{" "}
                    {t("console.minutes")}
                  </small>
                </td>
                <td>{row.actual_teacher || t("console.unassigned")}</td>
                <td>
                  <details>
                    <summary
                      className="console-link"
                      style={{ cursor: "pointer" }}
                    >
                      {row.attendance_summary || t("console.show_students")}
                    </summary>
                    <ul
                      className="console-stack"
                      style={{ listStyle: "none", padding: 0, marginTop: 12 }}
                    >
                      {row.students.map((student) => (
                        <li key={student.id}>
                          <Link
                            href={"/manage/students/" + student.id}
                            className="console-link"
                          >
                            {student.name}
                          </Link>
                          <small>
                            {student.attendance_label} ·{" "}
                            {formatNumber(student.attended_minutes, locale)}{" "}
                            {t("console.minutes")}
                          </small>
                        </li>
                      ))}
                    </ul>
                  </details>
                </td>
                <td>
                  <span className="console-status">
                    {row.report_status_label}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {detail && (
        <SessionDetail
          row={detail}
          timezone={timezone}
          onClose={() => setDetail(null)}
        />
      )}
    </>
  );
}

function SessionDetail({
  row,
  timezone,
  onClose,
}: {
  row: ReportRow;
  timezone: string;
  onClose: () => void;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const [report, setReport] = useState<SessionReport | null>(null);
  const [reportFailed, setReportFailed] = useState(false);
  const { console: context } = usePage<
    AppPageProps & { console?: { navigation: { key: string }[] } }
  >().props;
  const can = (key: string) =>
    context?.navigation.some((item) => item.key === key);
  useEffect(() => {
    const element = dialog.current;
    const previous = document.activeElement;
    const overflow = document.body.style.overflow;
    element?.showModal();
    document.body.style.overflow = "hidden";
    return () => {
      element?.close();
      document.body.style.overflow = overflow;
      if (previous instanceof HTMLElement && previous.isConnected)
        previous.focus();
    };
  }, []);
  useEffect(() => {
    let active = true;
    setReport(null);
    setReportFailed(false);
    fetch("/manage/sessions/" + row.id + "/report", {
      headers: { Accept: "application/json" },
    })
      .then((response) => (response.ok ? response.json() : Promise.reject()))
      .then((data: SessionReport) => {
        if (active) setReport(data);
      })
      .catch(() => {
        if (active) setReportFailed(true);
      });
    return () => {
      active = false;
    };
  }, [row.id]);
  return (
    <dialog
      ref={dialog}
      className="console-session-dialog"
      aria-labelledby="session-detail-title"
      onCancel={(event) => {
        event.preventDefault();
        onClose();
      }}
    >
      <div className="console-session-heading">
        <div>
          <p>{t("console_dashboard.details")}</p>
          <h2 id="session-detail-title">{row.title || row.course}</h2>
        </div>
        <button className="console-button" onClick={onClose}>
          {t("console_dashboard.close")}
        </button>
      </div>
      <div className="console-actions">
        <span
          className={
            "console-status " + (row.status === "completed" ? "success" : "")
          }
        >
          {row.status_label}
        </span>
        <span className="console-status">{row.report_status_label}</span>
        <button className="console-button small" onClick={() => window.print()}>
          {t("console_dashboard.print")}
        </button>
      </div>
      <section className="console-session-section">
        <h3>{t("console_dashboard.context")}</h3>
        <dl>
          <div>
            <dt>{t("console.nav.courses")}</dt>
            <dd>{row.course}</dd>
          </div>
          <div>
            <dt>{t("console.nav.groups")}</dt>
            <dd>
              {row.group ? (
                can("groups") ? (
                  <Link
                    className="console-link"
                    href={"/manage/groups?group=" + row.group_id}
                  >
                    {row.group}
                  </Link>
                ) : (
                  row.group
                )
              ) : (
                row.session_type_label
              )}
            </dd>
          </div>
          <div>
            <dt>{t("console.columns.teacher")}</dt>
            <dd>
              {row.actual_teacher_id && can("teachers") ? (
                <Link
                  className="console-link"
                  href={"/manage/teachers/" + row.actual_teacher_id}
                >
                  {row.actual_teacher}
                </Link>
              ) : (
                row.actual_teacher || t("console.unassigned")
              )}
            </dd>
          </div>
          {row.has_substitute && (
            <div>
              <dt>{t("console_dashboard.original_teacher")}</dt>
              <dd>{row.original_teacher}</dd>
            </div>
          )}
          <div>
            <dt>{t("console.columns.time")}</dt>
            <dd>
              {formatDate(row.scheduled_start, timezone)}
              <br />
              <bdi>
                {formatTime(row.scheduled_start, timezone)} –{" "}
                {formatTime(row.scheduled_end, timezone)}
              </bdi>
              <small>
                <bdi>{timezone}</bdi>
              </small>
            </dd>
          </div>
          {report?.session.actual_start && (
            <div>
              <dt>{t("console_dashboard.actual_time")}</dt>
              <dd>
                <bdi>
                  {formatTime(report.session.actual_start, timezone)}
                  {report.session.actual_end
                    ? " – " + formatTime(report.session.actual_end, timezone)
                    : ""}
                </bdi>
                <small>
                  <bdi>{formatDate(report.session.actual_start, timezone)}</bdi>
                </small>
              </dd>
            </div>
          )}
          <div>
            <dt>{t("console_dashboard.planned_minutes")}</dt>
            <dd>
              {formatNumber(row.duration_minutes)} {t("console.minutes")}
            </dd>
          </div>
          <div>
            <dt>{t("console_dashboard.actual_minutes")}</dt>
            <dd>
              {row.actual_duration_minutes === null
                ? "—"
                : formatNumber(row.actual_duration_minutes)}
            </dd>
          </div>
        </dl>
        {row.cancellation_reason && (
          <div className="dashboard-quiet">
            <strong>{t("console_dashboard.cancellation")}</strong>
            <p>{row.cancellation_reason}</p>
          </div>
        )}
      </section>
      <section className="console-session-section">
        <h3>{t("console_dashboard.attendance")}</h3>
        {row.students.length ? (
          <div className="console-table-wrap">
            <table className="console-table">
              <thead>
                <tr>
                  <th>{t("console_dashboard.student")}</th>
                  <th>{t("console_dashboard.status")}</th>
                  <th>{t("console_dashboard.actual_minutes")}</th>
                </tr>
              </thead>
              <tbody>
                {row.students.map((student) => (
                  <tr key={student.id}>
                    <td>
                      {can("students") ? (
                        <Link
                          className="console-link"
                          href={"/manage/students/" + student.id}
                        >
                          {student.name}
                        </Link>
                      ) : (
                        student.name
                      )}
                    </td>
                    <td>{student.attendance_label}</td>
                    <td>{formatNumber(student.attended_minutes)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="console-empty">
            {t("console_dashboard.empty_attendance")}
          </p>
        )}
      </section>
      <section className="console-session-section">
        <h3>{t("console_dashboard.recording")}</h3>
        {report === null ? (
          <p className="console-empty">
            {reportFailed
              ? t("console_dashboard.recording_empty")
              : t("console_dashboard.loading")}
          </p>
        ) : report.recordings.length ? (
          <ul
            className="console-stack"
            style={{ listStyle: "none", padding: 0 }}
          >
            {report.recordings.map((recording) => (
              <li key={recording.id}>
                <span className="console-status">
                  {recording.status_label}
                </span>
                {recording.duration_minutes !== null && (
                  <small>
                    {formatNumber(recording.duration_minutes)}{" "}
                    {t("console.minutes")}
                  </small>
                )}{" "}
                {recording.preview_url ? (
                  <a
                    className="console-link"
                    href={recording.preview_url}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {t("console_dashboard.recording_preview")}
                  </a>
                ) : (
                  <small>{t("console_dashboard.recording_unavailable")}</small>
                )}
              </li>
            ))}
          </ul>
        ) : (
          <p className="console-empty">
            {t("console_dashboard.recording_empty")}
          </p>
        )}
      </section>
    </dialog>
  );
}
