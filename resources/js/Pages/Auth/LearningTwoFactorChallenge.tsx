import { useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import LearningEntryLayout from "@/Layouts/LearningEntryLayout";
import { Errors } from "@/Pages/Learning/shared";
import { useI18n } from "@/lib/i18n";
export default function LearningTwoFactorChallenge() {
  const t = useI18n();
  const [recovery, setRecovery] = useState(false);
  const form = useForm({ code: "", recovery_code: "" });
  const key = recovery ? "recovery_code" : "code";
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/two-factor-challenge", {
      preserveScroll: "errors",
      onFinish: () => form.reset(),
    });
  }
  return (
    <LearningEntryLayout title={t("auth.two_factor.title")}>
      <h2 id="entry-title">{t("auth.two_factor.title")}</h2>
      <p>
        {t(
          recovery
            ? "auth.two_factor.recovery_hint"
            : "auth.two_factor.code_hint",
        )}
      </p>
      <form className="lp-form lp-auth-form" onSubmit={submit}>
        <Errors errors={form.errors} />
        <label className="lp-field">
          <span>{t(`auth.two_factor.${key}`)}</span>
          <input
            autoFocus
            key={key}
            name={key}
            autoComplete="one-time-code"
            inputMode={recovery ? "text" : "numeric"}
            dir="ltr"
            required
            value={form.data[key]}
            onChange={(e) => form.setData(key, e.target.value)}
          />
        </label>
        <button type="submit" className="lp-btn" disabled={form.processing}>
          {t(form.processing ? "actions.processing" : "learning.entry.verify")}
        </button>
        <button
          className="lp-recovery"
          type="button"
          onClick={() => {
            setRecovery(!recovery);
            form.reset();
            form.clearErrors();
          }}
        >
          {t(
            recovery
              ? "learning.entry.use_code"
              : "learning.entry.use_recovery",
          )}
        </button>
      </form>
    </LearningEntryLayout>
  );
}
