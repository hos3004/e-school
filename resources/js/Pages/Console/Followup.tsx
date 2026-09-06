import { Link, router, useForm } from "@inertiajs/react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber, formatTime } from "@/lib/console-format";
type Entry = {
  id: string;
  label?: string;
  at: string | null;
  actor_id?: string | null;
  reason?: string | null;
};
type AttendanceEntry = Entry & {
  title: string;
  session_id: string;
  status: string;
  confirmed: boolean;
  excused_at: string | null;
};
type Violation = Entry & {
  type: string;
  session_id: string | null;
  countable: boolean;
  waived_at: string | null;
};
type Reactivation = Entry & {
  status: string;
  attempt: number;
  statement: string;
  decision_note: string | null;
};
type Case = {
  id: string;
  student_id: string;
  name: string;
  code: string;
  program: string;
  tracks: string[];
  status: string;
  status_label: string;
  return_date: string | null;
  absences: number;
  violations: number;
  held: boolean;
  next: string;
};
type Selected = Case & {
  can_correct_attendance: boolean;
  can_waive: boolean;
  attendance_statuses: { value: string; label: string }[];
  attendance: AttendanceEntry[];
  frozen_reason: string | null;
  actors: Record<string, string>;
  history: (Entry & { from: string | null; to: string })[];
  discipline: {
    violations: Violation[];
    actions: Entry[];
    requests: Reactivation[];
  };
  actions: string[];
  open_request: Reactivation | null;
  submit_url: string;
  assessment_options: {
    id: string;
    title: string;
    score: number | null;
    total: number;
    passed: boolean;
  }[];
};
type Props = {
  cases: {
    data: Case[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: { search: string; filter: string; track: string };
  counts: { students: number; absence: number; held: number };
  selected: Selected | null;
  timezone: string;
  window: { from: string; until: string };
  requiresAssessment: boolean;
};
const contextKeys = [
  "student_request",
  "health_travel",
  "attendance_review",
  "requirements_completed",
  "other",
];
export default function Followup(props: Props) {
  const t = useI18n();
  const filterForm = useForm(props.filters);
  function filter(event: FormEvent) {
    event.preventDefault();
    filterForm.get("/manage/followup", { preserveScroll: true });
  }
  const query = (extra: Record<string, string> = {}) =>
    "/manage/followup?" + new URLSearchParams({ ...props.filters, ...extra });
  return (
    <ConsoleLayout
      title={t("console_followup.title")}
      description={t("console_followup.description")}
      section="during"
    >
      <div className="console-metrics followup-stats">
        {(["students", "absence", "held"] as const).map((key) => (
          <div className="console-metric" key={key}>
            <span>{t("console_followup.counts." + key)}</span>
            <strong>{formatNumber(props.counts[key])}</strong>
          </div>
        ))}
      </div>
      <section className="panel">
        <div className="panel-head">
          <div>
            <h2>{t("console_followup.table_title")}</h2>
            <p>{t("console_followup.table_note")}</p>
          </div>
        </div>
        <form onSubmit={filter} className="toolbar console-filter-bar">
          <div className="filters console-filter-pills console-filter-full">
            {["all", "absence", "held", "directory"].map((key) => (
              <Link
                className={
                  "pill " + (props.filters.filter === key ? "current" : "")
                }
                key={key}
                href={query({ filter: key })}
                preserveScroll
              >
                {t("console_followup.filters." + key)}
              </Link>
            ))}
          </div>
          <div className="field console-filter-search">
            <label htmlFor="followup-search">
              {t("console_followup.filters.search")}
            </label>
            <input
              className="console-control"
              id="followup-search"
              type="search"
              value={filterForm.data.search}
              onChange={(event) =>
                filterForm.setData("search", event.target.value)
              }
            />
          </div>
          <div className="field">
            <label htmlFor="followup-track">
              {t("console_followup.filters.track")}
            </label>
            <select
              className="console-control"
              id="followup-track"
              value={filterForm.data.track}
              onChange={(event) =>
                filterForm.setData("track", event.target.value)
              }
            >
              {["all", "quran", "courses"].map((key) => (
                <option value={key} key={key}>
                  {t("console_followup.tracks." + key)}
                </option>
              ))}
            </select>
          </div>
          <button className="console-button" disabled={filterForm.processing}>
            {t("console.search")}
          </button>
        </form>
        <div className="console-table-wrap">
          <table className="console-table followup-table">
            <thead>
              <tr>
                {[
                  "student",
                  "absence",
                  "status",
                  "next",
                  "return_date",
                  "open",
                ].map((key) => (
                  <th key={key}>{t("console_followup.columns." + key)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {props.cases.data.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link
                      className="inline-link"
                      href={query({ enrollment: row.id })}
                      preserveScroll
                    >
                      {row.name}
                    </Link>
                    <small>
                      <bdi>{row.code}</bdi> ·{" "}
                      {row.tracks
                        .map((track) => t("console_followup.tracks." + track))
                        .join(" · ")}
                    </small>
                    <small>{row.program}</small>
                  </td>
                  <td>
                    {formatNumber(row.absences)}
                    <small>
                      {t("console_followup.violations")} ·{" "}
                      {formatNumber(row.violations)}
                    </small>
                  </td>
                  <td>
                    <span
                      className={
                        "console-status " + (row.held ? "is-warning" : "")
                      }
                    >
                      {row.status_label}
                    </span>
                  </td>
                  <td>{row.next}</td>
                  <td>
                    <bdi>{row.return_date || "—"}</bdi>
                  </td>
                  <td>
                    <Link
                      className="inline-link"
                      href={query({ enrollment: row.id })}
                      preserveScroll
                    >
                      {t("console_followup.columns.open")}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!props.cases.data.length && (
          <div className="console-empty">
            <h3>{t("console_followup.empty")}</h3>
            <p>{t("console_followup.empty_help")}</p>
          </div>
        )}
        <div className="panel-foot">
          <p>
            {t("console_followup.window")} ·{" "}
            {formatDate(props.window.from, props.timezone)}{" "}
            {t("console_followup.to")}{" "}
            {formatDate(props.window.until, props.timezone)}
            <br />
            {t("console_followup.case_note")}
          </p>
          <div className="console-actions">
            {props.cases.prev_page_url && (
              <Link className="console-button" href={props.cases.prev_page_url}>
                {t("console_followup.previous")}
              </Link>
            )}
            <bdi>
              {props.cases.current_page} / {props.cases.last_page}
            </bdi>
            {props.cases.next_page_url && (
              <Link className="console-button" href={props.cases.next_page_url}>
                {t("console_followup.next_page")}
              </Link>
            )}
          </div>
        </div>
      </section>
      {props.selected && (
        <FollowupDetail
          key={props.selected.id + props.selected.status}
          person={props.selected}
          closeUrl={query()}
          timezone={props.timezone}
          requiresAssessment={props.requiresAssessment}
        />
      )}
    </ConsoleLayout>
  );
}
function FollowupDetail({
  person,
  closeUrl,
  timezone,
  requiresAssessment,
}: {
  person: Selected;
  closeUrl: string;
  timezone: string;
  requiresAssessment: boolean;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const form = useForm({
    action: person.actions[0] || "",
    expected_status: person.status,
    context: "",
    note: "",
    return_date: person.return_date || "",
    assessment_id: "",
  });
  const close = () => router.get(closeUrl, {}, { preserveScroll: true });
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    return () => element?.close();
  }, []);
  const when = (at: string | null) =>
    at ? formatDate(at, timezone) + " · " + formatTime(at, timezone) : "—";
  const actor = (id?: string | null) =>
    id
      ? person.actors[id] || t("console_followup.unavailable")
      : t("console_followup.system");
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(person.submit_url, { preserveScroll: true });
  }
  const noteKey =
    form.data.action === "pause"
      ? "pause_note"
      : form.data.action === "freeze"
        ? "freeze_note"
        : form.data.action === "resume"
          ? "resume_note"
          : form.data.action === "request"
            ? "request_note"
            : "assessment_note";
  return (
    <dialog
      ref={dialog}
      className="detail-sheet console-followup-dialog"
      aria-labelledby="followup-title"
      aria-describedby="followup-description"
      onCancel={(event) => {
        event.preventDefault();
        close();
      }}
    >
      <div className="panel-head">
        <div>
          <h2 id="followup-title">{person.name}</h2>
          <p id="followup-description">{t("console_followup.details")}</p>
        </div>
        <button className="console-button" type="button" onClick={close}>
          {t("console_followup.close")}
        </button>
      </div>
      <div className="console-panel-body">
        <Link
          href={"/manage/students/" + person.student_id}
          className="inline-link"
        >
          {t("console_followup.profile")}
        </Link>
        <div className="detail-note">
          {person.program} · {person.status_label}
          {person.status === "paused" && person.return_date && (
            <p className="operation-field">
              {t("console_followup.return_date")} ·{" "}
              <bdi>{person.return_date}</bdi>
            </p>
          )}
          {person.frozen_reason && <p>{person.frozen_reason}</p>}
        </div>
        <section className="detail-block">
          <h3>{t("console_followup.attendance_title")}</h3>
          {person.attendance.length ? (
            person.attendance.map((row) => (
              <div className="definition" key={row.id}>
                <div>
                  <b>{row.title || t("console_followup.columns.student")}</b>
                  <p>{when(row.at)}</p>
                  <p>
                    {row.confirmed
                      ? t("console_followup.confirmed_by") +
                        " " +
                        actor(row.actor_id)
                      : t("console_followup.not_confirmed")}
                  </p>
                  {row.reason && <p>{row.reason}</p>}
                  {person.can_correct_attendance && (
                    <AttendanceCorrection
                      key={row.id + row.status}
                      person={person}
                      row={row}
                    />
                  )}
                </div>
                <span className="console-status">{row.label}</span>
              </div>
            ))
          ) : (
            <p>{t("console_followup.no_attendance")}</p>
          )}
          <p className="detail-note">{t("console_followup.attendance_note")}</p>
        </section>
        <section className="detail-block">
          <h3>{t("console_followup.history")}</h3>
          {person.history.length ? (
            person.history.map((entry) => (
              <div className="definition" key={entry.id}>
                <div>
                  <p>{entry.reason}</p>
                  <small>
                    {actor(entry.actor_id)} · {when(entry.at)}
                  </small>
                </div>
              </div>
            ))
          ) : (
            <p>{t("console_followup.no_history")}</p>
          )}
        </section>
        <section className="detail-block">
          <h3>{t("console_followup.discipline_title")}</h3>
          {person.discipline.violations.map((entry) => (
            <div className="definition" key={entry.id}>
              <div>
                <b>{entry.label}</b>
                <p>{when(entry.at)}</p>
                {entry.reason && <p>{entry.reason}</p>}
              </div>
              <span className="console-status">
                {t(
                  "console_followup." +
                    (entry.waived_at
                      ? "waived"
                      : entry.countable
                        ? "countable"
                        : "not_countable"),
                )}
              </span>
            </div>
          ))}
          {person.discipline.actions.map((entry) => (
            <div className="definition" key={entry.id}>
              <div>
                <b>{entry.label}</b>
                <p>
                  {actor(entry.actor_id)} · {when(entry.at)}
                </p>
                {entry.reason && <p>{entry.reason}</p>}
              </div>
            </div>
          ))}
        </section>
        {!!person.discipline.requests.length && (
          <section className="detail-block">
            <h3>{t("console_followup.requests")}</h3>
            {person.discipline.requests.map((entry) => (
              <div className="definition" key={entry.id}>
                <div>
                  <b>
                    {entry.label} · {t("console_followup.request_number")}{" "}
                    {formatNumber(entry.attempt)}
                  </b>
                  <p>{entry.statement}</p>
                  {entry.decision_note && <p>{entry.decision_note}</p>}
                  <small>
                    {actor(entry.actor_id)} · {when(entry.at)}
                  </small>
                </div>
              </div>
            ))}
          </section>
        )}
        {person.open_request && (
          <section className="detail-block">
            <h3>{t("console_followup.assessment")}</h3>
            {person.assessment_options.length ? (
              person.assessment_options.map((assessment) => (
                <div className="definition" key={assessment.id}>
                  <div>
                    <b>{assessment.title}</b>
                    <p>
                      {assessment.score === null
                        ? "—"
                        : formatNumber(assessment.score)}{" "}
                      / {formatNumber(assessment.total)} ·{" "}
                      {t(
                        "console_followup." +
                          (assessment.passed ? "passed" : "not_passed"),
                      )}
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <p>{t("console_followup.no_assessment")}</p>
            )}
          </section>
        )}
        {person.actions.length ? (
          <form className="detail-block" onSubmit={submit}>
            <h3>{t("console_followup.action_title")}</h3>
            <div className="field-grid">
              <div className="field span2">
                <label htmlFor="followup-action">
                  {t("console_followup.action_type")}
                </label>
                <select
                  className="console-control"
                  id="followup-action"
                  value={form.data.action}
                  onChange={(event) =>
                    form.setData("action", event.target.value)
                  }
                >
                  {person.actions.map((action) => (
                    <option key={action} value={action}>
                      {t("console_followup.actions." + action)}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field span2">
                <label htmlFor="followup-context">
                  {t("console_followup.context")}
                </label>
                <select
                  className="console-control"
                  id="followup-context"
                  required
                  value={form.data.context}
                  onChange={(event) =>
                    form.setData("context", event.target.value)
                  }
                >
                  <option value="">{t("console_followup.choose")}</option>
                  {contextKeys.map((context) => (
                    <option key={context} value={context}>
                      {t("console_followup.contexts." + context)}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field span2">
                <label htmlFor="followup-note">
                  {t("console_followup.note")}
                </label>
                <textarea
                  className="console-control"
                  id="followup-note"
                  value={form.data.note}
                  onChange={(event) => form.setData("note", event.target.value)}
                  maxLength={2000}
                />
              </div>
              {form.data.action === "pause" && (
                <div className="field span2">
                  <label htmlFor="followup-return">
                    {t("console_followup.return_date")}
                  </label>
                  <input
                    className="console-control"
                    type="date"
                    required
                    id="followup-return"
                    value={form.data.return_date}
                    onChange={(event) =>
                      form.setData("return_date", event.target.value)
                    }
                  />
                </div>
              )}
              {form.data.action === "approve" && requiresAssessment && (
                <div className="field span2">
                  <label htmlFor="followup-assessment">
                    {t("console_followup.assessment")}
                  </label>
                  <select
                    className="console-control"
                    required
                    id="followup-assessment"
                    value={form.data.assessment_id}
                    onChange={(event) =>
                      form.setData("assessment_id", event.target.value)
                    }
                  >
                    <option value="">{t("console_followup.choose")}</option>
                    {person.assessment_options
                      .filter((row) => row.passed)
                      .map((row) => (
                        <option key={row.id} value={row.id}>
                          {row.title} · {row.score} / {row.total}
                        </option>
                      ))}
                  </select>
                </div>
              )}
            </div>
            <p className="detail-note">{t("console_followup." + noteKey)}</p>
            {Object.keys(form.errors).length > 0 && (
              <div role="alert" className="console-feedback is-error">
                {Object.values(form.errors).join(" · ")}
              </div>
            )}
            <button
              className="console-button primary"
              disabled={form.processing || !form.data.context}
            >
              {t("console_followup." + (form.processing ? "saving" : "save"))}
            </button>
          </form>
        ) : (
          <p className="detail-note">{t("console_followup.read_only")}</p>
        )}
        <p className="detail-note">{t("console_followup.notes_scope")}</p>
      </div>
    </dialog>
  );
}

function AttendanceCorrection({
  person,
  row,
}: {
  person: Selected;
  row: AttendanceEntry;
}) {
  const t = useI18n();
  const [open, setOpen] = useState(false);
  const form = useForm({
    status: row.status === "excused" ? "present" : "excused",
    expected_status: row.status,
    context: "",
    note: "",
  });
  const needsWaiver = person.discipline.violations.some(
    (violation) =>
      violation.session_id === row.session_id &&
      !violation.waived_at &&
      ["no_show", "unexcused_absence"].includes(violation.type),
  );
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/manage/followup/" + person.id + "/attendance/" + row.id, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
    });
  }
  if (!open)
    return (
      <>
        <button
          type="button"
          className="inline-link"
          disabled={needsWaiver && !person.can_waive}
          onClick={() => setOpen(true)}
        >
          {t("console_followup.correct_attendance")}
        </button>
        {needsWaiver && !person.can_waive && (
          <p>{t("console_followup.waiver_permission")}</p>
        )}
      </>
    );
  return (
    <form className="detail-block" onSubmit={submit}>
      <div className="field-grid">
        <div className="field span2">
          <label htmlFor={"correction-status-" + row.id}>
            {t("console_followup.correction_status")}
          </label>
          <select
            className="console-control"
            id={"correction-status-" + row.id}
            value={form.data.status}
            onChange={(event) => form.setData("status", event.target.value)}
          >
            {person.attendance_statuses
              .filter((status) => status.value !== row.status)
              .map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
          </select>
        </div>
        <div className="field span2">
          <label htmlFor={"correction-context-" + row.id}>
            {t("console_followup.correction_context")}
          </label>
          <select
            required
            className="console-control"
            id={"correction-context-" + row.id}
            value={form.data.context}
            onChange={(event) => form.setData("context", event.target.value)}
          >
            <option value="">{t("console_followup.choose")}</option>
            {[
              "accepted_excuse",
              "recording_correction",
              "technical_issue",
              "not_held",
            ].map((context) => (
              <option key={context} value={context}>
                {t("console_followup.correction_contexts." + context)}
              </option>
            ))}
          </select>
        </div>
        <div className="field span2">
          <label htmlFor={"correction-note-" + row.id}>
            {t("console_followup.note")}
          </label>
          <textarea
            className="console-control"
            id={"correction-note-" + row.id}
            maxLength={2000}
            value={form.data.note}
            onChange={(event) => form.setData("note", event.target.value)}
          />
        </div>
      </div>
      <p className="detail-note">
        {t("console_followup.correction_finance")}{" "}
        {t("console_followup.correction_scope")}
      </p>
      {Object.keys(form.errors).length > 0 && (
        <div role="alert" className="console-feedback is-error">
          {Object.values(form.errors).join(" · ")}
        </div>
      )}
      <div className="console-actions">
        <button
          className="console-button primary"
          disabled={form.processing || !form.data.context}
        >
          {t("console_followup.correction_save")}
        </button>
        <button
          type="button"
          className="console-button"
          onClick={() => setOpen(false)}
        >
          {t("console_followup.cancel")}
        </button>
      </div>
    </form>
  );
}
