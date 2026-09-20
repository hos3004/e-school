import { router, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

export type CampaignRow = {
  id: string;
  name: string;
  body: string;
  status: "draft" | "running" | "completed" | "stopped";
  total_recipients: number;
  counts: {
    pending: number;
    sent: number;
    failed: number;
    cancelled: number;
    invalid: number;
  };
  delay_min_seconds: number;
  delay_max_seconds: number;
  media_count: number;
  created_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  media_expires_at: string | null;
};

export type CampaignsData = {
  items: CampaignRow[];
  limits: {
    maxRecipients: number;
    delayFloor: number;
    delayCeiling: number;
    defaultDelayMin: number;
    defaultDelayMax: number;
    maxMediaFiles: number;
    maxMediaKilobytes: number;
    mediaRetentionDays: number;
  };
  placeholders: string[];
  urls: { preview: string; store: string };
};

type RejectedRow = { name: string | null; phone_input: string; reason: string };

type PreviewResult = {
  accepted_count: number;
  rejected_count: number;
  duplicates: number;
  rejected: RejectedRow[];
  sample_text: string | null;
};

type Props = {
  data: CampaignsData;
  channelEnabled: boolean;
  displayTimezone: string;
};

export default function WhatsappCampaigns({
  data,
  channelEnabled,
  displayTimezone,
}: Props) {
  const t = useI18n();
  const [preview, setPreview] = useState<PreviewResult | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [previewFailed, setPreviewFailed] = useState(false);
  const [openProblems, setOpenProblems] = useState<string | null>(null);

  const form = useForm<{
    name: string;
    body: string;
    reason: string;
    recipients_text: string;
    recipients_file: File | null;
    delay_min_seconds: number;
    delay_max_seconds: number;
    media: File[];
  }>({
    name: "",
    body: "",
    reason: "",
    recipients_text: "",
    recipients_file: null,
    delay_min_seconds: data.limits.defaultDelayMin,
    delay_max_seconds: data.limits.defaultDelayMax,
    media: [],
  });

  /*
   * الفحص يمر بالخادم بنفس الشيفرة التي تبني القائمة عند الحفظ، فما يظهر هنا
   * هو ما سيُحفظ حرفيًا — لا تقدير تقريبي في المتصفح يخالف نتيجة الخادم.
   */
  const runPreview = async () => {
    setPreviewing(true);
    setPreviewFailed(false);

    const payload = new FormData();
    payload.append("recipients_text", form.data.recipients_text);
    payload.append("body", form.data.body);
    if (form.data.recipients_file) {
      payload.append("recipients_file", form.data.recipients_file);
    }

    try {
      const response = await fetch(data.urls.preview, {
        method: "POST",
        body: payload,
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error(String(response.status));
      }

      setPreview((await response.json()) as PreviewResult);
    } catch {
      setPreview(null);
      setPreviewFailed(true);
    } finally {
      setPreviewing(false);
    }
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post(data.urls.store, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        form.reset();
        setPreview(null);
      },
    });
  };

  const start = (campaign: CampaignRow) => {
    if (!window.confirm(t("console_whatsapp.campaigns.confirm_start"))) {
      return;
    }

    router.post(
      `/manage/whatsapp/campaigns/${campaign.id}/start`,
      {},
      { preserveScroll: true },
    );
  };

  const stop = (campaign: CampaignRow) => {
    if (!window.confirm(t("console_whatsapp.campaigns.confirm_stop"))) {
      return;
    }

    const reason = window.prompt(t("console_whatsapp.campaigns.fields.reason"));

    if (reason === null || reason.trim().length < 3) {
      return;
    }

    router.post(
      `/manage/whatsapp/campaigns/${campaign.id}/stop`,
      { reason },
      { preserveScroll: true },
    );
  };

  const field = "w-full rounded-lg border border-[var(--line)] p-3";
  const card = "rounded-xl border border-[var(--line)] bg-white p-5 my-5";

  return (
    <>
      <section className={card}>
        <h2 className="font-bold text-lg">
          {t("console_whatsapp.campaigns.title")}
        </h2>
        <p className="text-sm">{t("console_whatsapp.campaigns.hint")}</p>

        {!channelEnabled && (
          <p role="alert" className="mt-3 text-amber-800">
            {t("console_whatsapp.campaigns.channel_off_warning")}
          </p>
        )}

        <form onSubmit={submit} className="mt-4 grid gap-4">
          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.name")}
            </span>
            <input
              className={field}
              required
              maxLength={255}
              value={form.data.name}
              onChange={(event) => form.setData("name", event.target.value)}
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.fields.name_hint")}
            </span>
          </label>

          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.body")}
            </span>
            <textarea
              className={field}
              rows={5}
              required
              maxLength={20000}
              value={form.data.body}
              onChange={(event) => form.setData("body", event.target.value)}
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.placeholders.hint").replace(
                ":tokens",
                data.placeholders.join(" / "),
              )}
            </span>
          </label>

          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.recipients_text")}
            </span>
            <textarea
              className={field}
              rows={5}
              dir="ltr"
              value={form.data.recipients_text}
              onChange={(event) =>
                form.setData("recipients_text", event.target.value)
              }
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.fields.recipients_text_hint")}
            </span>
          </label>

          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.recipients_file")}
            </span>
            <input
              type="file"
              className={field}
              accept=".csv,.txt,.xlsx,.xls,.ods"
              onChange={(event) =>
                form.setData(
                  "recipients_file",
                  event.target.files?.[0] ?? null,
                )
              }
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.fields.recipients_file_hint")}
            </span>
          </label>

          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.media")}
            </span>
            <input
              type="file"
              multiple
              className={field}
              onChange={(event) =>
                form.setData("media", Array.from(event.target.files ?? []))
              }
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.fields.media_hint").replace(
                ":days",
                String(data.limits.mediaRetentionDays),
              )}
            </span>
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block">
              <span className="block mb-1">
                {t("console_whatsapp.campaigns.fields.delay_min")}
              </span>
              <input
                type="number"
                className={field}
                required
                min={data.limits.delayFloor}
                max={data.limits.delayCeiling}
                value={form.data.delay_min_seconds}
                onChange={(event) =>
                  form.setData(
                    "delay_min_seconds",
                    Number(event.target.value),
                  )
                }
              />
            </label>

            <label className="block">
              <span className="block mb-1">
                {t("console_whatsapp.campaigns.fields.delay_max")}
              </span>
              <input
                type="number"
                className={field}
                required
                min={data.limits.delayFloor}
                max={data.limits.delayCeiling}
                value={form.data.delay_max_seconds}
                onChange={(event) =>
                  form.setData(
                    "delay_max_seconds",
                    Number(event.target.value),
                  )
                }
              />
            </label>
          </div>
          <p className="text-xs -mt-2">
            {t("console_whatsapp.campaigns.fields.delay_hint")}
          </p>

          <label className="block">
            <span className="block mb-1">
              {t("console_whatsapp.campaigns.fields.reason")}
            </span>
            <input
              className={field}
              required
              minLength={3}
              maxLength={500}
              value={form.data.reason}
              onChange={(event) => form.setData("reason", event.target.value)}
            />
            <span className="text-xs">
              {t("console_whatsapp.campaigns.fields.reason_hint")}
            </span>
          </label>

          {Object.values(form.errors).map((error, index) => (
            <p key={index} role="alert" className="text-red-700">
              {error}
            </p>
          ))}

          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={runPreview}
              disabled={previewing}
              className="rounded-lg border border-[var(--line)] px-4 py-2 disabled:opacity-50"
            >
              {t("console_whatsapp.campaigns.preview.run")}
            </button>

            <button
              className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50"
              disabled={form.processing}
            >
              {t("console_whatsapp.campaigns.new")}
            </button>
          </div>
        </form>

        {previewFailed && (
          <p role="alert" className="mt-4 text-red-700">
            {t("console_whatsapp.campaigns.preview.failed")}
          </p>
        )}

        {preview && (
          <div className="mt-5 rounded-lg border border-[var(--line)] p-4" aria-live="polite">
            <p>
              {t("console_whatsapp.campaigns.preview.accepted")}:{" "}
              <strong>{preview.accepted_count}</strong> ·{" "}
              {t("console_whatsapp.campaigns.preview.rejected")}:{" "}
              <strong>{preview.rejected_count}</strong> ·{" "}
              {t("console_whatsapp.campaigns.preview.duplicates")}:{" "}
              <strong>{preview.duplicates}</strong>
            </p>

            {preview.sample_text && (
              <>
                <h3 className="font-bold mt-3">
                  {t("console_whatsapp.campaigns.preview.sample")}
                </h3>
                <p className="whitespace-pre-wrap">{preview.sample_text}</p>
              </>
            )}

            {preview.rejected.length > 0 && (
              <>
                <h3 className="font-bold mt-3">
                  {t("console_whatsapp.campaigns.problems.title")}
                </h3>
                <ul className="mt-2 grid gap-1">
                  {preview.rejected.map((row, index) => (
                    <li key={index} className="text-sm">
                      <span dir="ltr">{row.phone_input}</span>
                      {row.name ? ` — ${row.name}` : ""} —{" "}
                      {t("console_whatsapp.campaigns.reasons." + row.reason)}
                    </li>
                  ))}
                </ul>
              </>
            )}
          </div>
        )}
      </section>

      <section className={card}>
        <h2 className="font-bold text-lg">
          {t("console_whatsapp.campaigns.title")}
        </h2>

        {data.items.length === 0 && (
          <p className="text-sm">{t("console_whatsapp.campaigns.empty")}</p>
        )}

        <ul className="grid gap-4 mt-3">
          {data.items.map((campaign) => (
            <li
              key={campaign.id}
              className="rounded-lg border border-[var(--line)] p-4"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-bold">{campaign.name}</h3>
                <span>
                  {t("console_whatsapp.campaigns.status." + campaign.status)}
                </span>
              </div>

              <p className="text-sm mt-1">
                {t("console_whatsapp.campaigns.counts.progress")
                  .replace(":sent", String(campaign.counts.sent))
                  .replace(":total", String(campaign.total_recipients))}
                {" · "}
                {t("console_whatsapp.campaigns.counts.pending")}:{" "}
                {campaign.counts.pending}
                {" · "}
                {t("console_whatsapp.campaigns.counts.failed")}:{" "}
                {campaign.counts.failed}
                {campaign.counts.invalid > 0 && (
                  <>
                    {" · "}
                    {t("console_whatsapp.campaigns.counts.invalid")}:{" "}
                    {campaign.counts.invalid}
                  </>
                )}
              </p>

              <p className="text-xs mt-1">
                {campaign.delay_min_seconds}–{campaign.delay_max_seconds}s
                {campaign.media_count > 0 && (
                  <>
                    {" · "}
                    {t("console_whatsapp.campaigns.fields.media")}:{" "}
                    {campaign.media_count}
                  </>
                )}
                {campaign.created_at && (
                  <> · {formatDate(campaign.created_at, displayTimezone)}</>
                )}
              </p>

              {campaign.media_expires_at && campaign.media_count > 0 && (
                <p className="text-xs">
                  {t("console_whatsapp.campaigns.media_expires").replace(
                    ":date",
                    formatDate(campaign.media_expires_at, displayTimezone),
                  )}
                </p>
              )}

              <div className="flex flex-wrap gap-2 mt-3">
                {campaign.status === "draft" && (
                  <button
                    type="button"
                    onClick={() => start(campaign)}
                    className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white"
                  >
                    {t("console_whatsapp.campaigns.start")}
                  </button>
                )}

                {campaign.status === "running" && (
                  <>
                    <button
                      type="button"
                      onClick={() => stop(campaign)}
                      className="rounded-lg bg-red-700 px-4 py-2 text-white"
                    >
                      {t("console_whatsapp.campaigns.stop")}
                    </button>
                    <button
                      type="button"
                      onClick={() =>
                        router.reload({ only: ["campaigns"] })
                      }
                      className="rounded-lg border border-[var(--line)] px-4 py-2"
                    >
                      {t("console_whatsapp.campaigns.refresh")}
                    </button>
                  </>
                )}

                {campaign.counts.invalid + campaign.counts.failed > 0 && (
                  <button
                    type="button"
                    onClick={() =>
                      setOpenProblems(
                        openProblems === campaign.id ? null : campaign.id,
                      )
                    }
                    className="rounded-lg border border-[var(--line)] px-4 py-2"
                  >
                    {t("console_whatsapp.campaigns.problems.title")}
                  </button>
                )}
              </div>

              {openProblems === campaign.id && (
                <CampaignProblems campaignId={campaign.id} />
              )}
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}

type ProblemRow = {
  id: string;
  name: string | null;
  phone_input: string;
  status: string;
  reason: string | null;
};

function CampaignProblems({ campaignId }: { campaignId: string }) {
  const t = useI18n();
  const [rows, setRows] = useState<ProblemRow[] | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;

    fetch(`/manage/whatsapp/campaigns/${campaignId}`, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }

        return response.json();
      })
      .then((payload: { problems: ProblemRow[] }) => {
        if (active) {
          setRows(payload.problems);
        }
      })
      .catch(() => {
        if (active) {
          setFailed(true);
        }
      });

    return () => {
      active = false;
    };
  }, [campaignId]);

  if (failed) {
    return (
      <p role="alert" className="text-red-700 mt-3">
        {t("console_whatsapp.campaigns.preview.failed")}
      </p>
    );
  }

  if (rows === null) {
    return null;
  }

  if (rows.length === 0) {
    return (
      <p className="text-sm mt-3">
        {t("console_whatsapp.campaigns.problems.empty")}
      </p>
    );
  }

  return (
    <>
      <p className="text-xs mt-3">
        {t("console_whatsapp.campaigns.problems.hint")}
      </p>
      <ul className="grid gap-1 mt-2">
        {rows.map((row) => (
          <li key={row.id} className="text-sm">
            <span dir="ltr">{row.phone_input}</span>
            {row.name ? ` — ${row.name}` : ""}
            {row.reason
              ? ` — ${t("console_whatsapp.campaigns.reasons." + row.reason)}`
              : ""}
          </li>
        ))}
      </ul>
    </>
  );
}
