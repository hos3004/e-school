import { router, useForm } from "@inertiajs/react";
import { useMemo, useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber } from "@/lib/console-format";

type Student = {
  id: string;
  name: string;
  attendance: string | null;
  joined: boolean;
};

type ReportStudent = {
  id: string;
  name: string;
  participation: number | null;
  performance: number | null;
  commitment: number | null;
  note: string | null;
};

type ReportDetail = {
  topics: string | null;
  homework: string | null;
  notes: string | null;
  next_plan: string | null;
  is_late: boolean;
  students: ReportStudent[];
};

type Row = {
  id: string;
  status: string;
  status_label: string;
  never_started: boolean;
  due: boolean;
  teacher_joined: boolean;
  ready: boolean;
  approve_url: string;
  report_detail: ReportDetail | null;
  date: string;
  start: string;
  end: string;
  minutes: number;
  teacher: string;
  course: string;
  students: Student[];
  participants: number;
  attendance_recorded: number;
  all_absent: boolean;
  report: "submitted" | "late" | "missing";
  rate_ok: boolean | null;
};

type Decision = "complete" | "no_show" | "excused" | "cancelled_by_school";

const DECISIONS: Decision[] = [
  "complete",
  "no_show",
  "excused",
  "cancelled_by_school",
];

export default function SessionReview({
  rows,
  timezone,
  can,
}: {
  rows: Row[];
  timezone: string;
  can: Record<Decision, boolean>;
}) {
  const t = useI18n();
  const [filter, setFilter] = useState<
    "all" | "due" | "awaiting_review" | "never_started"
  >("all");
  const [open, setOpen] = useState<string | null>(null);
  const [openReport, setOpenReport] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [quickError, setQuickError] = useState<string | null>(null);

  const form = useForm({
    decision: "complete" as Decision,
    expected_status: "",
    reason: "",
  });

  const visible = useMemo(
    () =>
      rows.filter((row) =>
        filter === "all"
          ? true
          : filter === "due"
            ? row.due
            : filter === "never_started"
              ? row.never_started
              : !row.never_started,
      ),
    [rows, filter],
  );

  const counts = useMemo(
    () => ({
      total: rows.length,
      due: rows.filter((row) => row.due).length,
      awaiting_review: rows.filter((row) => !row.never_started).length,
      never_started: rows.filter((row) => row.never_started).length,
    }),
    [rows],
  );

  const start = (row: Row) => {
    setOpen(row.id);
    /*
     * الافتراض يتبع الدليل المرصود لا الراحة: حصة كل طلابها متغيّبون قرارها
     * الطبيعي «تغيّب الطالب»، وغيرها «اعتماد». المستخدم يظل حرًّا في تغييره.
     */
    form.setData({
      decision: row.all_absent ? "no_show" : "complete",
      expected_status: row.status,
      reason: "",
    });
  };

  const submit = (event: FormEvent, row: Row) => {
    event.preventDefault();
    form.post("/manage/sessions/" + row.id + "/review", {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(null);
        form.reset();
      },
    });
  };

  /*
   * الاعتماد السريع لا يرسل سببًا: الخادم يولّده من الدليل ويعيد فحص الأهلية
   * بنفسه، فلا يُبنى قرار مالي على ما عرضته شاشة قد تكون قديمة.
   */
  const approve = (row: Row) => {
    setQuickError(null);
    setBusy(row.id);
    router.post(
      row.approve_url,
      { expected_status: row.status },
      {
        preserveScroll: true,
        onError: (errors) =>
          setQuickError(errors.decision ?? errors.expected_status ?? null),
        onFinish: () => setBusy(null),
      },
    );
  };

  return (
    <ConsoleLayout
      title={t("console_session_review.title")}
      section="after"
      description={t("console_session_review.description")}
    >
      <div className="console-metrics">
        {(["total", "due", "awaiting_review", "never_started"] as const).map(
          (key) => (
            <p
              className={"console-metric" + (key === "due" ? " is-due" : "")}
              key={key}
            >
              <span>{t("console_session_review.counts." + key)}</span>
              <strong>{formatNumber(counts[key])}</strong>
            </p>
          ),
        )}
      </div>

      <div className="console-actions">
        {(["all", "due", "awaiting_review", "never_started"] as const).map(
          (key) => (
            <button
              key={key}
              type="button"
              className={"console-button" + (filter === key ? " primary" : "")}
              aria-pressed={filter === key}
              onClick={() => setFilter(key)}
            >
              {t("console_session_review.filters." + key)}
            </button>
          ),
        )}
        <small>{timezone}</small>
      </div>

      {quickError && (
        <p className="console-feedback is-error" role="alert">
          {quickError}
        </p>
      )}

      {visible.length === 0 ? (
        <p className="console-empty">{t("console_session_review.empty")}</p>
      ) : (
        <div className="console-table-wrap">
          <table className="console-table">
            <thead>
              <tr>
                {[
                  "time",
                  "teacher",
                  "course",
                  "students",
                  "evidence",
                  "status",
                  "decision",
                ].map((key) => (
                  <th key={key}>
                    {t("console_session_review.columns." + key)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {visible.map((row) => (
                <tr key={row.id} className={row.due ? "is-due" : undefined}>
                  <td>
                    {formatDate(row.date, timezone)}
                    <small>
                      <bdi>
                        {row.start} — {row.end}
                      </bdi>{" "}
                      · {formatNumber(row.minutes)}{" "}
                      {t("console_sessions.minutes")}
                    </small>
                  </td>
                  <td>{row.teacher}</td>
                  <td>{row.course}</td>
                  <td>
                    {row.students.map((student) => (
                      <span key={student.id}>
                        {student.name}
                        {student.attendance !== null && (
                          <small>
                            {t(
                              "console_session_review.attendance_status." +
                                student.attendance,
                            )}
                          </small>
                        )}
                      </span>
                    ))}
                  </td>
                  <td>
                    {row.due && (
                      <small
                        className="console-due-badge"
                        title={t("console_session_review.due_hint")}
                      >
                        {t("console_session_review.due_badge")}
                      </small>
                    )}
                    <small>
                      {t("console_session_review.evidence.report_" + row.report)}
                    </small>
                    <small>
                      {t("console_session_review.evidence.attendance")}:{" "}
                      <bdi>
                        {formatNumber(row.attendance_recorded)} /{" "}
                        {formatNumber(row.participants)}
                      </bdi>
                    </small>
                    {row.all_absent && (
                      <small>
                        {t("console_session_review.evidence.all_absent")}
                      </small>
                    )}
                    {row.never_started && (
                      <small
                        title={t(
                          "console_session_review.evidence.never_started_hint",
                        )}
                      >
                        {t("console_session_review.evidence.never_started")}
                      </small>
                    )}
                    {row.rate_ok === false && (
                      <small
                        title={t("console_session_review.evidence.no_rate_hint")}
                      >
                        {t("console_session_review.evidence.no_rate")}
                      </small>
                    )}
                    {row.report_detail && (
                      <>
                        <button
                          type="button"
                          className="inline-link"
                          onClick={() =>
                            setOpenReport(openReport === row.id ? null : row.id)
                          }
                        >
                          {t(
                            openReport === row.id
                              ? "console_session_review.report.hide"
                              : "console_session_review.report.show",
                          )}
                        </button>
                        {openReport === row.id && (
                          <div className="console-report-detail">
                            {row.report_detail.topics && (
                              <p>
                                <b>
                                  {t("console_session_review.report.topics")}
                                </b>
                                {row.report_detail.topics}
                              </p>
                            )}
                            {row.report_detail.homework && (
                              <p>
                                <b>
                                  {t("console_session_review.report.homework")}
                                </b>
                                {row.report_detail.homework}
                              </p>
                            )}
                            {row.report_detail.notes && (
                              <p>
                                <b>
                                  {t("console_session_review.report.notes")}
                                </b>
                                {row.report_detail.notes}
                              </p>
                            )}
                            {row.report_detail.next_plan && (
                              <p>
                                <b>
                                  {t("console_session_review.report.next_plan")}
                                </b>
                                {row.report_detail.next_plan}
                              </p>
                            )}
                            {row.report_detail.students.map((student) => (
                              <p key={student.id}>
                                <b>{student.name}</b>
                                <bdi>
                                  {formatNumber(student.participation ?? 0)} /{" "}
                                  {formatNumber(student.performance ?? 0)} /{" "}
                                  {formatNumber(student.commitment ?? 0)}
                                </bdi>{" "}
                                <small>
                                  {t("console_session_review.report.scores")}
                                </small>
                                {student.note ? ` — ${student.note}` : ""}
                              </p>
                            ))}
                          </div>
                        )}
                      </>
                    )}
                  </td>
                  <td>
                    <span className="console-status">{row.status_label}</span>
                  </td>
                  <td>
                    {open === row.id ? (
                      <form onSubmit={(event) => submit(event, row)}>
                        <label>
                          {t("console_session_review.fields.decision")}
                          <select
                            className="console-control"
                            value={form.data.decision}
                            onChange={(event) =>
                              form.setData(
                                "decision",
                                event.target.value as Decision,
                              )
                            }
                          >
                            {DECISIONS.filter((decision) => can[decision]).map(
                              (decision) => (
                                <option key={decision} value={decision}>
                                  {t(
                                    "console_session_review.decisions." +
                                      decision,
                                  )}
                                </option>
                              ),
                            )}
                          </select>
                        </label>
                        <small>
                          {t(
                            "console_session_review.decision_hints." +
                              (form.data.decision === "complete" &&
                              row.never_started
                                ? "complete_off_platform"
                                : form.data.decision),
                          )}
                        </small>
                        <label>
                          {t("console_session_review.fields.reason")}
                          <textarea
                            className="console-control"
                            rows={2}
                            required
                            maxLength={2000}
                            value={form.data.reason}
                            placeholder={t(
                              "console_session_review.reason_placeholder",
                            )}
                            onChange={(event) =>
                              form.setData("reason", event.target.value)
                            }
                          />
                        </label>
                        {form.errors.decision && (
                          <p className="console-feedback is-error">
                            {form.errors.decision}
                          </p>
                        )}
                        {form.errors.reason && (
                          <p className="console-feedback is-error">
                            {form.errors.reason}
                          </p>
                        )}
                        <div className="console-actions">
                          <button
                            type="submit"
                            className="console-button primary"
                            disabled={form.processing}
                          >
                            {form.processing
                              ? t("console_session_review.submitting")
                              : t("console_session_review.submit")}
                          </button>
                          <button
                            type="button"
                            className="console-button"
                            onClick={() => {
                              setOpen(null);
                              form.reset();
                            }}
                          >
                            {t("console_session_review.cancel")}
                          </button>
                        </div>
                      </form>
                    ) : (
                      <div className="console-actions">
                        {row.ready && can.complete && (
                          <button
                            type="button"
                            className="console-button primary"
                            title={t("console_session_review.quick.hint")}
                            disabled={busy === row.id}
                            onClick={() => approve(row)}
                          >
                            {busy === row.id
                              ? t("console_session_review.quick.approving")
                              : t("console_session_review.quick.approve")}
                          </button>
                        )}
                        <button
                          type="button"
                          className="console-button"
                          onClick={() => start(row)}
                        >
                          {t("console_session_review.columns.decision")}
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </ConsoleLayout>
  );
}
