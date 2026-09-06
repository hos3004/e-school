import { Head, Link, useForm } from "@inertiajs/react";
import {
  cloneElement,
  useState,
  type FormEvent,
  type ReactNode,
  type ReactElement,
} from "react";
import axios from "axios";
import { ArrowLeft, CalendarDays, Check } from "lucide-react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import "../../../css/console-sessions.css";

type Schedule = {
  id: string | null;
  group_id: string;
  course_id: string;
  staff_profile_id: string;
  weekdays: number[];
  start_time: string;
  duration_minutes: number;
  interval_weeks: number;
  timezone: string;
  starts_on: string;
  ends_on: string | null;
  materialized_until?: string;
};
type Group = {
  id: string;
  name: string;
  code: string;
  timezone: string;
  program_ids: string[];
  assignments: {
    teacher_id: string;
    course_id: string | null;
    from: string | null;
    until: string | null;
  }[];
};
type Course = {
  id: string;
  name: string;
  program_id: string;
  program: string;
  teachers: string[];
};
type Props = {
  schedule: Schedule;
  groups: Group[];
  courses: Course[];
  teachers: { staff_profile_id: string; name: string }[];
  timezones: string[];
  durations: number[];
  maxInterval: number;
  editLockHours: number;
  outsideAvailability: string;
  can: { group: boolean; sessions: boolean };
};

export default function GroupScheduleEditor({
  schedule,
  groups,
  courses,
  teachers,
  timezones,
  durations,
  maxInterval,
  editLockHours,
  outsideAvailability,
  can,
}: Props) {
  const t = useI18n();
  const form = useForm({
    group_id: schedule.group_id,
    course_id: schedule.course_id,
    staff_profile_id: schedule.staff_profile_id,
    weekdays: schedule.weekdays,
    start_time: schedule.start_time,
    duration_minutes: schedule.duration_minutes,
    interval_weeks: schedule.interval_weeks,
    timezone: schedule.timezone,
    starts_on: schedule.starts_on,
    ends_on: schedule.ends_on ?? "",
  });
  const [checking, setChecking] = useState(false);
  const [availability, setAvailability] = useState<string[] | null>(null);
  const [availabilityError, setAvailabilityError] = useState("");
  const group = groups.find((item) => item.id === form.data.group_id);
  const availableCourses = courses.filter((item) =>
    group?.program_ids.includes(item.program_id),
  );
  const course = availableCourses.find(
    (item) => item.id === form.data.course_id,
  );
  const eligibleTeachers = teachers.filter(
    (item) =>
      course?.teachers.includes(item.staff_profile_id) &&
      group?.assignments.some(
        (assignment) =>
          assignment.teacher_id === item.staff_profile_id &&
          assignment.course_id === course.id &&
          (!assignment.from || assignment.from <= form.data.starts_on) &&
          (!assignment.until ||
            !form.data.ends_on ||
            assignment.until >= form.data.ends_on),
      ),
  );
  const selectedTeacher = teachers.find(
    (item) => item.staff_profile_id === form.data.staff_profile_id,
  );
  const title = t(
    "console_sessions." + (schedule.id ? "edit_title" : "create_title"),
  );
  const back = can.sessions
    ? "/manage/sessions?" +
      new URLSearchParams({
        group: form.data.group_id,
        course: form.data.course_id,
        ...(form.data.starts_on ? { date: form.data.starts_on } : {}),
      })
    : can.group && form.data.group_id
      ? "/manage/groups/" + form.data.group_id
      : "/manage";
  const set = <K extends keyof typeof form.data>(
    key: K,
    value: (typeof form.data)[K],
  ) => {
    form.setData((data) => ({ ...data, [key]: value }));
    setAvailability(null);
    setAvailabilityError("");
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (schedule.id)
      form.patch("/manage/schedules/" + schedule.id, { preserveScroll: true });
    else form.post("/manage/schedules", { preserveScroll: true });
  };
  const checkAvailability = async () => {
    setChecking(true);
    setAvailability(null);
    setAvailabilityError("");
    try {
      const result = await axios.get<{ available_start_times: string[] }>(
        "/manage/schedules/availability",
        {
          params: {
            ...form.data,
            ...(schedule.id ? { schedule_id: schedule.id } : {}),
          },
        },
      );
      setAvailability(result.data.available_start_times);
    } catch (error) {
      const errors = axios.isAxiosError(error)
        ? error.response?.data?.errors
        : null;
      setAvailabilityError(
        errors
          ? String(Object.values(errors).flat()[0])
          : t("console_sessions.availability_failed"),
      );
    } finally {
      setChecking(false);
    }
  };
  const field = (name: keyof typeof form.data, children: ReactNode) => (
    <label className="field" key={name}>
      <span>{t("console_sessions.fields." + name)}</span>
      {cloneElement(children as ReactElement<{ "aria-label": string }>, {
        "aria-label": t("console_sessions.fields." + name),
      })}
      {form.errors[name] && (
        <small className="sessions-error">{form.errors[name]}</small>
      )}
    </label>
  );
  const summary = [
    [t("console_sessions.group"), group?.name ?? t("console_sessions.select")],
    [
      t("console_sessions.course"),
      course?.name ?? t("console_sessions.select"),
    ],
    [
      t("console_sessions.teacher"),
      selectedTeacher?.name ?? t("console_sessions.select"),
    ],
    [
      t("console_sessions.fields.weekdays"),
      form.data.weekdays
        .map((day) => t("console_sessions.weekdays." + day))
        .join("، ") || "—",
    ],
    [t("console_sessions.time"), form.data.start_time || "—"],
    [
      t("console_sessions.fields.duration_minutes"),
      String(form.data.duration_minutes) + " " + t("console_sessions.minutes"),
    ],
    [
      t("console_sessions.fields.interval_weeks"),
      t("console_sessions.weekly").replace(
        ":count",
        String(form.data.interval_weeks),
      ),
    ],
    [t("console_sessions.fields.timezone"), form.data.timezone],
    [t("console_sessions.fields.starts_on"), form.data.starts_on],
    [
      t("console_sessions.fields.ends_on"),
      form.data.ends_on || t("console_sessions.no_end"),
    ],
  ];
  return (
    <ConsoleLayout
      title={title}
      section="during"
      description={t("console_sessions.editor_description")}
      actions={
        <Link className="console-button" href={back}>
          <ArrowLeft size={16} />
          {t("console_sessions.return")}
        </Link>
      }
    >
      <Head title={title} />
      <div className="setup-layout console-setup-layout sessions-editor">
        <form className="panel console-setup-panel" onSubmit={submit}>
          {!!Object.keys(form.errors).length && (
            <div className="sessions-alert" role="alert">
              {Object.values(form.errors).map((error, index) => (
                <p key={index}>{error}</p>
              ))}
            </div>
          )}
          {schedule.id && (
            <p className="sessions-note">
              {t("console_sessions.edit_notice").replace(
                ":hours",
                String(editLockHours),
              )}
            </p>
          )}
          <Section title={t("console_sessions.setup")} step={1}>
            {field(
              "group_id",
              <select
                className="console-control"
                required
                disabled={!!schedule.id}
                value={form.data.group_id}
                onChange={(event) => {
                  const selected = groups.find(
                    (item) => item.id === event.target.value,
                  );
                  form.setData((data) => ({
                    ...data,
                    group_id: event.target.value,
                    course_id: "",
                    staff_profile_id: "",
                    timezone: selected?.timezone ?? data.timezone,
                  }));
                  setAvailability(null);
                }}
              >
                <option value="">{t("console_sessions.select")}</option>
                {groups.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name} · {item.code}
                  </option>
                ))}
              </select>,
            )}
            {field(
              "course_id",
              <select
                className="console-control"
                required
                disabled={!!schedule.id || !group}
                value={form.data.course_id}
                onChange={(event) => {
                  form.setData((data) => ({
                    ...data,
                    course_id: event.target.value,
                    staff_profile_id: "",
                  }));
                  setAvailability(null);
                }}
              >
                <option value="">{t("console_sessions.select")}</option>
                {availableCourses.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name} · {item.program}
                  </option>
                ))}
              </select>,
            )}
            {field(
              "staff_profile_id",
              <select
                className="console-control"
                required
                value={form.data.staff_profile_id}
                onChange={(event) =>
                  set("staff_profile_id", event.target.value)
                }
              >
                <option value="">{t("console_sessions.select")}</option>
                {eligibleTeachers.map((item) => (
                  <option
                    value={item.staff_profile_id}
                    key={item.staff_profile_id}
                  >
                    {item.name}
                  </option>
                ))}
                {selectedTeacher &&
                  !eligibleTeachers.includes(selectedTeacher) && (
                    <option value={selectedTeacher.staff_profile_id} disabled>
                      {selectedTeacher.name}
                    </option>
                  )}
              </select>,
            )}
            <p className="sessions-field-note">
              {course && eligibleTeachers.length === 0
                ? t("console_sessions.no_teacher")
                : t("console_sessions.readiness")}
              {can.group && group && (
                <Link
                  href={"/manage/groups/" + group.id}
                  className="inline-link"
                >
                  {t("console_sessions.open_group")}
                </Link>
              )}
            </p>
            {!groups.length && (
              <p className="sessions-field-note" role="status">
                {t("console_sessions.no_groups")}
              </p>
            )}
          </Section>
          <Section title={t("console_sessions.timing")} step={2}>
            <fieldset className="sessions-days">
              <legend>{t("console_sessions.fields.weekdays")}</legend>
              {Array.from({ length: 7 }, (_, day) => (
                <label key={day}>
                  <input
                    type="checkbox"
                    checked={form.data.weekdays.includes(day)}
                    onChange={(event) =>
                      set(
                        "weekdays",
                        event.target.checked
                          ? [...form.data.weekdays, day].sort((a, b) => a - b)
                          : form.data.weekdays.filter((item) => item !== day),
                      )
                    }
                  />
                  <span>{t("console_sessions.weekdays." + day)}</span>
                </label>
              ))}
            </fieldset>
            {field(
              "start_time",
              <input
                className="console-control"
                type="time"
                required
                dir="ltr"
                value={form.data.start_time}
                onChange={(event) => set("start_time", event.target.value)}
              />,
            )}
            {field(
              "duration_minutes",
              <select
                className="console-control"
                value={form.data.duration_minutes}
                onChange={(event) =>
                  set("duration_minutes", Number(event.target.value))
                }
              >
                {durations.map((value) => (
                  <option key={value} value={value}>
                    {value} {t("console_sessions.minutes")}
                  </option>
                ))}
              </select>,
            )}
            {field(
              "interval_weeks",
              <input
                className="console-control"
                type="number"
                required
                min={1}
                max={maxInterval}
                value={form.data.interval_weeks}
                onChange={(event) =>
                  set("interval_weeks", Number(event.target.value))
                }
              />,
            )}
            {field(
              "timezone",
              <select
                className="console-control"
                value={form.data.timezone}
                onChange={(event) => set("timezone", event.target.value)}
              >
                {timezones.map((value) => (
                  <option key={value}>{value}</option>
                ))}
              </select>,
            )}
            <p className="sessions-field-note">
              {t("console_sessions.same_time")}
            </p>
          </Section>
          <Section title={t("console_sessions.period")} step={3}>
            {field(
              "starts_on",
              <input
                className="console-control"
                type="date"
                required
                value={form.data.starts_on}
                onChange={(event) => set("starts_on", event.target.value)}
              />,
            )}
            {field(
              "ends_on",
              <input
                className="console-control"
                type="date"
                min={form.data.starts_on}
                value={form.data.ends_on}
                onChange={(event) => set("ends_on", event.target.value)}
              />,
            )}
            <div className="sessions-availability">
              <button
                className="console-button"
                type="button"
                disabled={
                  checking ||
                  !form.data.staff_profile_id ||
                  !form.data.start_time ||
                  !form.data.weekdays.length
                }
                onClick={() => void checkAvailability()}
              >
                <CalendarDays size={16} />
                {t(
                  "console_sessions." +
                    (checking ? "checking" : "availability"),
                )}
              </button>
              <p>
                {t("console_sessions.availability_notice")} ·{" "}
                {t("console_sessions.outside_availability")}:{" "}
                {t("console_sessions." + outsideAvailability)}
              </p>
              {availabilityError && (
                <p className="sessions-error" role="alert">
                  {availabilityError}
                </p>
              )}
              {availability !== null && (
                <div role="status">
                  <p>
                    {t(
                      "console_sessions." +
                        (availability.length
                          ? "available"
                          : "availability_empty"),
                    )}
                  </p>
                  <div className="sessions-times">
                    {availability.map((time) => (
                      <button
                        type="button"
                        className="console-button"
                        key={time}
                        onClick={() => {
                          form.setData("start_time", time);
                          setAvailability(null);
                        }}
                      >
                        {time}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </Section>
          <footer className="panel-foot">
            <span>
              {schedule.materialized_until
                ? t("console_sessions.generated_until") +
                  " " +
                  schedule.materialized_until
                : ""}
            </span>
            <button
              className="console-button primary"
              disabled={
                form.processing ||
                !form.data.weekdays.length ||
                !form.data.staff_profile_id
              }
            >
              <Check size={16} />
              {t("console_sessions." + (form.processing ? "saving" : "save"))}
            </button>
          </footer>
        </form>
        <aside className="panel summary">
          <div className="panel-head">
            <h2>{t("console_sessions.summary")}</h2>
          </div>
          <div className="summary-content">
            <dl>
              {summary.map(([label, value]) => (
                <div className="definition" key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <div className="summary-note">
              {t("console_sessions.same_time")}
            </div>
          </div>
        </aside>
      </div>
    </ConsoleLayout>
  );
}
function Section({
  title,
  step,
  children,
}: {
  title: string;
  step: number;
  children: ReactNode;
}) {
  return (
    <section className="form-section">
      <h2>
        <span className="step-number">{String(step).padStart(2, "0")}</span>
        {title}
      </h2>
      <div className="field-grid">{children}</div>
    </section>
  );
}
