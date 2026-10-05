import { Head, Link, useForm } from "@inertiajs/react";
import {
  cloneElement,
  useEffect,
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

type WeeklySlot = { weekday: number; start_time: string };
type Schedule = {
  id: string | null;
  group_id: string;
  course_id: string;
  staff_profile_id: string;
  weekdays: number[];
  start_time: string;
  weekly_slots: WeeklySlot[];
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
  durationLimits: { min: number; max: number };
  maxInterval: number;
  editLockHours: number;
  outsideAvailability: string;
  can: { group: boolean; sessions: boolean };
};

function sortSlots(slots: WeeklySlot[]): WeeklySlot[] {
  return [...slots].sort((a, b) => a.weekday - b.weekday);
}

export default function GroupScheduleEditor({
  schedule,
  groups,
  courses,
  teachers,
  timezones,
  durations,
  durationLimits,
  maxInterval,
  editLockHours,
  outsideAvailability,
  can,
}: Props) {
  const t = useI18n();
  const initialWeeklySlots = sortSlots(
    schedule.weekly_slots.length > 0
      ? schedule.weekly_slots
      : schedule.weekdays.map((day) => ({
          weekday: day,
          start_time: schedule.start_time,
        })),
  );
  const form = useForm({
    group_id: schedule.group_id,
    course_id: schedule.course_id,
    staff_profile_id: schedule.staff_profile_id,
    weekdays: schedule.weekdays,
    start_time: schedule.start_time,
    weekly_slots: initialWeeklySlots,
    duration_minutes: schedule.duration_minutes,
    session_rate_major: "",
    rate_reason: "",
    interval_weeks: schedule.interval_weeks,
    timezone: schedule.timezone,
    starts_on: schedule.starts_on,
    ends_on: schedule.ends_on ?? "",
    apply_immediately: false,
    override_reason: "",
    notify_student: true,
    notify_teacher: true,
  });
  const [dayChecks, setDayChecks] = useState<
    Record<
      number,
      { checking: boolean; times: string[] | null; error: string }
    >
  >({});
  const [teacherRate, setTeacherRate] = useState<{
    rate_major: string | null;
    currency: string;
    requires_rate: boolean;
  } | null>(null);
  useEffect(() => {
    // Inertia keeps this component mounted across navigations between
    // /schedules/create and /schedules/{id}/edit (same component name), so
    // useForm's one-time initial state otherwise keeps stale values from
    // whichever schedule/group this page last showed. Resync on every fresh
    // schedule prop so a submission targets what is actually on screen.
    form.setData({
      group_id: schedule.group_id,
      course_id: schedule.course_id,
      staff_profile_id: schedule.staff_profile_id,
      weekdays: schedule.weekdays,
      start_time: schedule.start_time,
      weekly_slots: sortSlots(
        schedule.weekly_slots.length > 0
          ? schedule.weekly_slots
          : schedule.weekdays.map((day) => ({
              weekday: day,
              start_time: schedule.start_time,
            })),
      ),
      duration_minutes: schedule.duration_minutes,
      session_rate_major: "",
      rate_reason: "",
      interval_weeks: schedule.interval_weeks,
      timezone: schedule.timezone,
      starts_on: schedule.starts_on,
      ends_on: schedule.ends_on ?? "",
      apply_immediately: false,
      override_reason: "",
      notify_student: true,
      notify_teacher: true,
    });
    setDayChecks({});
    setTeacherRate(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [schedule]);
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
          // course_id فارغ يعني المعلم معيّن للمجموعة كلها، وليس كورسًا واحدًا فقط.
          (assignment.course_id === course.id ||
            assignment.course_id === null) &&
          (!assignment.from || assignment.from <= form.data.starts_on) &&
          (!assignment.until ||
            !form.data.ends_on ||
            assignment.until >= form.data.ends_on),
      ),
  );
  const selectedTeacher = teachers.find(
    (item) => item.staff_profile_id === form.data.staff_profile_id,
  );
  const courseId = form.data.course_id;
  const staffProfileId = form.data.staff_profile_id;
  useEffect(() => {
    if (!courseId || !staffProfileId) {
      setTeacherRate(null);
      return;
    }
    let active = true;
    axios
      .get("/manage/schedules/rate", {
        params: { course_id: courseId, staff_profile_id: staffProfileId },
      })
      .then((result) => {
        if (active) {
          setTeacherRate(result.data);
          form.setData(
            "session_rate_major",
            result.data.rate_major ?? "",
          );
        }
      })
      .catch(() => {
        if (active) setTeacherRate(null);
      });
    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [courseId, staffProfileId]);
  const title = t(
    "console_sessions." + (schedule.id ? "edit_title" : "create_title"),
  );
  const addAnotherTimeHref = schedule.id
    ? "/manage/schedules/create?" +
      new URLSearchParams({
        group: schedule.group_id,
        course: schedule.course_id,
        teacher: schedule.staff_profile_id,
        duration: String(schedule.duration_minutes),
        interval: String(schedule.interval_weeks),
        timezone: schedule.timezone,
        starts: schedule.starts_on,
        ...(schedule.ends_on ? { ends: schedule.ends_on } : {}),
      })
    : null;
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
  };
  const toggleDay = (day: number, checked: boolean) => {
    form.setData((data) => {
      const nextSlots = sortSlots(
        checked
          ? [...data.weekly_slots, { weekday: day, start_time: "" }]
          : data.weekly_slots.filter((slot) => slot.weekday !== day),
      );
      return {
        ...data,
        weekdays: checked
          ? [...data.weekdays, day].sort((a, b) => a - b)
          : data.weekdays.filter((item) => item !== day),
        weekly_slots: nextSlots,
        start_time: nextSlots[0]?.start_time ?? "",
      };
    });
    setDayChecks((prev) => {
      const next = { ...prev };
      delete next[day];
      return next;
    });
  };
  const setDaySlot = (day: number, time: string) => {
    form.setData((data) => {
      const nextSlots = sortSlots(
        data.weekly_slots.map((slot) =>
          slot.weekday === day ? { ...slot, start_time: time } : slot,
        ),
      );
      return { ...data, weekly_slots: nextSlots, start_time: nextSlots[0]?.start_time ?? "" };
    });
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (schedule.id) {
      form.transform((data) => ({
        ...data,
        override_reason: data.apply_immediately ? data.override_reason : "",
      }));
      form.patch("/manage/schedules/" + schedule.id, { preserveScroll: true });
    } else {
      // apply_immediately لا يُقبل إلا عند تعديل جدول قائم.
      form.transform(
        ({
          apply_immediately: _apply,
          override_reason: _reason,
          notify_student: _student,
          notify_teacher: _teacher,
          ...data
        }) => data,
      );
      form.post("/manage/schedules", { preserveScroll: true });
    }
  };
  const checkDayAvailability = async (day: number) => {
    setDayChecks((prev) => ({
      ...prev,
      [day]: { checking: true, times: null, error: "" },
    }));
    const slotTime =
      form.data.weekly_slots.find((slot) => slot.weekday === day)
        ?.start_time || "00:00";
    try {
      const result = await axios.get<{ available_start_times: string[] }>(
        "/manage/schedules/availability",
        {
          params: {
            group_id: form.data.group_id,
            course_id: form.data.course_id,
            staff_profile_id: form.data.staff_profile_id,
            weekdays: [day],
            start_time: slotTime,
            duration_minutes: form.data.duration_minutes,
            interval_weeks: form.data.interval_weeks,
            timezone: form.data.timezone,
            starts_on: form.data.starts_on,
            ...(form.data.ends_on ? { ends_on: form.data.ends_on } : {}),
            ...(schedule.id ? { schedule_id: schedule.id } : {}),
          },
        },
      );
      setDayChecks((prev) => ({
        ...prev,
        [day]: { checking: false, times: result.data.available_start_times, error: "" },
      }));
    } catch (error) {
      const errors = axios.isAxiosError(error)
        ? error.response?.data?.errors
        : null;
      setDayChecks((prev) => ({
        ...prev,
        [day]: {
          checking: false,
          times: null,
          error: errors
            ? String(Object.values(errors).flat()[0])
            : t("console_sessions.availability_failed"),
        },
      }));
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
  const missingDayTimes = form.data.weekly_slots.some(
    (slot) => !slot.start_time,
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
      t("console_sessions.fields.weekly_slots"),
      form.data.weekly_slots.length
        ? form.data.weekly_slots
            .map(
              (slot) =>
                t("console_sessions.weekdays." + slot.weekday) +
                " " +
                (slot.start_time || "—"),
            )
            .join("، ")
        : "—",
    ],
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
        <>
          {addAnotherTimeHref && (
            <Link className="console-button" href={addAnotherTimeHref}>
              <CalendarDays size={16} />
              {t("console_sessions.add_another_time")}
            </Link>
          )}
          <Link className="console-button" href={back}>
            <ArrowLeft size={16} />
            {t("console_sessions.return")}
          </Link>
        </>
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
              <p className="sessions-field-note">
                {t("console_sessions.weekdays_hint")}
              </p>
              {(form.errors.weekdays ||
                form.errors.start_time ||
                form.errors.weekly_slots) && (
                <small className="sessions-error" role="alert">
                  {form.errors.weekdays ||
                    form.errors.weekly_slots ||
                    form.errors.start_time}
                </small>
              )}
              {Array.from({ length: 7 }, (_, day) => {
                const checked = form.data.weekdays.includes(day);
                const slotTime =
                  form.data.weekly_slots.find((slot) => slot.weekday === day)
                    ?.start_time ?? "";
                const check = dayChecks[day];
                return (
                  <div className="sessions-day-row" key={day}>
                    <label>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={(event) =>
                          toggleDay(day, event.target.checked)
                        }
                      />
                      <span>{t("console_sessions.weekdays." + day)}</span>
                    </label>
                    {checked && (
                      <div className="sessions-day-time">
                        <input
                          className="console-control"
                          type="time"
                          required
                          dir="ltr"
                          value={slotTime}
                          aria-label={t("console_sessions.weekdays." + day)}
                          onChange={(event) =>
                            setDaySlot(day, event.target.value)
                          }
                        />
                        <button
                          type="button"
                          className="console-button"
                          disabled={
                            !form.data.staff_profile_id || check?.checking
                          }
                          onClick={() => void checkDayAvailability(day)}
                        >
                          <CalendarDays size={16} />
                          {t(
                            "console_sessions." +
                              (check?.checking
                                ? "checking_day"
                                : "check_day_availability"),
                          )}
                        </button>
                        {!slotTime && (
                          <small className="sessions-error">
                            {t("console_sessions.day_time_missing")}
                          </small>
                        )}
                        {check?.error && (
                          <small className="sessions-error" role="alert">
                            {check.error}
                          </small>
                        )}
                        {check?.times && (
                          <div className="sessions-times">
                            {check.times.length === 0 && (
                              <span>{t("console_sessions.no_day_times")}</span>
                            )}
                            {check.times.map((time) => (
                              <button
                                type="button"
                                className="console-button"
                                key={time}
                                onClick={() => {
                                  setDaySlot(day, time);
                                  setDayChecks((prev) => ({
                                    ...prev,
                                    [day]: {
                                      checking: false,
                                      times: null,
                                      error: "",
                                    },
                                  }));
                                }}
                              >
                                {time}
                              </button>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                );
              })}
            </fieldset>
            {field(
              "duration_minutes",
              <input
                className="console-control"
                type="number"
                required
                list="group-session-durations"
                min={durationLimits.min}
                max={durationLimits.max}
                step={1}
                value={form.data.duration_minutes}
                onChange={(event) =>
                  set("duration_minutes", Number(event.target.value))
                }
                aria-describedby="group-duration-hint"
              />,
            )}
            <datalist id="group-session-durations">
              {durations.map((value) => (
                <option key={value} value={value} />
              ))}
            </datalist>
            <p id="group-duration-hint" className="sessions-field-note">
              {t("console_sessions.rates.duration_hint")
                .replace(":min", String(durationLimits.min))
                .replace(":max", String(durationLimits.max))
                .replace(":durations", durations.join("، "))}
            </p>
            {selectedTeacher && (
              <div className="field">
                <span>{t("console_sessions.rates.session_rate")}</span>
                <input
                  className="console-control"
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={form.data.session_rate_major}
                  onChange={(event) =>
                    set("session_rate_major", event.target.value)
                  }
                  aria-label={t("console_sessions.rates.session_rate")}
                />
                {form.errors.session_rate_major && (
                  <small className="sessions-error">
                    {form.errors.session_rate_major}
                  </small>
                )}
                <p className="sessions-field-note">
                  {!teacherRate || !teacherRate.requires_rate
                    ? t("console_sessions.rates.rate_not_needed")
                    : teacherRate.rate_major === null
                      ? t("console_sessions.rates.no_rate")
                      : t("console_sessions.rates.current_rate") +
                        " " +
                        teacherRate.rate_major +
                        " " +
                        teacherRate.currency}
                </p>
                <p className="sessions-field-note">
                  {t("console_sessions.rates.session_rate_hint")}
                </p>
              </div>
            )}
            {form.data.session_rate_major !== "" && (
              <div className="field">
                <span>{t("console_sessions.rates.rate_reason")}</span>
                <input
                  className="console-control"
                  type="text"
                  required
                  minLength={3}
                  value={form.data.rate_reason}
                  onChange={(event) =>
                    set("rate_reason", event.target.value)
                  }
                  aria-label={t("console_sessions.rates.rate_reason")}
                />
                {form.errors.rate_reason && (
                  <small className="sessions-error">
                    {form.errors.rate_reason}
                  </small>
                )}
              </div>
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
              {schedule.id && (
                <>
                  {" "}
                  <Link className="inline-link" href={addAnotherTimeHref!}>
                    {t("console_sessions.add_another_time")}
                  </Link>
                </>
              )}
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
            <p className="sessions-field-note">
              {t("console_sessions.availability_notice")} ·{" "}
              {t("console_sessions.outside_availability")}:{" "}
              {t("console_sessions." + outsideAvailability)}
            </p>
          </Section>
          {schedule.id && (
            <div className="field quran-override">
              <label htmlFor="group-apply-immediately">
                <input
                  id="group-apply-immediately"
                  type="checkbox"
                  checked={form.data.apply_immediately}
                  disabled={form.processing}
                  onChange={(event) => {
                    set("apply_immediately", event.target.checked);
                    if (!event.target.checked) set("override_reason", "");
                  }}
                />
                {t("console_sessions.apply_immediately")}
              </label>
              <small className="cell-sub">
                {t("console_sessions.apply_immediately_help").replace(
                  ":hours",
                  String(editLockHours),
                )}
              </small>
              {form.data.apply_immediately && (
                <>
                  <label htmlFor="group-override-reason">
                    {t("console_sessions.override_reason")}
                  </label>
                  <textarea
                    id="group-override-reason"
                    className="console-control"
                    required
                    minLength={3}
                    maxLength={1000}
                    placeholder={t("console_sessions.override_reason_placeholder")}
                    value={form.data.override_reason}
                    disabled={form.processing}
                    onChange={(event) => set("override_reason", event.target.value)}
                  />
                </>
              )}
              <label htmlFor="group-notify-student">
                <input
                  id="group-notify-student"
                  type="checkbox"
                  checked={form.data.notify_student}
                  disabled={form.processing}
                  onChange={(event) => set("notify_student", event.target.checked)}
                />
                {t("console_sessions.notify_student")}
              </label>
              <label htmlFor="group-notify-teacher">
                <input
                  id="group-notify-teacher"
                  type="checkbox"
                  checked={form.data.notify_teacher}
                  disabled={form.processing}
                  onChange={(event) => set("notify_teacher", event.target.checked)}
                />
                {t("console_sessions.notify_teacher")}
              </label>
              <small className="cell-sub">{t("console_sessions.notify_help")}</small>
            </div>
          )}
          <footer className="panel-foot">
            <span>
              {schedule.materialized_until
                ? t("console_sessions.generated_until") +
                  " " +
                  schedule.materialized_until
                : !form.data.weekdays.length
                  ? t("console_sessions.save_needs_weekday")
                  : !form.data.staff_profile_id
                    ? t("console_sessions.save_needs_teacher")
                    : missingDayTimes
                      ? t("console_sessions.save_needs_times")
                      : ""}
            </span>
            <button
              className="console-button primary"
              disabled={
                form.processing ||
                !form.data.weekdays.length ||
                !form.data.staff_profile_id ||
                missingDayTimes
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
