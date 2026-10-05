import { Head, Link, router, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

type Account = { id: string; name: string; email: string };
type Current = {
  recipientType: "staff_email" | "custom_email";
  recipientUserId: string | null;
  customEmail: string | null;
  version: string;
};
type LastChangedBy = { name: string; at: string; reason: string | null } | null;
type Props = {
  current: Current | null;
  accounts: Account[];
  lastChangedBy: LastChangedBy;
  saveUrl: string;
  backUrl: string;
};

function Feedback({ errors }: { errors: Record<string, string> }) {
  const t = useI18n();
  return Object.keys(errors).length ? (
    <div className="console-feedback is-error" role="alert">
      {Object.values(errors).join(" · ")}
      {errors.version && (
        <button
          type="button"
          className="console-button"
          onClick={() => router.reload()}
        >
          {t("console_settings.reload")}
        </button>
      )}
    </div>
  ) : null;
}

export default function ReportSettings({
  current,
  accounts,
  lastChangedBy,
  saveUrl,
  backUrl,
}: Props) {
  const t = useI18n();
  const form = useForm({
    recipient_type: current?.recipientType ?? "custom_email",
    recipient_user_id: current?.recipientUserId ?? "",
    custom_email: current?.customEmail ?? "",
    reason: "",
    version: current?.version ?? "",
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(saveUrl, {
      preserveScroll: true,
      onSuccess: () => form.reset("reason"),
    });
  }

  return (
    <ConsoleLayout
      section="settings"
      title={t("console_reports.settings.title")}
      description={t("console_reports.settings.description")}
      actions={
        <Link href={backUrl} className="console-button">
          ← {t("console_reports.settings.back")}
        </Link>
      }
    >
      <Head title={t("console_reports.settings.title")} />

      <form onSubmit={submit} className="console-settings-editor">
        <div className="form-section">
          {!current && (
            <p className="console-settings-note">
              {t("console_reports.settings.not_configured")}
            </p>
          )}

          <div className="field-grid">
            <label className="console-settings-check span2">
              <input
                type="radio"
                name="recipient_type"
                checked={form.data.recipient_type === "staff_email"}
                onChange={() => form.setData("recipient_type", "staff_email")}
              />
              <span>{t("console_reports.settings.type_staff")}</span>
            </label>

            {form.data.recipient_type === "staff_email" && (
              <div className="field span2">
                <label htmlFor="recipient-user">
                  {t("console_reports.settings.select_user")}
                </label>
                {accounts.length === 0 ? (
                  <p className="console-settings-note">
                    {t("console_reports.settings.no_accounts")}
                  </p>
                ) : (
                  <select
                    id="recipient-user"
                    className="console-control"
                    value={form.data.recipient_user_id}
                    onChange={(event) =>
                      form.setData("recipient_user_id", event.target.value)
                    }
                  >
                    <option value="" disabled>
                      {t("console_reports.settings.select_user")}
                    </option>
                    {accounts.map((account) => (
                      <option key={account.id} value={account.id}>
                        {account.name} — {account.email}
                      </option>
                    ))}
                  </select>
                )}
              </div>
            )}

            <label className="console-settings-check span2">
              <input
                type="radio"
                name="recipient_type"
                checked={form.data.recipient_type === "custom_email"}
                onChange={() => form.setData("recipient_type", "custom_email")}
              />
              <span>{t("console_reports.settings.type_custom")}</span>
            </label>

            {form.data.recipient_type === "custom_email" && (
              <div className="field span2">
                <label htmlFor="recipient-custom-email">
                  {t("console_reports.settings.custom_email")}
                </label>
                <input
                  id="recipient-custom-email"
                  type="email"
                  dir="ltr"
                  className="console-control"
                  autoComplete="off"
                  value={form.data.custom_email}
                  onChange={(event) =>
                    form.setData("custom_email", event.target.value)
                  }
                />
              </div>
            )}

            <div className="field span2">
              <label htmlFor="recipient-reason">
                {t("console_reports.settings.reason")}
              </label>
              <input
                id="recipient-reason"
                className="console-control"
                required
                minLength={5}
                maxLength={500}
                placeholder={t(
                  "console_reports.settings.reason_placeholder",
                )}
                value={form.data.reason}
                onChange={(event) =>
                  form.setData("reason", event.target.value)
                }
              />
            </div>
          </div>

          {lastChangedBy && (
            <p className="console-settings-note" role="status">
              {t("console_reports.settings.last_changed_by")}:{" "}
              {lastChangedBy.name} ·{" "}
              {t("console_reports.settings.last_changed_at")}{" "}
              {formatDate(lastChangedBy.at, "UTC", "ar")}
              {lastChangedBy.reason && (
                <>
                  {" · "}
                  {t("console_reports.settings.last_changed_reason")}:{" "}
                  {lastChangedBy.reason}
                </>
              )}
            </p>
          )}
        </div>

        <Feedback errors={form.errors} />

        <div className="savebar">
          <button
            type="submit"
            className="console-button primary"
            disabled={form.processing}
          >
            {t(
              form.processing ? "console.saving" : "console_reports.settings.save",
            )}
          </button>
        </div>
      </form>
    </ConsoleLayout>
  );
}
