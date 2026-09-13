import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

type Choice = { value: string; label: string };

export type StudentEnrollment = {
  id: string;
  program: string;
  status: string;
  freezeUrl: string | null;
};

export type StudentSchedule = {
  id: string;
  course_id: string;
  course: string;
  teacher_id: string;
  teacher: string;
  slots: string[];
  duration_minutes: number;
};

export type StudentProgramsData = {
  enrollments: StudentEnrollment[];
  availablePrograms: Choice[];
  enrollUrl: string | null;
  schedules: StudentSchedule[];
  assignableCourses: Choice[];
  teacherOptionsUrl: string | null;
  assignUrl: string | null;
  changeUrl: string | null;
  removeUrl: string | null;
  durations: number[];
  timezone: string;
};

type Panel =
  | { kind: "enroll" }
  | { kind: "freeze"; id: string; url: string }
  | { kind: "assign" }
  | { kind: "change"; id: string }
  | { kind: "remove"; id: string };

const box = "rounded-lg border border-[var(--line)] p-3";
const field = "w-full rounded-lg border border-[var(--line)] p-3";
const ghost = "rounded-lg border border-[var(--line)] px-4 py-2";
const primary =
  "rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50";

export default function StudentPrograms({
  programs,
}: {
  programs: StudentProgramsData;
}) {
  const t = useI18n();
  const [panel, setPanel] = useState<Panel | null>(null);
  const [teachers, setTeachers] = useState<Choice[]>([]);
  const [loading, setLoading] = useState(false);
  const form = useForm({
    program_id: "",
    course_id: "",
    schedule_id: "",
    staff_profile_id: "",
    weekly_slots: [{ weekday: 0, start_time: "16:00" }],
    duration_minutes: programs.durations[0] ?? 25,
    interval_weeks: 1,
    timezone: programs.timezone,
    starts_on: new Date().toISOString().slice(0, 10),
    reason: "",
  });

  const close = () => {
    form.clearErrors();
    form.reset();
    setTeachers([]);
    setPanel(null);
  };

  const loadTeachers = (courseId: string) => {
    form.setData("staff_profile_id", "");
    setTeachers([]);
    if (!programs.teacherOptionsUrl || courseId === "") {
      return;
    }
    setLoading(true);
    fetch(
      programs.teacherOptionsUrl +
        "?" +
        new URLSearchParams({ course_id: courseId }),
      { headers: { Accept: "application/json" } },
    )
      .then((response) => (response.ok ? response.json() : null))
      .then((data) => setTeachers(data?.teachers ?? []))
      .catch(() => undefined)
      .finally(() => setLoading(false));
  };

  const reasonField = (
    <label className="block my-3">
      <span className="block mb-2">{t("console_people.programs.reason")}</span>
      <textarea
        className={field}
        value={form.data.reason}
        onChange={(event) => form.setData("reason", event.target.value)}
        required
        minLength={3}
        maxLength={1000}
        rows={2}
      />
    </label>
  );

  const slot = form.data.weekly_slots[0] ?? { weekday: 0, start_time: "16:00" };

  const errors = Object.values(form.errors).map((error, index) => (
    <p key={index} role="alert" className="text-red-700">
      {error}
    </p>
  ));

  const actions = (label: string, disabled = false) => (
    <div className="flex gap-2">
      <button
        className={primary}
        disabled={form.processing || form.data.reason.trim().length < 3 || disabled}
      >
        {label}
      </button>
      <button type="button" className={ghost} onClick={close}>
        {t("console_people.programs.cancel")}
      </button>
    </div>
  );

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">{t("console_people.programs.title")}</h2>
      <p className="text-sm my-2">{t("console_people.programs.hint")}</p>

      <h3 className="font-bold mt-5">
        {t("console_people.programs.current")}
      </h3>
      {programs.enrollments.length === 0 && (
        <p className="text-sm my-2">{t("console_people.programs.none")}</p>
      )}
      <ul className="my-3 flex flex-col gap-3">
        {programs.enrollments.map((enrollment) => (
          <li key={enrollment.id} className={box}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>
                <strong>{enrollment.program}</strong> — {enrollment.status}
              </span>
              {enrollment.freezeUrl && (
                <button
                  type="button"
                  className={ghost + " border-red-700 text-red-700"}
                  onClick={() => {
                    close();
                    setPanel({
                      kind: "freeze",
                      id: enrollment.id,
                      url: enrollment.freezeUrl as string,
                    });
                  }}
                >
                  {t("console_people.programs.freeze")}
                </button>
              )}
            </div>
            {panel?.kind === "freeze" && panel.id === enrollment.id && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.put(panel.url, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm">
                  {t("console_people.programs.confirm_freeze")}
                </p>
                {reasonField}
                {errors}
                {actions(t("console_people.programs.submit_freeze"))}
              </form>
            )}
          </li>
        ))}
      </ul>

      {programs.enrollUrl && programs.availablePrograms.length > 0 && (
        <>
          <button
            type="button"
            className={ghost}
            aria-expanded={panel?.kind === "enroll"}
            onClick={() => {
              close();
              setPanel({ kind: "enroll" });
            }}
          >
            {t("console_people.programs.enroll")}
          </button>
          {panel?.kind === "enroll" && (
            <form
              className="mt-3"
              onSubmit={(event) => {
                event.preventDefault();
                form.post(programs.enrollUrl as string, {
                  preserveScroll: true,
                  onSuccess: close,
                });
              }}
            >
              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_people.programs.program")}
                </span>
                <select
                  className={field}
                  value={form.data.program_id}
                  onChange={(event) =>
                    form.setData("program_id", event.target.value)
                  }
                  required
                >
                  <option value="">
                    {t("console_people.programs.choose")}
                  </option>
                  {programs.availablePrograms.map((program) => (
                    <option key={program.value} value={program.value}>
                      {program.label}
                    </option>
                  ))}
                </select>
              </label>
              {reasonField}
              {errors}
              {actions(
                t("console_people.programs.submit_enroll"),
                form.data.program_id === "",
              )}
            </form>
          )}
        </>
      )}

      <h3 className="font-bold mt-6">
        {t("console_people.programs.teachers")}
      </h3>
      {programs.schedules.length === 0 && (
        <p className="text-sm my-2">
          {t("console_people.programs.no_teacher")}
        </p>
      )}
      <ul className="my-3 flex flex-col gap-3">
        {programs.schedules.map((schedule) => (
          <li key={schedule.id} className={box}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>
                <strong>{schedule.course}</strong> —{" "}
                {t("console_people.programs.teacher")}: {schedule.teacher}
                <br />
                <span className="text-sm">
                  {schedule.slots.join("، ")} ({schedule.duration_minutes}{" "}
                  {t("console_people.programs.minutes")})
                </span>
              </span>
              <span className="flex gap-2">
                {programs.changeUrl && (
                  <button
                    type="button"
                    className={ghost}
                    onClick={() => {
                      close();
                      setPanel({ kind: "change", id: schedule.id });
                      form.setData("schedule_id", schedule.id);
                      loadTeachers(schedule.course_id);
                    }}
                  >
                    {t("console_people.programs.change_teacher")}
                  </button>
                )}
                {programs.removeUrl && (
                  <button
                    type="button"
                    className={ghost + " border-red-700 text-red-700"}
                    onClick={() => {
                      close();
                      setPanel({ kind: "remove", id: schedule.id });
                      form.setData("schedule_id", schedule.id);
                    }}
                  >
                    {t("console_people.programs.remove_teacher")}
                  </button>
                )}
              </span>
            </div>

            {panel?.kind === "change" && panel.id === schedule.id && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.put(programs.changeUrl as string, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm">
                  {t("console_people.programs.confirm_change")}
                </p>
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.programs.new_teacher")}
                  </span>
                  <select
                    className={field}
                    value={form.data.staff_profile_id}
                    onChange={(event) =>
                      form.setData("staff_profile_id", event.target.value)
                    }
                    required
                  >
                    <option value="">
                      {loading
                        ? t("console_people.programs.loading")
                        : t("console_people.programs.choose")}
                    </option>
                    {teachers
                      .filter((option) => option.value !== schedule.teacher_id)
                      .map((teacher) => (
                        <option key={teacher.value} value={teacher.value}>
                          {teacher.label}
                        </option>
                      ))}
                  </select>
                </label>
                {reasonField}
                {errors}
                {actions(
                  t("console_people.programs.submit_change"),
                  form.data.staff_profile_id === "",
                )}
              </form>
            )}

            {panel?.kind === "remove" && panel.id === schedule.id && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.delete(programs.removeUrl as string, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm">
                  {t("console_people.programs.confirm_remove")}
                </p>
                {reasonField}
                {errors}
                {actions(t("console_people.programs.submit_remove"))}
              </form>
            )}
          </li>
        ))}
      </ul>

      {programs.assignUrl && programs.assignableCourses.length > 0 && (
        <>
          <button
            type="button"
            className={ghost}
            aria-expanded={panel?.kind === "assign"}
            onClick={() => {
              close();
              setPanel({ kind: "assign" });
            }}
          >
            {t("console_people.programs.assign_teacher")}
          </button>
          {panel?.kind === "assign" && (
            <form
              className="mt-3"
              onSubmit={(event) => {
                event.preventDefault();
                form.post(programs.assignUrl as string, {
                  preserveScroll: true,
                  onSuccess: close,
                });
              }}
            >
              <p className="text-sm">
                {t("console_people.programs.confirm_assign")}
              </p>
              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_people.programs.course")}
                </span>
                <select
                  className={field}
                  value={form.data.course_id}
                  onChange={(event) => {
                    form.setData("course_id", event.target.value);
                    loadTeachers(event.target.value);
                  }}
                  required
                >
                  <option value="">
                    {t("console_people.programs.choose")}
                  </option>
                  {programs.assignableCourses.map((course) => (
                    <option key={course.value} value={course.value}>
                      {course.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_people.programs.teacher")}
                </span>
                <select
                  className={field}
                  value={form.data.staff_profile_id}
                  onChange={(event) =>
                    form.setData("staff_profile_id", event.target.value)
                  }
                  required
                  disabled={form.data.course_id === ""}
                >
                  <option value="">
                    {loading
                      ? t("console_people.programs.loading")
                      : t("console_people.programs.choose")}
                  </option>
                  {teachers.map((teacher) => (
                    <option key={teacher.value} value={teacher.value}>
                      {teacher.label}
                    </option>
                  ))}
                </select>
              </label>
              <div className="flex flex-wrap gap-3">
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.programs.weekday")}
                  </span>
                  <select
                    className={field}
                    value={slot.weekday}
                    onChange={(event) =>
                      form.setData("weekly_slots", [
                        {
                          weekday: Number(event.target.value),
                          start_time: slot.start_time,
                        },
                      ])
                    }
                  >
                    {[0, 1, 2, 3, 4, 5, 6].map((day) => (
                      <option key={day} value={day}>
                        {t("console_people.teaching.weekday_" + day)}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.programs.time")}
                  </span>
                  <input
                    type="time"
                    className={field}
                    value={slot.start_time}
                    onChange={(event) =>
                      form.setData("weekly_slots", [
                        {
                          weekday: slot.weekday,
                          start_time: event.target.value,
                        },
                      ])
                    }
                    required
                  />
                </label>
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.programs.duration")}
                  </span>
                  <select
                    className={field}
                    value={form.data.duration_minutes}
                    onChange={(event) =>
                      form.setData("duration_minutes", Number(event.target.value))
                    }
                  >
                    {programs.durations.map((duration) => (
                      <option key={duration} value={duration}>
                        {duration}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.programs.starts_on")}
                  </span>
                  <input
                    type="date"
                    className={field}
                    value={form.data.starts_on}
                    onChange={(event) =>
                      form.setData("starts_on", event.target.value)
                    }
                    required
                  />
                </label>
              </div>
              {reasonField}
              {errors}
              {actions(
                t("console_people.programs.submit_assign"),
                form.data.course_id === "" || form.data.staff_profile_id === "",
              )}
            </form>
          )}
        </>
      )}
    </section>
  );
}
