import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import LearningEntryLayout from "@/Layouts/LearningEntryLayout";
import { Errors } from "@/Pages/Learning/shared";
import { useI18n } from "@/lib/i18n";
export default function LearningForgotPassword({
  status,
}: {
  status?: string;
}) {
  const t = useI18n();
  const form = useForm({ email: "" });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/forgot-password", { preserveScroll: true });
  }
  return (
    <LearningEntryLayout title={t("auth.forgot_password.title")}>
      <h2 id="entry-title">{t("auth.forgot_password.title")}</h2>
      <p>{t("auth.forgot_password.subtitle")}</p>
      {status && (
        <p role="status" className="lp-feedback">
          {status}
        </p>
      )}
      <form className="lp-form lp-auth-form" onSubmit={submit}>
        <Errors errors={form.errors} />
        <label className="lp-field">
          <span>{t("auth.forgot_password.email")}</span>
          <input
            type="email"
            name="email"
            autoFocus
            autoComplete="email"
            dir="ltr"
            required
            value={form.data.email}
            onChange={(e) => form.setData("email", e.target.value)}
            aria-invalid={Boolean(form.errors.email)}
          />
          <small>{t("learning.entry.recovery_hint")}</small>
        </label>
        <button className="lp-btn" type="submit" disabled={form.processing}>
          {t(
            form.processing
              ? "actions.processing"
              : "auth.forgot_password.submit",
          )}
        </button>
        <Link className="lp-recovery" href="/login">
          {t("auth.back_to_login")}
        </Link>
      </form>
    </LearningEntryLayout>
  );
}
