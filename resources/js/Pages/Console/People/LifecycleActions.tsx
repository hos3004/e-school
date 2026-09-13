import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type LifecycleEnrollment = {
  id: string;
  program: string;
  freezeUrl: string;
};

export type LifecycleData = {
  archiveUrl: string | null;
  restoreUrl: string | null;
  terminateUrl: string | null;
  enrollments: LifecycleEnrollment[];
};

type ActionKey = "freeze" | "archive" | "restore" | "terminate";

export default function LifecycleActions({
  lifecycle,
}: {
  lifecycle: LifecycleData;
}) {
  const t = useI18n();
  const [open, setOpen] = useState<ActionKey | null>(null);
  const form = useForm({
    reason: "",
    enrollment: lifecycle.enrollments[0]?.id ?? "",
  });

  const actions: { key: ActionKey; url: string | null; danger: boolean }[] = [
    {
      key: "freeze",
      url: lifecycle.enrollments[0]?.freezeUrl ?? null,
      danger: false,
    },
    { key: "archive", url: lifecycle.archiveUrl, danger: true },
    { key: "restore", url: lifecycle.restoreUrl, danger: false },
    { key: "terminate", url: lifecycle.terminateUrl, danger: true },
  ];
  const available = actions.filter((action) => action.url !== null);

  if (available.length === 0) {
    return null;
  }

  const submit = (key: ActionKey) => {
    const url =
      key === "freeze"
        ? lifecycle.enrollments.find(
            (enrollment) => enrollment.id === form.data.enrollment,
          )?.freezeUrl
        : actions.find((action) => action.key === key)?.url;

    if (!url) {
      return;
    }

    form.put(url, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset("reason");
        setOpen(null);
      },
    });
  };

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">
        {t("console_people.lifecycle.title")}
      </h2>
      <p className="text-sm my-2">{t("console_people.lifecycle.hint")}</p>
      <div className="flex flex-wrap gap-2 my-4">
        {available.map((action) => (
          <button
            key={action.key}
            type="button"
            aria-expanded={open === action.key}
            onClick={() => {
              form.clearErrors();
              form.reset("reason");
              setOpen(open === action.key ? null : action.key);
            }}
            className={
              "rounded-lg px-4 py-2 border " +
              (action.danger
                ? "border-red-700 text-red-700"
                : "border-[var(--line)]") +
              (open === action.key ? " bg-[var(--surface-2,#f3f4f6)]" : "")
            }
          >
            {t("console_people.lifecycle." + action.key)}
          </button>
        ))}
      </div>

      {open !== null && (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            submit(open);
          }}
        >
          <p className="text-sm my-2">
            {t("console_people.lifecycle.confirm_" + open)}
          </p>

          {open === "freeze" && lifecycle.enrollments.length > 1 && (
            <label className="block my-3">
              <span className="block mb-2">
                {t("console_people.lifecycle.program")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.enrollment}
                onChange={(event) =>
                  form.setData("enrollment", event.target.value)
                }
                required
              >
                {lifecycle.enrollments.map((enrollment) => (
                  <option key={enrollment.id} value={enrollment.id}>
                    {enrollment.program}
                  </option>
                ))}
              </select>
            </label>
          )}

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_people.lifecycle.reason")}
            </span>
            <textarea
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.reason}
              onChange={(event) => form.setData("reason", event.target.value)}
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
              disabled={form.processing || form.data.reason.trim().length < 3}
            >
              {t("console_people.lifecycle.submit_" + open)}
            </button>
            <button
              type="button"
              className="rounded-lg border border-[var(--line)] px-4 py-2"
              onClick={() => {
                form.clearErrors();
                form.reset("reason");
                setOpen(null);
              }}
            >
              {t("console_people.lifecycle.cancel")}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
