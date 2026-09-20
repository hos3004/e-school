import { useForm, Link } from "@inertiajs/react";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import AudienceMessaging, {
  type AudienceMessagingData,
} from "@/Components/Console/AudienceMessaging";
import WhatsappCampaigns, {
  type CampaignsData,
} from "@/Components/Console/WhatsappCampaigns";
import { WhatsappTemplateSettings } from "@/Pages/Console/SettingsEditors";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

type Connection = {
  api_url: string;
  instance_id: string;
  configured: boolean;
  enabled: boolean;
  webhook_registered: boolean;
  state: string;
  version: string;
};

type TemplateRow = {
  event_key: string;
  locale: string;
  label: string;
  parameters: string[];
  original: { subject: string | null; body: string };
  custom: { id: string; subject: string | null; body: string } | null;
};

type LogRow = {
  id: string;
  event_name: string;
  category: string;
  status: string;
  automatic: boolean;
  created_at: string | null;
  sent_at: string | null;
  failure_reason: string | null;
  subject: string | null;
  body: string | null;
};

type Props = {
  connection: Connection;
  channelEnabled: boolean;
  stats: Record<string, number>;
  recent: LogRow[];
  templates: TemplateRow[];
  messaging: AudienceMessagingData | null;
  campaigns: CampaignsData | null;
  automaticNotice: string;
  abilities: {
    send: boolean;
    templates: boolean;
    toggle: boolean;
    settings: boolean;
  };
  urls: {
    toggle: string;
    preview: string;
    settings: string;
    webhook: string;
    notifications: string;
  };
  displayTimezone: string;
};

const STAT_KEYS = [
  "sent",
  "queued",
  "sending",
  "failed",
  "cancelled",
  "suppressed",
] as const;

export default function Whatsapp({
  connection,
  channelEnabled,
  stats,
  recent,
  templates,
  messaging,
  campaigns,
  automaticNotice,
  abilities,
  urls,
  displayTimezone,
}: Props) {
  const t = useI18n();
  const [tab, setTab] = useState<
    "status" | "templates" | "compose" | "campaigns" | "log" | "settings"
  >("status");

  const toggleForm = useForm({ active: !channelEnabled, reason: "" });

  const submitToggle = (event: React.FormEvent) => {
    event.preventDefault();

    if (channelEnabled && !window.confirm(t("console_whatsapp.toggle.confirm_pause"))) {
      return;
    }

    toggleForm.transform((data) => ({ ...data, active: !channelEnabled }));
    toggleForm.post(urls.toggle, {
      preserveScroll: true,
      onSuccess: () => toggleForm.reset("reason"),
    });
  };

  const tabs = [
    "status",
    "compose",
    ...(campaigns ? (["campaigns"] as const) : []),
    "templates",
    "log",
    "settings",
  ] as const;

  return (
    <ConsoleLayout
      section="settings"
      title={t("console_whatsapp.title")}
      description={t("console_whatsapp.subtitle")}
    >
      <nav
        aria-label={t("console_whatsapp.title")}
        className="flex flex-wrap gap-2 my-4"
      >
        {tabs.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            aria-current={tab === key ? "page" : undefined}
            className={
              "rounded-lg border border-[var(--line)] px-4 py-2" +
              (tab === key ? " bg-[var(--brand)] text-white" : "")
            }
          >
            {t("console_whatsapp.tabs." + key)}
          </button>
        ))}
      </nav>

      {tab === "status" && (
        <>
          <section
            className="rounded-xl border border-[var(--line)] bg-white p-5 my-5"
            aria-live="polite"
          >
            <h2 className="font-bold text-lg">
              {channelEnabled
                ? t("console_whatsapp.status.channel_on")
                : t("console_whatsapp.status.channel_off")}
            </h2>
            <p className="text-sm">
              {channelEnabled
                ? t("console_whatsapp.status.channel_on_hint")
                : t("console_whatsapp.status.channel_off_hint")}
            </p>

            {abilities.toggle && (
              <form onSubmit={submitToggle} className="mt-4">
                <label className="block">
                  <span className="block mb-2">
                    {t("console_whatsapp.toggle.reason")}
                  </span>
                  <textarea
                    className="w-full rounded-lg border border-[var(--line)] p-3"
                    rows={2}
                    minLength={3}
                    maxLength={500}
                    required
                    placeholder={t("console_whatsapp.toggle.reason_placeholder")}
                    value={toggleForm.data.reason}
                    onChange={(event) =>
                      toggleForm.setData("reason", event.target.value)
                    }
                  />
                </label>

                {Object.values(toggleForm.errors).map((error, index) => (
                  <p key={index} role="alert" className="text-red-700">
                    {error}
                  </p>
                ))}

                <button
                  className={
                    "rounded-lg px-4 py-2 text-white disabled:opacity-50 mt-3 " +
                    (channelEnabled ? "bg-red-700" : "bg-[var(--brand)]")
                  }
                  disabled={
                    toggleForm.processing ||
                    toggleForm.data.reason.trim().length < 3
                  }
                >
                  {channelEnabled
                    ? t("console_whatsapp.toggle.pause")
                    : t("console_whatsapp.toggle.resume")}
                </button>
              </form>
            )}
          </section>

          <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
            <h2 className="font-bold text-lg">
              {t("console_whatsapp.stats.title")}
            </h2>
            <dl className="grid gap-3 md:grid-cols-3 mt-3">
              {STAT_KEYS.map((key) => (
                <div key={key} className="rounded-lg border border-[var(--line)] p-3">
                  <dt className="text-sm">
                    {t("console_whatsapp.stats." + key)}
                  </dt>
                  <dd className="font-bold text-xl">{stats[key] ?? 0}</dd>
                </div>
              ))}
            </dl>
          </section>

          <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
            <h2 className="font-bold text-lg">
              {t("console_whatsapp.automatic.notice_title")}
            </h2>
            <p className="text-sm">
              {t("console_whatsapp.automatic.notice_hint")}
            </p>
            <pre className="whitespace-pre-wrap rounded-lg border border-[var(--line)] p-3 mt-2">
              {automaticNotice}
            </pre>
          </section>

          <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
            <h2 className="font-bold text-lg">
              {t("console_whatsapp.other_entries.title")}
            </h2>
            <p className="text-sm">
              {t("console_whatsapp.other_entries.hint")}
            </p>
            <Link className="underline mt-2 inline-block" href={urls.notifications}>
              {t("console_whatsapp.other_entries.messages_link")}
            </Link>
          </section>
        </>
      )}

      {tab === "compose" && (
        <>
          {!channelEnabled && (
            <p role="alert" className="text-amber-800 my-3">
              {t("console_whatsapp.preview.channel_off_warning")}
            </p>
          )}
          {messaging ? (
            <AudienceMessaging
              messaging={{ ...messaging, previewUrl: urls.preview }}
            />
          ) : (
            <p className="my-3">{t("console_whatsapp.log.empty")}</p>
          )}
        </>
      )}

      {tab === "campaigns" && campaigns && (
        <WhatsappCampaigns
          data={campaigns}
          channelEnabled={channelEnabled}
          displayTimezone={displayTimezone}
        />
      )}

      {tab === "templates" && (
        <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
          <h2 className="font-bold text-lg">
            {t("console_whatsapp.templates.title")}
          </h2>
          <p className="text-sm">{t("console_whatsapp.templates.hint")}</p>

          {abilities.templates ? (
            <WhatsappTemplateSettings templates={templates} />
          ) : (
            <p className="mt-3">{t("console_whatsapp.templates.empty")}</p>
          )}
        </section>
      )}

      {tab === "log" && (
        <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
          <h2 className="font-bold text-lg">
            {t("console_whatsapp.log.title")}
          </h2>
          <p className="text-sm">{t("console_whatsapp.log.hint")}</p>

          {recent.length === 0 ? (
            <p className="mt-3">{t("console_whatsapp.log.empty")}</p>
          ) : (
            <div className="overflow-x-auto mt-3">
              <table className="w-full text-start">
                <thead>
                  <tr>
                    <th scope="col" className="text-start p-2">
                      {t("console_whatsapp.log.event")}
                    </th>
                    <th scope="col" className="text-start p-2">
                      {t("console_whatsapp.log.status")}
                    </th>
                    <th scope="col" className="text-start p-2">
                      {t("console_whatsapp.log.created")}
                    </th>
                    <th scope="col" className="text-start p-2">
                      {t("console_whatsapp.log.reason")}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {recent.map((row) => (
                    <tr key={row.id} className="border-t border-[var(--line)]">
                      <td className="p-2 align-top">
                        {row.event_name}
                        <span className="text-sm block">
                          {row.automatic
                            ? t("console_whatsapp.automatic.badge")
                            : t("console_whatsapp.automatic.manual_badge")}
                        </span>
                      </td>
                      <td className="p-2 align-top">{row.status}</td>
                      <td className="p-2 align-top">
                        {row.created_at
                          ? formatDate(row.created_at, displayTimezone)
                          : "—"}
                      </td>
                      <td className="p-2 align-top">
                        {row.failure_reason ?? "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {tab === "settings" && (
        <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
          <h2 className="font-bold text-lg">
            {t("console_whatsapp.settings.title")}
          </h2>
          <p className="text-sm">{t("console_whatsapp.settings.hint")}</p>

          <dl className="grid gap-3 md:grid-cols-2 mt-3">
            <div>
              <dt className="text-sm">
                {t("console_whatsapp.status.instance")}
              </dt>
              <dd className="font-bold">{connection.instance_id || "—"}</dd>
            </div>
            <div>
              <dt className="text-sm">{t("console_whatsapp.status.api_url")}</dt>
              <dd className="font-bold">{connection.api_url}</dd>
            </div>
            <div>
              <dt className="text-sm">
                {t("console_whatsapp.status.provider_state")}
              </dt>
              <dd className="font-bold">
                {connection.state === "authorized"
                  ? t("console_whatsapp.status.authorized")
                  : t("console_whatsapp.status.not_authorized")}
              </dd>
            </div>
            <div>
              <dt className="text-sm">
                {connection.configured
                  ? t("console_whatsapp.status.token_set")
                  : t("console_whatsapp.status.token_missing")}
              </dt>
              <dd className="font-bold">
                {connection.webhook_registered
                  ? t("console_whatsapp.status.webhook_on")
                  : t("console_whatsapp.status.webhook_off")}
              </dd>
            </div>
          </dl>

          <Link className="underline mt-3 inline-block" href="/manage/settings">
            {t("console_whatsapp.settings.open")}
          </Link>
        </section>
      )}
    </ConsoleLayout>
  );
}
