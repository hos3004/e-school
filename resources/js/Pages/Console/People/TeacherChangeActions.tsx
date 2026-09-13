import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type IndividualSchedule = {
  id: string;
  course_id: string;
  course: string;
  teacher_id: string;
  teacher: string;
};

export type TeacherChangeData = {
  optionsUrl: string;
  updateUrl: string;
  schedules: IndividualSchedule[];
};

type TeacherOption = { value: string; label: string };

export default function TeacherChangeActions({
  teacherChange,
}: {
  teacherChange: TeacherChangeData;
}) {
  const t = useI18n();
  const [openId, setOpenId] = useState<string | null>(null);
  const [teachers, setTeachers] = useState<TeacherOption[]>([]);
  const [loading, setLoading] = useState(false);
  const form = useForm({ schedule_id: "", staff_profile_id: "", reason: "" });

  const open = (schedule: IndividualSchedule) => {
    form.clearErrors();
    form.setData({
      schedule_id: schedule.id,
      staff_profile_id: "",
      reason: "",
    });
    setOpenId(schedule.id);
    setTeachers([]);
    setLoading(true);
    fetch(
      teacherChange.optionsUrl +
        "?" +
        new URLSearchParams({ course_id: schedule.course_id }),
      { headers: { Accept: "application/json" } },
    )
      .then((response) => (response.ok ? response.json() : null))
      .then((data) =>
        setTeachers(
          (data?.teachers ?? []).filter(
            (option: TeacherOption) => option.value !== schedule.teacher_id,
          ),
        ),
      )
      .catch(() => undefined)
      .finally(() => setLoading(false));
  };

  const close = () => {
    form.clearErrors();
    form.reset();
    setOpenId(null);
    setTeachers([]);
  };

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">
        {t("console_people.teacher_change.title")}
      </h2>
      <p className="text-sm my-2">{t("console_people.teacher_change.hint")}</p>

      <ul className="my-4 flex flex-col gap-3">
        {teacherChange.schedules.map((schedule) => (
          <li
            key={schedule.id}
            className="rounded-lg border border-[var(--line)] p-3"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>
                <strong>{schedule.course}</strong>
                {" — "}
                {t("console_people.teacher_change.current")}: {schedule.teacher}
              </span>
              <button
                type="button"
                aria-expanded={openId === schedule.id}
                className="rounded-lg border border-[var(--line)] px-4 py-2"
                onClick={() =>
                  openId === schedule.id ? close() : open(schedule)
                }
              >
                {t("console_people.teacher_change.change")}
              </button>
            </div>

            {openId === schedule.id && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.put(teacherChange.updateUrl, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm my-2">
                  {t("console_people.teacher_change.confirm")}
                </p>
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.teacher_change.new_teacher")}
                  </span>
                  <select
                    className="w-full rounded-lg border border-[var(--line)] p-3"
                    value={form.data.staff_profile_id}
                    onChange={(event) =>
                      form.setData("staff_profile_id", event.target.value)
                    }
                    required
                  >
                    <option value="">
                      {loading
                        ? t("console_people.teacher_change.loading")
                        : t("console_people.teacher_change.choose")}
                    </option>
                    {teachers.map((teacher) => (
                      <option key={teacher.value} value={teacher.value}>
                        {teacher.label}
                      </option>
                    ))}
                  </select>
                </label>
                {!loading && teachers.length === 0 && (
                  <p className="text-sm">
                    {t("console_people.teacher_change.none")}
                  </p>
                )}
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.teacher_change.reason")}
                  </span>
                  <textarea
                    className="w-full rounded-lg border border-[var(--line)] p-3"
                    value={form.data.reason}
                    onChange={(event) =>
                      form.setData("reason", event.target.value)
                    }
                    required
                    minLength={3}
                    maxLength={1000}
                    rows={3}
                  />
                </label>
                {Object.values(form.errors).map((error, index) => (
                  <p key={index} role="alert" className="text-red-700">
                    {error}
                  </p>
                ))}
                <div className="flex gap-2">
                  <button
                    className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50"
                    disabled={
                      form.processing ||
                      form.data.staff_profile_id === "" ||
                      form.data.reason.trim().length < 3
                    }
                  >
                    {t("console_people.teacher_change.submit")}
                  </button>
                  <button
                    type="button"
                    className="rounded-lg border border-[var(--line)] px-4 py-2"
                    onClick={close}
                  >
                    {t("console_people.teacher_change.cancel")}
                  </button>
                </div>
              </form>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
