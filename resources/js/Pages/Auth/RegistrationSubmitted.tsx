import { useState } from "react";
import { Check, Copy } from "lucide-react";
import PublicRegistrationLayout from "@/Layouts/PublicRegistrationLayout";
import { Head } from "@inertiajs/react";

import Button from "@/Components/Button";
import GuestLayout from "@/Layouts/GuestLayout";
import { useI18n } from "@/lib/i18n";

interface Props {
  applicationId?: string | null;
  consoleRegistration?: boolean;
}

export default function RegistrationSubmitted({
  applicationId,
  consoleRegistration = false,
}: Props) {
  const t = useI18n();
  const [copyState, setCopyState] = useState("");
  async function copyReference() {
    if (!applicationId) return;
    try {
      await navigator.clipboard.writeText(applicationId);
      setCopyState("copied");
    } catch {
      setCopyState("copy_failed");
    }
  }
  if (consoleRegistration)
    return (
      <PublicRegistrationLayout>
        <Head title={t("auth.register.submitted_title")} />
        <section className="pr-result-card">
          <span className="pr-result-icon">
            <Check size={32} />
          </span>
          <h1>{t("auth.register.submitted_title")}</h1>
          <p>{t("auth.register.submitted_description")}</p>
          {applicationId && (
            <div className="pr-reference">
              <p>{t("auth.register.submitted_reference")}</p>
              <strong dir="ltr">{applicationId}</strong>
              <button type="button" className="pr-copy" onClick={copyReference}>
                <Copy
                  size={14}
                  style={{ display: "inline", marginInlineEnd: 7 }}
                />
                {t("public_registration.copy")}
              </button>
              <span className="pr-copy-state" role="status">
                {copyState && t("public_registration." + copyState)}
              </span>
            </div>
          )}
          <div className="pr-result-actions">
            {applicationId && (
              <Button
                as="link"
                href={"/register/status/" + encodeURIComponent(applicationId)}
              >
                {t("auth.register.track_application")}
              </Button>
            )}
            <Button as="link" href="/login" variant="secondary">
              {t("auth.back_to_login")}
            </Button>
          </div>
          <div className="pr-result-next">
            {[1, 2, 3].map((step) => (
              <div key={step}>
                <strong>
                  <span dir="ltr">0{step}</span> ·{" "}
                  {t("public_registration.next_" + step)}
                </strong>
                <p>{t("public_registration.next_" + step + "_hint")}</p>
              </div>
            ))}
          </div>
        </section>
      </PublicRegistrationLayout>
    );

  return (
    <GuestLayout>
      <Head title={t("auth.register.submitted_title")} />

      <div className="text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-[var(--radius-lg)] border border-[color:var(--success)]/25 bg-[var(--success-soft)] text-[var(--success)]">
          <svg
            className="size-8"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
        <h1 className="mt-5 text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">
          {t("auth.register.submitted_title")}
        </h1>
        <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty]">
          {t("auth.register.submitted_description")}
        </p>

        {applicationId ? (
          <div className="mt-6 rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface-subtle)] p-4 text-center">
            <p className="text-xs text-[var(--ink-muted)]">
              {t("auth.register.submitted_reference")}
            </p>
            <p className="mt-1 select-all font-mono text-lg font-semibold text-[var(--brand-strong)]">
              {applicationId}
            </p>
          </div>
        ) : null}

        <div className="mt-8 space-y-3">
          {applicationId ? (
            <Button
              as="link"
              fullWidth
              href={`/register/status/${applicationId}`}
            >
              {t("auth.register.track_application")}
            </Button>
          ) : null}

          <Button as="link" fullWidth href="/login" variant="ghost">
            {t("auth.back_to_login")}
          </Button>
        </div>
      </div>
    </GuestLayout>
  );
}
