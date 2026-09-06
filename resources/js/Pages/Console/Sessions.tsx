import { Head, Link, router } from "@inertiajs/react";
import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import {
  ArrowLeft,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Plus,
  X,
} from "lucide-react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import "../../../css/console-sessions.css";

type Session = {
  id: string;
  is_quran: boolean;
  students: { id: string; name: string }[];
  title: string;
  course_id: string;
  course: string;
  group_id: string;
  group: string;
  teacher_id: string;
  teacher: string;
  original_teacher: string | null;
  date: string;
  start: string;
  end: string;
  end_date: string;
  status: string;
  status_label: string;
  actual_start: string | null;
  actual_end: string | null;
};
type Schedule = {
  id: string;
  group_id: string;
  group: string;
  course_id: string;
  course: string;
  teacher: string;
  weekdays: number[];
  start_time: string;
  timezone: string;
  duration_minutes: number;
  interval_weeks: number;
  starts_on: string;
  ends_on: string | null;
};
type Option = { id: string; name: string };
type Filters = {
  view: "day" | "week" | "list";
  date: string;
  group: string | null;
  teacher: string | null;
  course: string | null;
  status: string | null;
  history: boolean;
  timezone: string;
  page?: number;
};
type Props = {
  filters: Filters;
  school: { timezone: string; week_starts_at: number };
  timezones: string[];
  from: string;
  until: string;
  previous: string;
  next: string;
  today: string;
  days: { date: string; weekday: number; today: boolean }[];
  sessions: Session[];
  limited: boolean;
  list: {
    data: Session[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  schedules: Schedule[];
  groups: Option[];
  courses: Option[];
  teachers: Option[];
  statuses: Option[];
  can: {
    manage: boolean;
    schedules: boolean;
    group: boolean;
    teacher: boolean;
    quran: boolean;
  };
};

export default function Sessions(props: Props) {
  const {
    filters,
    school,
    timezones,
    from,
    until,
    previous,
    next,
    today,
    days,
    sessions,
    limited,
    list,
    schedules,
    groups,
    courses,
    teachers,
    statuses,
    can,
  } = props;
  const t = useI18n();
  const [draft, setDraft] = useState(filters);
  const [selected, setSelected] = useState<Session | null>(null);
  const href = (changes: Partial<Filters> = {}) => {
    const query = { ...filters, ...changes };
    delete query.page;
    return (
      "/manage/sessions?" +
      new URLSearchParams(
        Object.entries(query)
          .filter(([, value]) => value !== null && value !== "")
          .map(([key, value]) => [
            key,
            typeof value === "boolean" ? (value ? "1" : "0") : String(value),
          ]),
      )
    );
  };
  const apply = (event: FormEvent) => {
    event.preventDefault();
    router.get(
      "/manage/sessions",
      { ...draft, history: draft.history ? "1" : "0", page: undefined },
      { preserveScroll: true },
    );
  };
  const selection = (
    key: "group" | "course" | "teacher" | "status",
    choices: Option[],
  ) => (
    <label className="field">
      <span>{t("console_sessions." + key)}</span>
      <select
        className="console-control"
        value={draft[key] ?? ""}
        onChange={(event) =>
          setDraft((value) => ({ ...value, [key]: event.target.value || null }))
        }
      >
        <option value="">{t("console_sessions.all")}</option>
        {choices.map((choice) => (
          <option key={choice.id} value={choice.id}>
            {choice.name}
          </option>
        ))}
      </select>
    </label>
  );
  const scheduleUrl =
    "/manage/schedules/create?" +
    new URLSearchParams({
      ...(filters.group ? { group: filters.group } : {}),
      ...(filters.course ? { course: filters.course } : {}),
    });
  return (
    <ConsoleLayout
      title={t("console_sessions.title")}
      section="during"
      description={t("console_sessions.description")}
      actions={
        can.manage ? (
          <Link className="console-button primary" href={scheduleUrl}>
            <Plus size={16} />
            {t("console_sessions.add")}
          </Link>
        ) : undefined
      }
    >
      <Head title={t("console_sessions.title")} />
      <div className="sessions-workspace">
        <section className="panel">
          <div className="panel-head sessions-heading">
            <div>
              <h2 dir="ltr">
                {from}
                {from !== until && " — " + until}
              </h2>
              <small>
                {t("console_sessions.timezone")}: {filters.timezone} ·{" "}
                {t("console_sessions.school_timezone")}: {school.timezone}
              </small>
            </div>
            <div className="sessions-date-tools">
              <Link
                className="console-button"
                aria-label={t("console_sessions.previous")}
                href={href({ date: previous })}
              >
                <ChevronRight size={16} />
              </Link>
              <Link className="console-button" href={href({ date: today })}>
                {t("console_sessions.today")}
              </Link>
              <Link
                className="console-button"
                aria-label={t("console_sessions.next")}
                href={href({ date: next })}
              >
                <ChevronLeft size={16} />
              </Link>
              <input
                className="console-control"
                aria-label={t("console_sessions.time")}
                type="date"
                value={filters.date}
                onChange={(event) => {
                  if (event.target.value)
                    router.get(href({ date: event.target.value }));
                }}
              />
            </div>
            <nav
              className="sessions-view-tabs"
              aria-label={t("console_sessions.title")}
            >
              {(["day", "week", "list"] as const).map((view) => (
                <Link
                  href={href({ view })}
                  aria-current={view === filters.view ? "page" : undefined}
                  key={view}
                >
                  {t("console_sessions." + view)}
                </Link>
              ))}
            </nav>
          </div>
          <form
            className="console-filter-bar console-filter-mobile-pairs"
            onSubmit={apply}
          >
            {selection("group", groups)}
            {selection("course", courses)}
            {selection("teacher", teachers)}
            {selection("status", statuses)}
            <label className="field console-filter-mobile-full">
              <span>{t("console_sessions.timezone")}</span>
              <select
                className="console-control"
                value={draft.timezone}
                onChange={(event) =>
                  setDraft((value) => ({
                    ...value,
                    timezone: event.target.value,
                  }))
                }
              >
                {timezones.map((timezone) => (
                  <option key={timezone}>{timezone}</option>
                ))}
              </select>
            </label>
            <div className="console-filter-actions">
              <button className="console-button primary">
                {t("console_sessions.filter")}
              </button>
              <Link
                className="console-button"
                href={"/manage/sessions?view=" + filters.view}
              >
                {t("console_sessions.clear")}
              </Link>
            </div>
          </form>
          <label className="sessions-history">
            <input
              type="checkbox"
              checked={!!filters.history}
              onChange={(event) =>
                router.get(href({ history: event.target.checked }))
              }
            />
            {t("console_sessions.history")}
          </label>
          {limited && (
            <div className="sessions-alert" role="status">
              {t("console_sessions.limited")}
            </div>
          )}
          {filters.view === "list" ? (
            <div className="sessions-table-scroll">
              <table className="console-table sessions-table">
                <thead>
                  <tr>
                    {["time", "session", "group", "teacher", "status"].map(
                      (key) => (
                        <th key={key}>{t("console_sessions." + key)}</th>
                      ),
                    )}
                  </tr>
                </thead>
                <tbody>
                  {list.data.map((session) => (
                    <tr key={session.id}>
                      <td>
                        <span dir="ltr">
                          {session.date} · {session.start}–{session.end}
                        </span>
                      </td>
                      <td>
                        <button
                          className="sessions-link"
                          onClick={() => setSelected(session)}
                        >
                          {session.title || session.course}
                        </button>
                      </td>
                      <td>
                        {session.students.length
                          ? session.students
                              .map((student) => student.name)
                              .join("، ")
                          : session.group}
                      </td>
                      <td>
                        {session.teacher}
                        {session.original_teacher && (
                          <small>
                            {t("console_sessions.original_teacher")}:{" "}
                            {session.original_teacher}
                          </small>
                        )}
                      </td>
                      <td>
                        <Status session={session} />
                      </td>
                    </tr>
                  ))}
                  {!list.data.length && (
                    <tr>
                      <td colSpan={5} className="sessions-empty">
                        {t("console_sessions.empty")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="sessions-calendar-scroll">
              <div className={"sessions-calendar " + filters.view}>
                {days.map((day) => (
                  <section
                    key={day.date}
                    className={"sessions-day" + (day.today ? " is-today" : "")}
                  >
                    <header>
                      <strong>
                        {t("console_sessions.weekdays." + day.weekday)}
                      </strong>
                      <small dir="ltr">{day.date}</small>
                    </header>
                    {sessions
                      .filter((session) => session.date === day.date)
                      .map((session) => (
                        <button
                          key={session.id}
                          className="sessions-event"
                          data-status={session.status}
                          onClick={() => setSelected(session)}
                        >
                          <b>
                            {session.start}–{session.end}
                          </b>
                          <strong>{session.title || session.course}</strong>
                          <small>
                            {session.students.length
                              ? session.students
                                  .map((student) => student.name)
                                  .join("، ")
                              : session.group}
                          </small>
                          <small>{session.teacher}</small>
                          <Status session={session} />
                        </button>
                      ))}
                    {!sessions.some((session) => session.date === day.date) && (
                      <p className="sessions-empty">
                        {t("console_sessions.empty_day")}
                      </p>
                    )}
                  </section>
                ))}
              </div>
            </div>
          )}
          <footer className="panel-foot">
            <span>
              {t("console_sessions." + (limited ? "shown_count" : "count"))}:{" "}
              {sessions.length} · {t("console_sessions.completed")}:{" "}
              {
                sessions.filter((session) => session.status === "completed")
                  .length
              }{" "}
              · {t("console_sessions.review")}:{" "}
              {
                sessions.filter(
                  (session) => session.status === "awaiting_review",
                ).length
              }
            </span>
            {filters.view === "list" && (
              <div className="sessions-date-tools">
                {list.prev_page_url && (
                  <Link className="console-button" href={list.prev_page_url}>
                    {t("console_sessions.previous")}
                  </Link>
                )}
                <span>
                  {list.current_page} / {list.last_page}
                </span>
                {list.next_page_url && (
                  <Link className="console-button" href={list.next_page_url}>
                    {t("console_sessions.next")}
                  </Link>
                )}
              </div>
            )}
          </footer>
        </section>
        {can.schedules && (
          <section className="panel">
            <div className="panel-head sessions-heading">
              <div>
                <h2>{t("console_sessions.schedules")}</h2>
                <small>{t("console_sessions.schedule_description")}</small>
              </div>
              {can.manage && (
                <Link className="console-button" href={scheduleUrl}>
                  <Plus size={16} />
                  {t("console_sessions.add")}
                </Link>
              )}
            </div>
            <div className="sessions-table-scroll">
              <table className="console-table sessions-table">
                <thead>
                  <tr>
                    {[
                      "group",
                      "course",
                      "teacher",
                      "time",
                      "period",
                      "edit",
                    ].map((key) => (
                      <th key={key}>{t("console_sessions." + key)}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {schedules.map((schedule) => (
                    <tr key={schedule.id}>
                      <td>
                        {can.group ? (
                          <Link
                            href={"/manage/groups/" + schedule.group_id}
                            className="sessions-link"
                          >
                            {schedule.group}
                          </Link>
                        ) : (
                          schedule.group
                        )}
                      </td>
                      <td>{schedule.course}</td>
                      <td>{schedule.teacher}</td>
                      <td>
                        {schedule.weekdays
                          .map((day) => t("console_sessions.weekdays." + day))
                          .join("، ")}
                        <small>
                          {schedule.start_time} · {schedule.duration_minutes}{" "}
                          {t("console_sessions.minutes")}
                        </small>
                        <small>
                          {t("console_sessions.weekly").replace(
                            ":count",
                            String(schedule.interval_weeks),
                          )}{" "}
                          · {schedule.timezone}
                        </small>
                      </td>
                      <td>
                        <span dir="ltr">{schedule.starts_on}</span>
                        <small>
                          {schedule.ends_on || t("console_sessions.no_end")}
                        </small>
                      </td>
                      <td>
                        {can.manage && (
                          <Link
                            href={"/manage/schedules/" + schedule.id + "/edit"}
                            className="sessions-link"
                          >
                            {t("console_sessions.edit")}
                            <ArrowLeft size={13} />
                          </Link>
                        )}
                      </td>
                    </tr>
                  ))}
                  {!schedules.length && (
                    <tr>
                      <td colSpan={6} className="sessions-empty">
                        {t("console_sessions.no_schedules")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        )}
      </div>
      {selected && (
        <SessionDetails
          session={selected}
          timezone={filters.timezone}
          can={can}
          onClose={() => setSelected(null)}
        />
      )}
    </ConsoleLayout>
  );
}
function Status({ session }: { session: Session }) {
  return (
    <span className="sessions-status" data-status={session.status}>
      {session.status_label}
    </span>
  );
}
function SessionDetails({
  session,
  timezone,
  can,
  onClose,
}: {
  session: Session;
  timezone: string;
  can: Props["can"];
  onClose: () => void;
}) {
  const t = useI18n();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  useEffect(() => {
    const dialog = ref.current;
    dialog?.showModal();
    return () => dialog?.close();
  }, []);
  const details = [
    [t("console_sessions.course"), session.course],
    ...(session.students.length
      ? [
          [
            t("console_sessions.student"),
            session.students.map((student) => student.name).join("، "),
          ],
        ]
      : []),
    [t("console_sessions.group"), session.group],
    [t("console_sessions.teacher"), session.teacher],
    ...(session.original_teacher
      ? [[t("console_sessions.original_teacher"), session.original_teacher]]
      : []),
    [
      t("console_sessions.time"),
      session.date +
        " · " +
        session.start +
        " — " +
        (session.end_date !== session.date ? session.end_date + " " : "") +
        session.end,
    ],
    [t("console_sessions.timezone"), timezone],
    ...(session.actual_start
      ? [
          [
            t("console_sessions.actual_time"),
            session.actual_start +
              (session.actual_end ? " — " + session.actual_end : ""),
          ],
        ]
      : []),
  ];
  return (
    <dialog
      className="sessions-sheet"
      ref={ref}
      aria-labelledby={titleId}
      onCancel={onClose}
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <header>
        <h2 id={titleId}>{session.title || t("console_sessions.details")}</h2>
        <button
          className="console-button"
          aria-label={t("console_sessions.close")}
          onClick={onClose}
        >
          <X size={18} />
        </button>
      </header>
      <div className="sessions-sheet-body">
        <Status session={session} />
        <dl>
          {details.map(([label, value]) => (
            <div className="sessions-definition" key={label}>
              <dt>{label}</dt>
              <dd>{value}</dd>
            </div>
          ))}
        </dl>
        <div className="sessions-sheet-actions">
          {session.students.map((student) => (
            <Link
              className="console-button"
              key={student.id}
              href={"/manage/students/" + student.id}
            >
              {t("console_sessions.open_student")}: {student.name}
            </Link>
          ))}
          {can.group && session.group_id && (
            <Link
              className="console-button"
              href={"/manage/groups/" + session.group_id}
            >
              {t("console_sessions.open_group")}
            </Link>
          )}
          {can.teacher && (
            <Link
              className="console-button"
              href={"/manage/teachers/" + session.teacher_id}
            >
              {t("console_sessions.open_teacher")}
            </Link>
          )}
          {can.quran && session.is_quran && (
            <Link className="console-button" href="/manage/quran?tab=schedule">
              {t("console_sessions.open_quran")}
            </Link>
          )}
          {can.manage && session.group_id && (
            <Link
              className="console-button"
              href={
                "/manage/sessions?" +
                new URLSearchParams({
                  group: session.group_id,
                  course: session.course_id,
                  date: session.date,
                })
              }
            >
              <CalendarDays size={16} />
              {t("console_sessions.schedules")}
            </Link>
          )}
        </div>
      </div>
    </dialog>
  );
}
