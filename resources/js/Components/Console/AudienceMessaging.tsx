import { useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import { useI18n } from "@/lib/i18n";
import { ulid } from "@/lib/ulid";

export type AudienceMessagingData = {
  sendUrl: string;
  targetsUrl: string;
  templatesUrl: string;
  channels: string[];
  defaultChannel: string;
  /** الهدف المثبّت حين يُفتح الزر من صفحة فصل بعينها. */
  fixedTarget?: { type: "group" | "course" | "schedule"; id: string; label: string } | null;
};

type TargetOption = { value: string; label: string };

type TemplateOption = {
  id: string;
  event_key: string;
  channel: string;
  locale: string;
  subject: string | null;
  body: string;
  parameters: string[];
};

/**
 * مراسلة أطراف فصل: الطلاب فقط، أو المعلم فقط، أو الجميع.
 *
 * الرسائل فردية منفصلة دائمًا، فلا تُكشف أرقام المستلمين لبعضهم. الهدف قد
 * يكون مجموعة أو كورسًا أو جدولًا فرديًا، لأن المدرسة تعمل بجداول فردية
 * ولا يكفي أن يقتصر الزر على المجموعات.
 */
export default function AudienceMessaging({
  messaging,
}: {
  messaging: AudienceMessagingData;
}) {
  const t = useI18n();
  const [open, setOpen] = useState(false);
  const [targets, setTargets] = useState<TargetOption[]>([]);
  const [templates, setTemplates] = useState<TemplateOption[]>([]);
  const [loading, setLoading] = useState(false);
  const form = useForm({
    recipient_type: messaging.fixedTarget?.type ?? "course",
    target_id: messaging.fixedTarget?.id ?? "",
    audience: "all",
    channel: messaging.defaultChannel,
    template_id: "",
    subject: "",
    body: "",
    reason: "",
    request_id: ulid(),
    scheduled_for: "",
  });

  const fixed = messaging.fixedTarget ?? null;

  useEffect(() => {
    if (!open || fixed) {
      return;
    }

    let cancelled = false;
    setLoading(true);

    fetch(
      `${messaging.targetsUrl}?${new URLSearchParams({ type: form.data.recipient_type })}`,
      { headers: { Accept: "application/json" } },
    )
      .then((response) => (response.ok ? response.json() : { targets: [] }))
      .then((payload: { targets?: TargetOption[] }) => {
        if (!cancelled) {
          setTargets(payload.targets ?? []);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setTargets([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [open, fixed, form.data.recipient_type, messaging.targetsUrl]);

  useEffect(() => {
    if (!open || templates.length > 0) {
      return;
    }

    fetch(messaging.templatesUrl, { headers: { Accept: "application/json" } })
      .then((response) => (response.ok ? response.json() : { templates: [] }))
      .then((payload: { templates?: TemplateOption[] }) =>
        setTemplates(payload.templates ?? []),
      )
      .catch(() => setTemplates([]));
  }, [open, templates.length, messaging.templatesUrl]);

  const applyTemplate = (id: string) => {
    form.setData("template_id", id);
    const template = templates.find((candidate) => candidate.id === id);

    if (template) {
      form.setData("subject", template.subject ?? "");
      form.setData("body", template.body);
    }
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post(messaging.sendUrl, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset("subject", "body", "reason", "scheduled_for", "template_id");
        form.setData("request_id", ulid());
        setOpen(false);
      },
    });
  };

  const ready =
    form.data.target_id !== "" &&
    form.data.subject.trim() !== "" &&
    form.data.body.trim() !== "" &&
    form.data.reason.trim().length >= 3;

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-bold text-lg">
            {t("console_messaging.bulk_title")}
          </h2>
          <p className="text-sm">{t("console_messaging.bulk_hint")}</p>
        </div>
        <button
          type="button"
          aria-expanded={open}
          onClick={() => {
            form.clearErrors();
            setOpen(!open);
          }}
          className="inline-flex items-center gap-2 rounded-lg border border-[var(--line)] px-4 py-2"
        >
          <ConsoleIcon name="message" />
          {t("console_messaging.open")}
        </button>
      </div>

      {open && (
        <form onSubmit={submit} className="mt-4">
          {fixed ? (
            <p className="my-2">
              {t("console_messaging.fields.target")}: <strong>{fixed.label}</strong>
            </p>
          ) : (
            <div className="grid gap-3 md:grid-cols-2 my-3">
              <label className="block">
                <span className="block mb-2">
                  {t("console_messaging.fields.recipient_type")}
                </span>
                <select
                  className="w-full rounded-lg border border-[var(--line)] p-3"
                  value={form.data.recipient_type}
                  onChange={(event) => {
                    form.setData(
                      "recipient_type",
                      event.target.value as "group" | "course" | "schedule",
                    );
                    form.setData("target_id", "");
                  }}
                >
                  {["course", "schedule", "group"].map((type) => (
                    <option key={type} value={type}>
                      {t("console_messaging.targets." + type)}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="block mb-2">
                  {t("console_messaging.fields.target")}
                </span>
                <select
                  className="w-full rounded-lg border border-[var(--line)] p-3"
                  value={form.data.target_id}
                  onChange={(event) =>
                    form.setData("target_id", event.target.value)
                  }
                  required
                  disabled={loading}
                >
                  <option value="">
                    {targets.length === 0 && !loading
                      ? t("console_messaging.no_targets")
                      : t("console_messaging.choose_target")}
                  </option>
                  {targets.map((target) => (
                    <option key={target.value} value={target.value}>
                      {target.label}
                    </option>
                  ))}
                </select>
              </label>
            </div>
          )}

          <div className="grid gap-3 md:grid-cols-3 my-3">
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.fields.audience")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.audience}
                onChange={(event) =>
                  form.setData("audience", event.target.value)
                }
              >
                {["students", "teacher", "all"].map((audience) => (
                  <option key={audience} value={audience}>
                    {t("console_messaging.audiences." + audience)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.fields.channel")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.channel}
                onChange={(event) => form.setData("channel", event.target.value)}
              >
                {messaging.channels.map((channel) => (
                  <option key={channel} value={channel}>
                    {t("console_messaging.channels." + channel)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.fields.template")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.template_id}
                onChange={(event) => applyTemplate(event.target.value)}
              >
                <option value="">
                  {t("console_messaging.template_none")}
                </option>
                {templates
                  .filter(
                    (template) => template.channel === form.data.channel,
                  )
                  .map((template) => (
                    <option key={template.id} value={template.id}>
                      {template.event_key} · {template.locale}
                    </option>
                  ))}
              </select>
            </label>
          </div>

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_messaging.fields.subject")}
            </span>
            <input
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.subject}
              onChange={(event) => form.setData("subject", event.target.value)}
              maxLength={255}
              required
            />
          </label>

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_messaging.fields.body")}
            </span>
            <textarea
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.body}
              onChange={(event) => form.setData("body", event.target.value)}
              rows={5}
              maxLength={4000}
              required
            />
            <span className="text-sm">
              {t("console_messaging.hints.variables").replace(
                ":variables",
                "{{school}} {{login_url}}",
              )}
            </span>
          </label>

          <div className="grid gap-3 md:grid-cols-2 my-3">
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.fields.scheduled_for")}
              </span>
              <input
                type="datetime-local"
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.scheduled_for}
                onChange={(event) =>
                  form.setData("scheduled_for", event.target.value)
                }
              />
              <span className="text-sm">
                {t("console_messaging.hints.scheduled_for")}
              </span>
            </label>
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.fields.reason")}
              </span>
              <textarea
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.reason}
                onChange={(event) => form.setData("reason", event.target.value)}
                rows={2}
                minLength={3}
                maxLength={500}
                required
              />
            </label>
          </div>

          <p className="text-sm">{t("console_messaging.hints.individual")}</p>

          {Object.values(form.errors).map((error, index) => (
            <p key={index} role="alert" className="text-red-700">
              {error}
            </p>
          ))}

          <div className="flex gap-2 mt-3">
            <button
              className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50"
              disabled={form.processing || !ready}
            >
              {t("console_messaging.send")}
            </button>
            <button
              type="button"
              className="rounded-lg border border-[var(--line)] px-4 py-2"
              onClick={() => setOpen(false)}
            >
              {t("console_messaging.cancel")}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
