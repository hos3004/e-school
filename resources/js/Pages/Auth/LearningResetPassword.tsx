import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import LearningEntryLayout from "@/Layouts/LearningEntryLayout";
import { Errors } from "@/Pages/Learning/shared";
import { useI18n } from "@/lib/i18n";
export default function LearningResetPassword({
  token,
  email = "",
}: {
  token: string;
  email?: string;
}) {
  const t = useI18n();
  const form = useForm({
    token,
    email,
    password: "",
    password_confirmation: "",
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/reset-password", {
      preserveScroll: "errors",
      onFinish: () => form.reset("password", "password_confirmation"),
    });
  }
  return (
    <LearningEntryLayout title={t("auth.reset_password.title")}>
      <h2 id="entry-title">{t("auth.reset_password.title")}</h2>
      <p>{t("auth.reset_password.subtitle")}</p>
      <form className="lp-form lp-auth-form" onSubmit={submit}>
        <Errors errors={form.errors} />
        {(["email", "password", "password_confirmation"] as const).map(
          (field) => (
            <label className="lp-field" key={field}>
              <span>
                {t(
                  field === "email"
                    ? "auth.forgot_password.email"
                    : field === "password"
                      ? "learning.password"
                      : "learning.password_confirmation",
                )}
              </span>
              <input
                name={field}
                autoComplete={field === "email" ? "username" : "new-password"}
                type={field === "email" ? "email" : "password"}
                dir="ltr"
                required
                value={form.data[field]}
                onChange={(e) => form.setData(field, e.target.value)}
                aria-invalid={Boolean(form.errors[field])}
              />
            </label>
          ),
        )}
        <button className="lp-btn" type="submit" disabled={form.processing}>
          {t(
            form.processing
              ? "actions.processing"
              : "auth.reset_password.submit",
          )}
        </button>
        <Link className="lp-recovery" href="/login">
          {t("auth.back_to_login")}
        </Link>
      </form>
    </LearningEntryLayout>
  );
}
