import { Head, Link, router, useRemember } from "@inertiajs/react";
import axios from "axios";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import QuranEditor, {
  QuranDays,
  QuranTimes,
  QuranSheet,
  requestError,
} from "./QuranEditor";
import type { Placement, QuranProps, Student, Tab, Page } from "./QuranTypes";
import "../../../css/console-quran.css";

export default function Quran(props: QuranProps) {
  const {
    students,
    directory,
    sessions,
    teachers,
    teacherRows,
    counts,
    filters,
    can,
    defaults,
    policy,
    course,
    registration,
  } = props;
  const t = useI18n();
  const [search, setSearch] = useState(filters.search);
  const [selectedStudent, setSelectedStudent] = useState<string | null>(
    filters.tab === "students" ? filters.student : null,
  );
  const [selectedTeacher, setSelectedTeacher] = useState<string | null>(null);
  const [editing, setEditing] = useState(false);
  const [drafts, setDrafts] = useRemember<Record<string, Placement>>(
    {},
    "quran-placement-drafts",
  );
  const [busy, setBusy] = useState<Record<string, boolean>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [feedback, setFeedback] = useState("");
  const [copied, setCopied] = useState("");
  const [saved, setSaved] = useState<
    Record<string, NonNullable<Student["schedule"]>>
  >({});
  const studentRow = (student: Student): Student =>
    saved[student.id]
      ? {
          ...student,
          schedule: saved[student.id] ?? student.schedule,
          teacher_name:
            teachers[saved[student.id]?.staff_profile_id ?? ""] ??
            student.teacher_name,
        }
      : student;
  const draft = (student: Student): Placement =>
    drafts[student.id] ??
    student.schedule ?? {
      staff_profile_id: student.pending_teacher_ids?.length === 1 ? (student.pending_teacher_ids[0] ?? "") : "",
      weekly_slots: [],
      duration_minutes: defaults.duration_minutes,
      interval_weeks: defaults.interval_weeks,
      timezone: defaults.timezone,
      starts_on: defaults.starts_on,
      ends_on: defaults.ends_on,
    };
  const setDraft = (student: Student, value: Placement) =>
    setDrafts((old) => ({ ...old, [student.id]: value }));
  const selected = directory.find((student) => student.id === selectedStudent);
  const current = selected ? studentRow(selected) : null;
  const teacher = teacherRows.find((item) => item.id === selectedTeacher);
  const studentById = (id: string) => directory.find((item) => item.id === id);
  const teacherName = (id: string) =>
    teacherRows.find((item) => item.id === id)?.name ??
    t("console_quran.teacher_unavailable");
  const href = (changes: Record<string, string | null>) =>
    "/manage/quran?" +
    new URLSearchParams(
      Object.fromEntries(
        Object.entries({ ...filters, ...changes, page: null }).filter(
          (entry): entry is [string, string] =>
            typeof entry[1] === "string" && entry[1] !== "",
        ),
      ),
    ).toString();
  const openStudent = (id: string, edit = false) => {
    setSelectedStudent(id);
    setEditing(edit);
  };
  const closeStudent = () => {
    setSelectedStudent(null);
    setEditing(false);
  };
  const save = async (student: Student) => {
    const value = draft(student);
    setBusy((old) => ({ ...old, [student.id]: true }));
    setErrors((old) => ({ ...old, [student.id]: "" }));
    setFeedback("");
    try {
      const url =
        "/manage/quran/" +
        student.id +
        (student.schedule ? "/schedules/" + student.schedule.id : "");
      const response = await axios.request<{
        message: string;
        schedule: NonNullable<Student["schedule"]>;
      }>({
        method: student.schedule ? "patch" : "post",
        url,
        data: {
          staff_profile_id: value.staff_profile_id,
          ...(!student.schedule && student.application_id
            ? { application_id: student.application_id }
            : {}),
          weekly_slots: value.weekly_slots,
          duration_minutes: value.duration_minutes,
          interval_weeks: value.interval_weeks,
          timezone: value.timezone,
          starts_on: value.starts_on,
          ends_on: value.ends_on || null,
        },
      });
      setSaved((old) => ({ ...old, [student.id]: response.data.schedule }));
      setDraft(student, response.data.schedule);
      setFeedback(response.data.message);
      setEditing(false);
      router.reload({
        only: ["students", "directory", "teacherRows", "counts", "sessions"],
      });
    } catch (error) {
      setErrors((old) => ({
        ...old,
        [student.id]: requestError(error, t("console_quran.failed")),
      }));
    } finally {
      setBusy((old) => ({ ...old, [student.id]: false }));
    }
  };
  const submitSearch = (event: FormEvent) => {
    event.preventDefault();
    router.get(
      href({ search }),
      {},
      { preserveState: false, preserveScroll: true },
    );
  };
  const followup = (student?: Student) =>
    "/manage/followup?track=quran" +
    (student?.enrollments[0] ? "&enrollment=" + student.enrollments[0].id : "");
  const displaySlots = (student: Student) =>
    student.schedule ? (
      <div className="quran-saved-times">
        {student.schedule.weekly_slots.map((slot) => (
          <span key={slot.weekday}>
            {t("console_quran.days." + slot.weekday)}{" "}
            <bdi>{slot.start_time}</bdi>
          </span>
        ))}
        <small>
          {student.schedule.duration_minutes} {t("console_quran.minute")} ·{" "}
          <bdi>{student.schedule.timezone}</bdi>
        </small>
      </div>
    ) : (
      <span className="cell-sub">{t("console_quran.pending")}</span>
    );
  const attendance = (student: Student) =>
    can.attendance && student.insight?.recorded ? (
      <span>
        {student.insight.attended}/{student.insight.recorded}
        <small className="cell-sub">
          {t("console_quran.attendance_recorded")}
        </small>
      </span>
    ) : (
      <span className="cell-sub">
        {t(
          can.attendance
            ? "console_quran.no_attendance"
            : "console_quran.permission_needed",
        )}
      </span>
    );
  const issue = (student: Student) => (
    <span className={"status " + (student.needs_followup ? "amber" : "slate")}>
      {student.held
        ? t("console_quran.held")
        : student.insight?.absences
          ? t("console_quran.absences_count").replace(
              ":count",
              String(student.insight.absences),
            )
          : t("console_quran.no_flag")}
    </span>
  );
  const pagination = (page: Page<unknown>) => (
    <footer className="panel-foot quran-pagination">
      <span>
        {page.total} {t("console_quran.result")} · {t("console_quran.page")}{" "}
        {page.current_page}/{page.last_page}
      </span>
      <div className="actions">
        {page.prev_page_url && (
          <Link
            className="console-button"
            href={page.prev_page_url}
            preserveScroll
          >
            {t("console_quran.previous")}
          </Link>
        )}
        {page.next_page_url && (
          <Link
            className="console-button"
            href={page.next_page_url}
            preserveScroll
          >
            {t("console_quran.next")}
          </Link>
        )}
      </div>
    </footer>
  );
  const tabLabel = (tab: Tab) =>
    t("console_quran.tabs." + tab + "." + filters.phase);
  const tabs: Tab[] = [
    "registration",
    "students",
    "teachers",
    "schedule",
    "policy",
  ];
  const toolbar = (
    <div className="console-filter-bar quran-toolbar">
      <div
        className="console-filter-pills console-filter-span-three"
        role="group"
        aria-label={t("console_quran.filter_status")}
      >
        {(["all", "pending", "assigned", "issues"] as const).map((status) => (
          <Link
            key={status}
            className={"pill " + (filters.status === status ? "current" : "")}
            href={href({ status, student: null })}
            aria-current={filters.status === status ? "page" : undefined}
            preserveScroll
          >
            {t("console_quran." + status)} <span>{counts[status]}</span>
          </Link>
        ))}
      </div>
      <form className="quran-search console-filter-search console-filter-span-three" onSubmit={submitSearch}>
        <input
          className="console-control"
          aria-label={t("console_quran.search")}
          placeholder={t("console_quran.search")}
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
        <button className="console-button" type="submit">
          {t("console_quran.search_action")}
        </button>
      </form>
    </div>
  );
  const periodPicker = (
    <label className="quran-period-picker console-filter-single">
      <span>{t("console_quran.period")}</span>
      <input
        className="console-control"
        type="month"
        dir="ltr"
        value={filters.period}
        onChange={(event) => {
          if (event.target.value)
            router.get(
              href({ period: event.target.value }),
              {},
              { preserveScroll: true },
            );
        }}
      />
    </label>
  );
  return (
    <ConsoleLayout
      title={t("console_quran.title")}
      description={t("console_quran.workspace_description")}
      section={filters.phase}
    >
      <Head title={t("console_quran.title")} />
      <div className="quran-workspace">
        <div className="quran-context">
          <span>
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              aria-hidden="true"
            >
              <path d="M12 5v16M12 5C9 3 5 3 2 4v15c3-1 7-1 10 2 3-3 7-3 10-2V4c-3-1-7-1-10 1Z" />
            </svg>
            <b>{t("console_quran.context")}</b>
          </span>
          <Link className="inline-link" href={href({ tab: "policy" })}>
            {t("console_quran.policy_link")}
          </Link>
        </div>
        <div className="quran-operation-links">
          {can.followup && (
            <Link className="inline-link" href={followup()}>
              {t("console_quran.absence_hold")}
            </Link>
          )}
          {can.dues && (
            <Link
              className="inline-link"
              href="/manage/teacher-dues?track=quran"
            >
              {t("console_quran.teacher_dues")}
            </Link>
          )}
        </div>
        <div className="stats quran-stats">
          {(["all", "assigned", "pending", "issues"] as const).map((key) => (
            <Link
              href={href({
                tab: "students",
                status: key,
                student: null,
                teacher: null,
              })}
              className="stat"
              key={key}
            >
              <span className="stat-label">
                {t("console_quran.stats." + key)}
              </span>
              <strong className="stat-value">{counts[key]}</strong>
            </Link>
          ))}
        </div>
        <section className="panel">
          <nav
            className="console-subtabs quran-tabs"
            aria-label={t("console_quran.workspace_navigation")}
          >
            {tabs.map((tab) => (
              <Link
                key={tab}
                href={href({ tab, student: null, teacher: null })}
                aria-current={filters.tab === tab ? "page" : undefined}
              >
                {tabLabel(tab)}
              </Link>
            ))}
          </nav>
          {feedback && (
            <p className="detail-note quran-feedback" role="status">
              {feedback}
            </p>
          )}
          {!course && (
            <p className="detail-note quran-feedback">
              {t("console_quran.course_missing")}
            </p>
          )}
          {filters.tab === "students" && (
            <>
              {toolbar}
              <div className="quran-table-context">
                <span>
                  {t("console_quran.timezone_reference")}{" "}
                  <bdi>{defaults.timezone}</bdi>
                </span>
                {filters.phase !== "before" && periodPicker}
                {filters.teacher && (
                  <Link className="inline-link" href={href({ teacher: null })}>
                    {t("console_quran.clear_teacher_filter")} ·{" "}
                    {teacherName(filters.teacher)}
                  </Link>
                )}
              </div>
              <div className="console-table-scroll">
                {filters.phase === "before" ? (
                  <table className="table console-table placement-table quran-placement">
                    <thead>
                      <tr>
                        {[
                          "student_need",
                          "primary_teacher",
                          "days_label",
                          "time_duration",
                          "placement_period",
                          "status_action",
                        ].map((key) => (
                          <th key={key} scope="col">
                            {t("console_quran." + key)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {students.data.map((original) => {
                        const student = studentRow(original);
                        const value = draft(student);
                        const editable =
                          can.place &&
                          !student.schedule &&
                          student.can_schedule;
                        return (
                          <tr key={student.id}>
                            <td>
                              <button
                                type="button"
                                className="cell-title inline-link"
                                onClick={() => openStudent(student.id)}
                              >
                                {student.name}
                              </button>
                              <span className="cell-sub">
                                <bdi>{student.code}</bdi>
                              </span>
                              <span className="cell-sub">
                                {t("console_quran.student_timezone")}{" "}
                                <bdi>{student.student_timezone}</bdi>
                              </span>
                            </td>
                            <td>
                              {editable ? (
                                <select
                                  className="console-control"
                                  aria-label={
                                    t("console_quran.primary_teacher") +
                                    " " +
                                    student.name
                                  }
                                  value={value.staff_profile_id}
                                  onChange={(event) =>
                                    setDraft(student, {
                                      ...value,
                                      staff_profile_id: event.target.value,
                                    })
                                  }
                                >
                                  <option value="">
                                    {t("console_quran.choose_teacher")}
                                  </option>
                                  {Object.entries(teachers).map(
                                    ([id, name]) => (
                                      <option key={id} value={id}>
                                        {name}
                                      </option>
                                    ),
                                  )}
                                </select>
                              ) : (
                                <strong>
                                  {student.teacher_name ??
                                    t("console_quran.not_assigned")}
                                </strong>
                              )}
                              {(value.staff_profile_id ||
                                student.schedule?.staff_profile_id) && (
                                <button
                                  type="button"
                                  className="inline-link quran-row-link"
                                  onClick={() =>
                                    setSelectedTeacher(
                                      value.staff_profile_id ||
                                        student.schedule?.staff_profile_id ||
                                        null,
                                    )
                                  }
                                >
                                  {t(
                                    "console_quran.teacher_students_availability",
                                  )}
                                </button>
                              )}
                            </td>
                            <td>
                              {editable ? (
                                <QuranDays
                                  value={value}
                                  onChange={(next) => setDraft(student, next)}
                                  disabled={busy[student.id]}
                                />
                              ) : (
                                <span>
                                  {student.schedule?.weekly_slots
                                    .map((slot) =>
                                      t("console_quran.days." + slot.weekday),
                                    )
                                    .join("، ") || "—"}
                                </span>
                              )}
                            </td>
                            <td>
                              {editable ? (
                                <>
                                  <QuranTimes
                                    compact
                                    student={student}
                                    value={value}
                                    onChange={(next) => setDraft(student, next)}
                                    defaults={defaults}
                                    disabled={busy[student.id]}
                                  />
                                  <select
                                    className="console-control quran-duration"
                                    aria-label={
                                      t("console_quran.duration") +
                                      " " +
                                      student.name
                                    }
                                    value={value.duration_minutes}
                                    onChange={(event) =>
                                      setDraft(student, {
                                        ...value,
                                        duration_minutes: Number(
                                          event.target.value,
                                        ),
                                      })
                                    }
                                  >
                                    {defaults.durations.map((duration) => (
                                      <option key={duration} value={duration}>
                                        {duration} {t("console_quran.minute")}
                                      </option>
                                    ))}
                                  </select>
                                </>
                              ) : (
                                displaySlots(student)
                              )}
                            </td>
                            <td>
                              <span className="cell-sub">
                                {t("console_quran.starts_on")}{" "}
                                <bdi>{value.starts_on}</bdi>
                              </span>
                              <span className="cell-sub">
                                {t("console_quran.ends_on")}{" "}
                                <bdi>
                                  {value.ends_on || t("console_quran.open_end")}
                                </bdi>
                              </span>
                              <small className="cell-sub">
                                {t("console_quran.repeat_every").replace(
                                  ":count",
                                  String(value.interval_weeks),
                                )}{" "}
                                · <bdi>{value.timezone}</bdi>
                              </small>
                              {can.place && (
                                <button
                                  className="inline-link quran-row-link"
                                  type="button"
                                  onClick={() => openStudent(student.id, true)}
                                >
                                  {t(
                                    "console_quran.period_repeat_availability",
                                  )}
                                </button>
                              )}
                            </td>
                            <td>
                              {student.held ? (
                                issue(student)
                              ) : (
                                <span
                                  className={
                                    "status " +
                                    (student.schedule ? "green" : "amber")
                                  }
                                >
                                  {t(
                                    student.schedule
                                      ? "console_quran.schedule_saved"
                                      : "console_quran.pending",
                                  )}
                                </span>
                              )}
                              {editable && (
                                <button
                                  type="button"
                                  className="console-button primary quran-approve"
                                  disabled={
                                    busy[student.id] ||
                                    !value.staff_profile_id ||
                                    !value.weekly_slots.length ||
                                    value.weekly_slots.some(
                                      (slot) => !slot.start_time,
                                    )
                                  }
                                  onClick={() => void save(student)}
                                >
                                  {t(
                                    busy[student.id]
                                      ? "console_quran.saving"
                                      : "console_quran.save",
                                  )}
                                </button>
                              )}
                              {student.schedule && can.place && (
                                <button
                                  type="button"
                                  className="inline-link quran-row-link"
                                  onClick={() => openStudent(student.id, true)}
                                >
                                  {t("console_quran.edit_schedule")}
                                </button>
                              )}
                              {errors[student.id] && (
                                <p className="quran-error" role="alert">
                                  {errors[student.id]}
                                </p>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                      {students.data.length === 0 && (
                        <tr>
                          <td colSpan={6} className="quran-empty">
                            {t("console_quran.empty")}
                            <p>{t("console_quran.empty_help")}</p>
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                ) : (
                  <table className="table console-table quran-followup-table">
                    <thead>
                      <tr>
                        {[
                          "student",
                          "primary_teacher",
                          filters.phase === "after"
                            ? "attendance"
                            : "next_session",
                          "learning_progress",
                          "followup",
                          "details",
                        ].map((key) => (
                          <th scope="col" key={key}>
                            {t("console_quran." + key)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {students.data.map((student) => (
                        <tr key={student.id}>
                          <td>
                            <button
                              type="button"
                              className="inline-link cell-title"
                              onClick={() => openStudent(student.id)}
                            >
                              {student.name}
                            </button>
                            <span className="cell-sub">{student.code}</span>
                          </td>
                          <td>
                            {student.teacher_name ??
                              t("console_quran.not_assigned")}
                            {displaySlots(student)}
                          </td>
                          <td>
                            {filters.phase === "after" ? (
                              attendance(student)
                            ) : (
                              <>
                                <bdi>
                                  {student.insight?.next_session?.local ?? "—"}
                                </bdi>
                                <span className="cell-sub">
                                  {t("console_quran.within_period")}
                                </span>
                              </>
                            )}
                          </td>
                          <td>
                            {student.insight?.progress?.topics ??
                              t(
                                can.progress
                                  ? "console_quran.no_progress"
                                  : "console_quran.permission_needed",
                              )}
                          </td>
                          <td>
                            {issue(student)}
                            {can.followup && (
                              <Link
                                className="inline-link quran-row-link"
                                href={followup(student)}
                              >
                                {t("console_quran.open_followup")}
                              </Link>
                            )}
                          </td>
                          <td>
                            <Link
                              className="inline-link"
                              href={"/manage/students/" + student.id}
                            >
                              {t("console_quran.full_profile")}
                            </Link>
                          </td>
                        </tr>
                      ))}
                      {students.data.length === 0 && (
                        <tr>
                          <td colSpan={6} className="quran-empty">
                            {t("console_quran.empty")}
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                )}
              </div>
              <p className="scroll-note">{t("console_quran.scroll_hint")}</p>
              {pagination(students)}
            </>
          )}
          {filters.tab === "teachers" && (
            <>
              <div className="toolbar">
                <div>
                  <h2>{tabLabel("teachers")}</h2>
                  <p className="cell-sub">
                    {t("console_quran.availability_help")}
                  </p>
                </div>
                {periodPicker}
              </div>
              <div className="console-table-scroll">
                <table className="table console-table quran-teachers">
                  <thead>
                    <tr>
                      {[
                        "teacher",
                        "current_students",
                        filters.phase === "after"
                          ? "sessions_period"
                          : "declared_availability",
                        "details",
                      ].map((key) => (
                        <th key={key} scope="col">
                          {t("console_quran." + key)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {teacherRows.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <button
                            type="button"
                            className="inline-link cell-title"
                            onClick={() => setSelectedTeacher(item.id)}
                          >
                            {item.name}
                          </button>
                          {!item.qualified && (
                            <span className="cell-sub">
                              {t("console_quran.historical_teacher")}
                            </span>
                          )}
                        </td>
                        <td>{item.student_ids.length}</td>
                        <td>
                          {filters.phase === "after" ? (
                            item.sessions
                          ) : (
                            <div className="quran-saved-times">
                              {item.availability.length ? (
                                item.availability.slice(0, 3).map((slot) => (
                                  <span key={slot.id}>
                                    {t("console_quran.days." + slot.weekday)}{" "}
                                    <bdi>
                                      {slot.start_time}–{slot.end_time}
                                    </bdi>
                                    <small>
                                      {t(
                                        "console_quran.approval." +
                                          slot.approval_status,
                                      )}{" "}
                                      · <bdi>{slot.timezone}</bdi>
                                    </small>
                                  </span>
                                ))
                              ) : (
                                <span className="cell-sub">
                                  {t("console_quran.no_declared_availability")}
                                </span>
                              )}
                              {item.availability.length > 3 && (
                                <button
                                  type="button"
                                  className="inline-link"
                                  onClick={() => setSelectedTeacher(item.id)}
                                >
                                  {t("console_quran.all_availability")}
                                </button>
                              )}
                            </div>
                          )}
                        </td>
                        <td>
                          <button
                            type="button"
                            className="inline-link"
                            onClick={() => setSelectedTeacher(item.id)}
                          >
                            {t("console_quran.teacher_students_availability")}
                          </button>
                          <Link
                            className="inline-link quran-row-link"
                            href={href({
                              tab: "students",
                              teacher: item.id,
                              status: "all",
                            })}
                          >
                            {t("console_quran.teacher_students")}
                          </Link>
                        </td>
                      </tr>
                    ))}
                    {teacherRows.length === 0 && (
                      <tr>
                        <td colSpan={4} className="quran-empty">
                          {t("console_quran.no_teachers")}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </>
          )}
          {filters.tab === "schedule" && (
            <>
              <div className="toolbar">
                <div>
                  <h2>{tabLabel("schedule")}</h2>
                  <p className="cell-sub">
                    {t("console_quran.timezone_reference")}{" "}
                    <bdi>{defaults.timezone}</bdi> ·{" "}
                    {t("console_quran.real_sessions")}
                  </p>
                </div>
                {periodPicker}
              </div>
              {can.sessions ? (
                <>
                  <label className="quran-history-toggle">
                    <input
                      type="checkbox"
                      checked={filters.history === "1"}
                      onChange={(event) =>
                        router.get(
                          href({ history: event.target.checked ? "1" : "0" }),
                          {},
                          { preserveScroll: true },
                        )
                      }
                    />
                    {t("console_quran.show_replaced")}
                  </label>
                  <div className="console-table-scroll">
                    <table className="table console-table quran-sessions">
                      <thead>
                        <tr>
                          {[
                            "student",
                            "teacher",
                            "session_time",
                            "duration",
                            "session_status",
                            "attendance",
                            "details",
                          ].map((key) => (
                            <th key={key} scope="col">
                              {t("console_quran." + key)}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {sessions.data.map((session) => (
                          <tr key={session.id + session.student_id}>
                            <td>
                              <button
                                type="button"
                                className="inline-link"
                                onClick={() => openStudent(session.student_id)}
                              >
                                {studentById(session.student_id)?.name ??
                                  t("console_quran.student")}
                              </button>
                            </td>
                            <td>
                              {teacherName(session.teacher_id)}
                              {session.original_teacher_id &&
                                session.original_teacher_id !==
                                  session.teacher_id && (
                                  <small className="cell-sub">
                                    {t("console_quran.substitute_for")}{" "}
                                    {teacherName(session.original_teacher_id)}
                                  </small>
                                )}
                            </td>
                            <td>
                              <bdi>{session.start}</bdi>
                              <span className="cell-sub">
                                <bdi>{session.end}</bdi>
                              </span>
                            </td>
                            <td>{session.duration}</td>
                            <td>
                              <span className="status slate">
                                {session.status_label}
                              </span>
                            </td>
                            <td>{session.attendance ?? "—"}</td>
                            <td>
                              <button
                                className="inline-link"
                                type="button"
                                onClick={() => openStudent(session.student_id)}
                              >
                                {t("console_quran.followup")}
                              </button>
                            </td>
                          </tr>
                        ))}
                        {sessions.data.length === 0 && (
                          <tr>
                            <td colSpan={7} className="quran-empty">
                              {t("console_quran.no_sessions_period")}
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                  {pagination(sessions)}
                </>
              ) : (
                <p className="quran-empty">
                  {t("console_quran.session_permission")}
                </p>
              )}
            </>
          )}
          {filters.tab === "registration" && (
            <div className="quran-registration">
              <div className="toolbar">
                <div>
                  <h2>{t("console_quran.registration_title")}</h2>
                  <p className="cell-sub">
                    {t("console_quran.registration_help")}
                  </p>
                </div>
                {registration && course && (
                  <Link
                    className="console-button primary"
                    href={
                      "/manage/registration/forms/create?course=" + course.id
                    }
                  >
                    {t("console_quran.create_form")}
                  </Link>
                )}
              </div>
              {registration && course ? (
                <>
                  <div className="quran-registration-stages">
                    <Link
                      href={
                        "/manage/registration?stage=requests&course=" +
                        course.id
                      }
                    >
                      {t("console_quran.new_requests")}{" "}
                      <b>{registration.counts.requests}</b>
                    </Link>
                    <Link
                      href={
                        "/manage/registration?stage=accepted&course=" +
                        course.id
                      }
                    >
                      {t("console_quran.accepted_requests")}{" "}
                      <b>{registration.counts.accepted}</b>
                    </Link>
                    <Link href={href({ tab: "students", status: "pending" })}>
                      {t("console_quran.pending")} <b>{counts.pending}</b>
                    </Link>
                  </div>
                  <div className="console-table-scroll">
                    <table className="table console-table">
                      <thead>
                        <tr>
                          {[
                            "form",
                            "questions",
                            "requests",
                            "status_action",
                          ].map((key) => (
                            <th key={key} scope="col">
                              {t("console_quran." + key)}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {registration.forms.map((form) => (
                          <tr key={form.id}>
                            <td>
                              <strong className="cell-title">
                                {form.title}
                              </strong>
                              <span className="cell-sub">
                                {form.description}
                              </span>
                            </td>
                            <td>{form.questions.length}</td>
                            <td>
                              <Link
                                className="inline-link"
                                href={
                                  "/manage/registration?stage=requests&course=" +
                                  course.id +
                                  "&form=" +
                                  form.id
                                }
                              >
                                {form.applications_count}
                              </Link>
                            </td>
                            <td>
                              <span
                                className={
                                  "status " +
                                  (form.is_active ? "green" : "slate")
                                }
                              >
                                {t(
                                  form.is_active
                                    ? "console_quran.form_active"
                                    : "console_quran.form_inactive",
                                )}
                              </span>
                              <div className="actions">
                                <Link
                                  className="inline-link"
                                  href={
                                    "/manage/registration/forms/" +
                                    form.id +
                                    "/edit"
                                  }
                                >
                                  {t("console_quran.edit_form")}
                                </Link>
                                <a
                                  className="inline-link"
                                  href={form.public_url}
                                  target="_blank"
                                  rel="noreferrer"
                                >
                                  {t("console_quran.public_form")}
                                </a>
                                <button
                                  type="button"
                                  className="inline-link"
                                  onClick={() => {
                                    void navigator.clipboard
                                      .writeText(form.public_url)
                                      .then(() => setCopied(form.id))
                                      .catch(() => setCopied("error"));
                                  }}
                                >
                                  {t(
                                    copied === form.id
                                      ? "console_quran.copied"
                                      : "console_quran.copy_link",
                                  )}
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        {registration.forms.length === 0 && (
                          <tr>
                            <td colSpan={4} className="quran-empty">
                              {t("console_quran.no_forms")}
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                  {copied === "error" && (
                    <p role="alert" className="quran-error">
                      {t("console_quran.copy_failed")}
                    </p>
                  )}
                </>
              ) : (
                <p className="quran-empty">
                  {t(
                    course
                      ? "console_quran.registration_permission"
                      : "console_quran.course_missing",
                  )}
                </p>
              )}
            </div>
          )}
          {filters.tab === "policy" && (
            <div className="quran-policy">
              <h2>{t("console_quran.policy_title")}</h2>
              <p>{t("console_quran.policy_description")}</p>
              <dl>
                <div className="quran-rule">
                  <dt>{t("console_quran.policy_relationship")}</dt>
                  <dd>{t("console_quran.policy_relationship_text")}</dd>
                </div>
                <div className="quran-rule">
                  <dt>{t("console_quran.policy_eligibility")}</dt>
                  <dd>
                    {t(
                      policy.requires_declared
                        ? "console_quran.policy_declared_required"
                        : "console_quran.policy_qualification",
                    )}
                  </dd>
                </div>
                <div className="quran-rule">
                  <dt>{t("console_quran.time_duration")}</dt>
                  <dd>
                    {t("console_quran.policy_times").replace(
                      ":durations",
                      defaults.durations.join("، "),
                    )}{" "}
                    <bdi>{defaults.timezone}</bdi>
                  </dd>
                </div>
                <div className="quran-rule">
                  <dt>{t("console_quran.policy_changes")}</dt>
                  <dd>
                    {t("console_quran.edit_protection").replace(
                      ":hours",
                      String(policy.edit_lock_hours),
                    )}
                    <p>
                      {t("console_quran.policy_notice")
                        .replace(":cancel", String(policy.cancel_minutes))
                        .replace(":postpone", String(policy.postpone_minutes))}
                    </p>
                  </dd>
                </div>
                <div className="quran-rule">
                  <dt>{t("console_quran.absence_hold")}</dt>
                  <dd>
                    {t("console_quran.policy_absence").replace(
                      ":days",
                      String(policy.counter_days),
                    )}
                    <ul>
                      {policy.ladder.map((step, index) => (
                        <li key={index}>
                          {step.threshold} ·{" "}
                          {t("console_quran.ladder." + step.action)}
                        </li>
                      ))}
                    </ul>
                    <p>{t("console_quran.policy_preserve")}</p>
                  </dd>
                </div>
                <div className="quran-rule">
                  <dt>{t("console_quran.teacher_dues")}</dt>
                  <dd>{t("console_quran.policy_dues")}</dd>
                </div>
              </dl>
              {can.settings && (
                <Link className="console-button" href="/manage/settings">
                  {t("console_quran.unified_settings")}
                </Link>
              )}
            </div>
          )}
        </section>
        {current && (
          <QuranSheet title={current.name} onClose={closeStudent}>
            <div className="quran-sheet-summary">
              <div>
                <span className="status slate">{current.code}</span>
                <p className="cell-sub">{course?.name}</p>
              </div>
              <Link
                className="inline-link"
                href={"/manage/students/" + current.id}
              >
                {t("console_quran.full_profile")}
              </Link>
            </div>
            {editing && can.place ? (
              <QuranEditor
                student={current}
                value={draft(current)}
                onChange={(value) => setDraft(current, value)}
                defaults={defaults}
                teachers={teachers}
                busy={busy[current.id] ?? false}
                error={errors[current.id] ?? ""}
                onSave={() => void save(current)}
                editLockHours={policy.edit_lock_hours}
              />
            ) : (
              <>
                <section className="quran-detail-section">
                  <h3>{t("console_quran.study_plan")}</h3>
                  <dl className="definition">
                    <div>
                      <dt>{t("console_quran.primary_teacher")}</dt>
                      <dd>
                        {current.teacher_name ??
                          t("console_quran.not_assigned")}
                      </dd>
                    </div>
                    <div>
                      <dt>{t("console_quran.slots")}</dt>
                      <dd>{displaySlots(current)}</dd>
                    </div>
                    <div>
                      <dt>{t("console_quran.placement_period")}</dt>
                      <dd>
                        <bdi>
                          {current.schedule?.starts_on ?? "—"} —{" "}
                          {current.schedule?.ends_on ??
                            t("console_quran.open_end")}
                        </bdi>
                        <small className="cell-sub">
                          {current.schedule &&
                            t("console_quran.repeat_every").replace(
                              ":count",
                              String(current.schedule.interval_weeks),
                            )}
                        </small>
                      </dd>
                    </div>
                  </dl>
                  {can.place && (
                    <button
                      className="console-button"
                      type="button"
                      onClick={() => setEditing(true)}
                    >
                      {t(
                        current.schedule
                          ? "console_quran.edit_schedule"
                          : "console_quran.prepare_placement",
                      )}
                    </button>
                  )}
                </section>
                <section className="quran-detail-section">
                  <h3>{t("console_quran.attendance_progress")}</h3>
                  <p className="cell-sub">{filters.period}</p>
                  {attendance(current)}
                  <p>
                    {current.insight?.progress?.topics ??
                      t("console_quran.no_progress")}
                  </p>
                  {current.insight?.progress?.note && (
                    <p>{current.insight.progress.note}</p>
                  )}
                  {current.insight?.progress?.next_plan && (
                    <p className="detail-note">
                      {t("console_quran.next_plan")}{" "}
                      {current.insight.progress.next_plan}
                    </p>
                  )}
                </section>
                <section className="quran-detail-section">
                  <h3>{t("console_quran.followup")}</h3>
                  {issue(current)}
                  {current.enrollments.map((enrollment) => (
                    <p key={enrollment.id}>
                      {enrollment.status_label}
                      {enrollment.return_date && (
                        <>
                          {" "}
                          · <bdi>{enrollment.return_date}</bdi>
                        </>
                      )}
                    </p>
                  ))}
                  {can.followup && (
                    <Link href={followup(current)} className="inline-link">
                      {t("console_quran.absence_hold")}
                    </Link>
                  )}
                </section>
                {can.sessions && (
                  <section className="quran-detail-section">
                    <h3>{t("console_quran.sessions_period")}</h3>
                    <Link
                      className="inline-link"
                      href={href({ tab: "schedule", student: current.id })}
                    >
                      {t("console_quran.open_student_sessions")}
                    </Link>
                  </section>
                )}
              </>
            )}
          </QuranSheet>
        )}
        {teacher && (
          <QuranSheet
            title={teacher.name}
            onClose={() => setSelectedTeacher(null)}
          >
            <div className="quran-sheet-summary">
              <span className="status slate">
                {teacher.student_ids.length}{" "}
                {t("console_quran.current_students")}
              </span>
              {can.teacherProfile && (
                <Link
                  className="inline-link"
                  href={"/manage/teachers/" + teacher.id}
                >
                  {t("console_quran.full_profile")}
                </Link>
              )}
            </div>
            <section className="quran-detail-section">
              <h3>{t("console_quran.declared_availability")}</h3>
              {can.availability && (
                <Link
                  className="console-button"
                  href={"/manage/teachers/" + teacher.id + "/availability"}
                >
                  {t("console_quran.manage_availability")}
                </Link>
              )}
              <p className="detail-note">
                {t("console_quran.availability_help")}
              </p>
              {teacher.availability.map((slot) => (
                <div className="quran-availability" key={slot.id}>
                  <strong>{t("console_quran.days." + slot.weekday)}</strong>
                  <bdi>
                    {slot.start_time}–{slot.end_time}
                  </bdi>
                  <span className="cell-sub">
                    <bdi>{slot.timezone}</bdi> ·{" "}
                    {t("console_quran.approval." + slot.approval_status)}
                  </span>
                  <small>
                    <bdi>
                      {slot.effective_from} —{" "}
                      {slot.effective_to ?? t("console_quran.open_end")}
                    </bdi>
                  </small>
                </div>
              ))}
              {teacher.availability.length === 0 && (
                <p>{t("console_quran.no_declared_availability")}</p>
              )}
            </section>
            <section className="quran-detail-section">
              <h3>{t("console_quran.teacher_students")}</h3>
              {teacher.student_ids.map((id) => {
                const student = studentById(id);
                return student ? (
                  <div className="quran-teacher-student" key={id}>
                    <div>
                      <button
                        className="inline-link"
                        type="button"
                        onClick={() => {
                          setSelectedTeacher(null);
                          openStudent(id);
                        }}
                      >
                        {student.name}
                      </button>
                      {displaySlots(student)}
                    </div>
                    {attendance(student)}
                  </div>
                ) : null;
              })}
              {teacher.student_ids.length === 0 && (
                <p>{t("console_quran.no_teacher_students")}</p>
              )}
            </section>
            <div className="actions">
              {can.sessions && (
                <Link
                  className="console-button"
                  href={href({
                    tab: "schedule",
                    teacher: teacher.id,
                    student: null,
                  })}
                >
                  {t("console_quran.teacher_sessions")}
                </Link>
              )}
              {can.dues && (
                <Link
                  className="console-button"
                  href={
                    "/manage/teacher-dues?track=quran&teacher=" + teacher.id
                  }
                >
                  {t("console_quran.teacher_dues")}
                </Link>
              )}
            </div>
          </QuranSheet>
        )}
      </div>
    </ConsoleLayout>
  );
}
