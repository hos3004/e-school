import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

type Choice = { value: string; label: string };

export type TeacherQualification = {
  id: string;
  course: string;
  program: string;
};

export type TeacherQualificationsData = {
  current: TeacherQualification[];
  availableCourses: Choice[];
  assignUrl: string | null;
  revokeUrl: string | null;
};

type Panel = { kind: "assign" } | { kind: "revoke"; id: string };

const box = "rounded-lg border border-[var(--line)] p-3";
const field = "w-full rounded-lg border border-[var(--line)] p-3";
const ghost = "rounded-lg border border-[var(--line)] px-4 py-2";
const primary =
  "rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50";

export default function TeacherQualifications({
  qualifications,
}: {
  qualifications: TeacherQualificationsData;
}) {
  const t = useI18n();
  const [panel, setPanel] = useState<Panel | null>(null);
  const form = useForm({
    course_ids: [] as string[],
    course_id: "",
    reason: "",
  });

  const close = () => {
    form.clearErrors();
    form.reset();
    setPanel(null);
  };

  const reasonField = (
    <label className="block my-3">
      <span className="block mb-2">
        {t("console_people.qualifications.reason")}
      </span>
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

  const errors = Object.values(form.errors).map((error, index) => (
    <p key={index} role="alert" className="text-red-700">
      {error}
    </p>
  ));

  const actions = (label: string, disabled = false) => (
    <div className="flex gap-2">
      <button
        className={primary}
        disabled={
          form.processing || form.data.reason.trim().length < 3 || disabled
        }
      >
        {label}
      </button>
      <button type="button" className={ghost} onClick={close}>
        {t("console_people.qualifications.cancel")}
      </button>
    </div>
  );

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">
        {t("console_people.qualifications.title")}
      </h2>
      <p className="text-sm my-2">{t("console_people.qualifications.hint")}</p>

      {qualifications.current.length === 0 && (
        <p className="text-sm my-2">{t("console_people.qualifications.none")}</p>
      )}
      <ul className="my-3 flex flex-col gap-3">
        {qualifications.current.map((row) => (
          <li key={row.id} className={box}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>
                <strong>{row.course}</strong> — {row.program}
              </span>
              {qualifications.revokeUrl && (
                <button
                  type="button"
                  className={ghost + " border-red-700 text-red-700"}
                  onClick={() => {
                    close();
                    setPanel({ kind: "revoke", id: row.id });
                    form.setData("course_id", row.id);
                  }}
                >
                  {t("console_people.qualifications.revoke")}
                </button>
              )}
            </div>
            {panel?.kind === "revoke" && panel.id === row.id && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.delete(qualifications.revokeUrl as string, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm">
                  {t("console_people.qualifications.confirm_revoke")}
                </p>
                {reasonField}
                {errors}
                {actions(t("console_people.qualifications.submit_revoke"))}
              </form>
            )}
          </li>
        ))}
      </ul>

      {qualifications.assignUrl &&
        qualifications.availableCourses.length > 0 && (
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
              {t("console_people.qualifications.assign")}
            </button>
            {panel?.kind === "assign" && (
              <form
                className="mt-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  form.post(qualifications.assignUrl as string, {
                    preserveScroll: true,
                    onSuccess: close,
                  });
                }}
              >
                <p className="text-sm">
                  {t("console_people.qualifications.confirm_assign")}
                </p>
                <fieldset className="my-3">
                  <legend className="mb-2">
                    {t("console_people.qualifications.courses")}
                  </legend>
                  <div className="max-h-72 overflow-y-auto rounded-lg border border-[var(--line)] p-3 flex flex-col gap-2">
                    {qualifications.availableCourses.map((course) => (
                      <label
                        key={course.value}
                        className="flex items-start gap-3"
                      >
                        <input
                          type="checkbox"
                          className="mt-1"
                          checked={form.data.course_ids.includes(course.value)}
                          onChange={(event) =>
                            form.setData(
                              "course_ids",
                              event.target.checked
                                ? [...form.data.course_ids, course.value]
                                : form.data.course_ids.filter(
                                    (value) => value !== course.value,
                                  ),
                            )
                          }
                        />
                        {course.label}
                      </label>
                    ))}
                  </div>
                </fieldset>
                {reasonField}
                {errors}
                {actions(
                  t("console_people.qualifications.submit_assign"),
                  form.data.course_ids.length === 0,
                )}
              </form>
            )}
          </>
        )}
    </section>
  );
}
