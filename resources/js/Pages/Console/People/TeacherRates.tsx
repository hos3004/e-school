import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

type Choice = { value: string; label: string };

export type TeacherRate = {
  id: string;
  scope: string;
  target: string;
  amount: string;
  effective_from: string;
  effective_to: string | null;
  active: boolean;
};

export type TeacherRatesData = {
  current: TeacherRate[];
  programs: Choice[];
  courses: Choice[];
  contract: { basis: string; currency: string } | null;
  storeUrl: string | null;
  today: string;
};

const box = "rounded-lg border border-[var(--line)] p-3";
const field = "w-full rounded-lg border border-[var(--line)] p-3";
const ghost = "rounded-lg border border-[var(--line)] px-4 py-2";
const primary =
  "rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50";

const scopes = ["default", "program", "course", "session_type"] as const;

export default function TeacherRates({ rates }: { rates: TeacherRatesData }) {
  const t = useI18n();
  const [open, setOpen] = useState(false);
  const form = useForm({
    scope: "default" as (typeof scopes)[number],
    program_id: "",
    course_id: "",
    session_type: "individual",
    amount_major: "",
    effective_from: rates.today,
    reason: "",
  });

  const close = () => {
    form.clearErrors();
    form.reset();
    setOpen(false);
  };

  const errors = Object.values(form.errors).map((error, index) => (
    <p key={index} role="alert" className="text-red-700">
      {error}
    </p>
  ));

  const incomplete =
    form.data.amount_major.trim() === "" ||
    (form.data.scope === "program" && form.data.program_id === "") ||
    (form.data.scope === "course" && form.data.course_id === "");

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">{t("console_people.rates.title")}</h2>
      <p className="text-sm my-2">{t("console_people.rates.hint")}</p>

      {rates.contract === null ? (
        <p className="text-sm my-2">{t("console_people.rates.no_contract")}</p>
      ) : (
        <p className="text-sm my-2">
          {t("console_people.rates.contract_basis")}: {rates.contract.basis}
        </p>
      )}

      {rates.current.length === 0 && (
        <p className="text-sm my-2">{t("console_people.rates.none")}</p>
      )}
      <ul className="my-3 flex flex-col gap-3">
        {rates.current.map((rate) => (
          <li key={rate.id} className={box}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>
                <strong>{rate.amount}</strong> — {rate.scope}: {rate.target}
                <br />
                <span className="text-sm">
                  {t("console_people.rates.effective_from")}{" "}
                  {rate.effective_from}
                  {rate.effective_to
                    ? " — " +
                      t("console_people.rates.effective_to") +
                      " " +
                      rate.effective_to
                    : ""}
                </span>
              </span>
              <span className="text-sm">
                {rate.active
                  ? t("console_people.rates.active")
                  : t("console_people.rates.ended")}
              </span>
            </div>
          </li>
        ))}
      </ul>

      {rates.storeUrl && rates.contract !== null && (
        <>
          <button
            type="button"
            className={ghost}
            aria-expanded={open}
            onClick={() => (open ? close() : setOpen(true))}
          >
            {t("console_people.rates.add")}
          </button>
          {open && (
            <form
              className="mt-3"
              onSubmit={(event) => {
                event.preventDefault();
                form.post(rates.storeUrl as string, {
                  preserveScroll: true,
                  onSuccess: close,
                });
              }}
            >
              <p className="text-sm">{t("console_people.rates.confirm")}</p>
              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_people.rates.scope")}
                </span>
                <select
                  className={field}
                  value={form.data.scope}
                  onChange={(event) =>
                    form.setData(
                      "scope",
                      event.target.value as (typeof scopes)[number],
                    )
                  }
                >
                  {scopes.map((scope) => (
                    <option key={scope} value={scope}>
                      {t("console_people.rates.scope_" + scope)}
                    </option>
                  ))}
                </select>
              </label>

              {form.data.scope === "program" && (
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.rates.program")}
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
                      {t("console_people.rates.choose")}
                    </option>
                    {rates.programs.map((program) => (
                      <option key={program.value} value={program.value}>
                        {program.label}
                      </option>
                    ))}
                  </select>
                </label>
              )}

              {form.data.scope === "course" && (
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.rates.course")}
                  </span>
                  <select
                    className={field}
                    value={form.data.course_id}
                    onChange={(event) =>
                      form.setData("course_id", event.target.value)
                    }
                    required
                  >
                    <option value="">
                      {t("console_people.rates.choose")}
                    </option>
                    {rates.courses.map((course) => (
                      <option key={course.value} value={course.value}>
                        {course.label}
                      </option>
                    ))}
                  </select>
                </label>
              )}

              {form.data.scope === "session_type" && (
                <label className="block my-3">
                  <span className="block mb-2">
                    {t("console_people.rates.session_type")}
                  </span>
                  <select
                    className={field}
                    value={form.data.session_type}
                    onChange={(event) =>
                      form.setData("session_type", event.target.value)
                    }
                  >
                    <option value="individual">
                      {t("session_pay.individual")}
                    </option>
                    <option value="group">{t("session_pay.group")}</option>
                  </select>
                </label>
              )}

              <div className="flex flex-wrap gap-3">
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.rates.amount")}
                    {rates.contract ? " (" + rates.contract.currency + ")" : ""}
                  </span>
                  <input
                    type="number"
                    className={field}
                    value={form.data.amount_major}
                    onChange={(event) =>
                      form.setData("amount_major", event.target.value)
                    }
                    min="0.01"
                    step="0.01"
                    required
                  />
                </label>
                <label className="block my-1">
                  <span className="block mb-2">
                    {t("console_people.rates.effective_from")}
                  </span>
                  <input
                    type="date"
                    className={field}
                    value={form.data.effective_from}
                    onChange={(event) =>
                      form.setData("effective_from", event.target.value)
                    }
                    required
                  />
                </label>
              </div>

              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_people.rates.reason")}
                </span>
                <textarea
                  className={field}
                  value={form.data.reason}
                  onChange={(event) =>
                    form.setData("reason", event.target.value)
                  }
                  required
                  minLength={3}
                  maxLength={1000}
                  rows={2}
                />
              </label>
              {errors}
              <div className="flex gap-2">
                <button
                  className={primary}
                  disabled={
                    form.processing ||
                    form.data.reason.trim().length < 3 ||
                    incomplete
                  }
                >
                  {t("console_people.rates.submit")}
                </button>
                <button type="button" className={ghost} onClick={close}>
                  {t("console_people.rates.cancel")}
                </button>
              </div>
            </form>
          )}
        </>
      )}
    </section>
  );
}
