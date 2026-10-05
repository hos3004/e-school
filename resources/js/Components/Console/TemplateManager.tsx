import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type TemplateRow = {
  id: string;
  event_key: string;
  channel: string;
  locale: string;
  subject: string | null;
  body: string;
  provider_template_name: string | null;
  parameters: string[];
  is_active: boolean;
  is_global: boolean;
  updateUrl: string | null;
  deleteUrl: string | null;
};

export type TemplateManagerData = {
  templates: TemplateRow[];
  locales: string[];
  channels: string[];
  storeUrl: string;
};

const EMPTY = {
  event_key: "",
  channel: "whatsapp",
  locale: "ar",
  subject: "",
  body: "",
  provider_template_name: "",
  is_active: true,
};

/**
 * إنشاء قوالب الرسائل وتعديلها.
 *
 * المتغيرات لا تُدخَل في حقل منفصل: الخادم يستخرجها من نص القالب، فلا يمكن
 * أن يعلن القالب متغيرًا لا يذكره أو يذكر متغيرًا لا يعلنه. القالب العام
 * يظهر للقراءة والنسخ فقط لأنه مرجع مشترك بين المؤسسات.
 */
export default function TemplateManager({
  data,
}: {
  data: TemplateManagerData;
}) {
  const t = useI18n();
  const [editing, setEditing] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const form = useForm({ ...EMPTY });

  const startCreate = (from?: TemplateRow) => {
    form.clearErrors();
    form.setData(
      from
        ? {
            event_key: from.event_key,
            channel: from.channel,
            locale: from.locale,
            subject: from.subject ?? "",
            body: from.body,
            provider_template_name: from.provider_template_name ?? "",
            is_active: true,
          }
        : { ...EMPTY, channel: data.channels[0] ?? "whatsapp", locale: data.locales[0] ?? "ar" },
    );
    setEditing(null);
    setOpen(true);
  };

  const startEdit = (row: TemplateRow) => {
    form.clearErrors();
    form.setData({
      event_key: row.event_key,
      channel: row.channel,
      locale: row.locale,
      subject: row.subject ?? "",
      body: row.body,
      provider_template_name: row.provider_template_name ?? "",
      is_active: row.is_active,
    });
    setEditing(row.id);
    setOpen(true);
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    const row = data.templates.find((candidate) => candidate.id === editing);

    if (editing && row?.updateUrl) {
      form.put(row.updateUrl, {
        preserveScroll: true,
        onSuccess: () => setOpen(false),
      });

      return;
    }

    form.post(data.storeUrl, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
    });
  };

  const remove = (row: TemplateRow) => {
    if (!row.deleteUrl) {
      return;
    }

    form.delete(row.deleteUrl, { preserveScroll: true });
  };

  const detected = Array.from(
    new Set(
      [...(form.data.body + " " + form.data.subject).matchAll(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g)].map(
        (match) => match[1],
      ),
    ),
  );

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-bold text-lg">
            {t("console_messaging.templates_title")}
          </h2>
          <p className="text-sm">{t("console_messaging.templates_hint")}</p>
        </div>
        <button
          type="button"
          className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white"
          onClick={() => startCreate()}
        >
          {t("console_messaging.template_new")}
        </button>
      </div>

      {open && (
        <form onSubmit={submit} className="my-4 border-t border-[var(--line)] pt-4">
          <div className="grid gap-3 md:grid-cols-3">
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.template_fields.event_key")}
              </span>
              <input
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.event_key}
                onChange={(event) =>
                  form.setData("event_key", event.target.value)
                }
                required
              />
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
                {data.channels.map((channel) => (
                  <option key={channel} value={channel}>
                    {t("console_messaging.channels." + channel)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="block mb-2">
                {t("console_messaging.template_fields.locale")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.locale}
                onChange={(event) => form.setData("locale", event.target.value)}
              >
                {data.locales.map((locale) => (
                  <option key={locale} value={locale}>
                    {locale}
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
              rows={6}
              maxLength={4000}
              required
            />
          </label>

          <p className="text-sm my-2">
            {t("console_messaging.template_fields.detected")}:{" "}
            {detected.length === 0
              ? t("console_messaging.template_fields.no_variables")
              : detected.map((name) => "{{" + name + "}}").join(" ")}
          </p>

          <label className="flex items-center gap-2 my-3">
            <input
              type="checkbox"
              checked={form.data.is_active}
              onChange={(event) =>
                form.setData("is_active", event.target.checked)
              }
            />
            {t("console_messaging.template_fields.is_active")}
          </label>

          {Object.values(form.errors).map((error, index) => (
            <p key={index} role="alert" className="text-red-700">
              {error}
            </p>
          ))}

          <div className="flex gap-2">
            <button
              className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50"
              disabled={form.processing}
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

      <div className="overflow-x-auto mt-4">
        <table className="w-full text-sm">
          <thead>
            <tr>
              <th className="text-start p-2">
                {t("console_messaging.template_fields.event_key")}
              </th>
              <th className="text-start p-2">
                {t("console_messaging.fields.channel")}
              </th>
              <th className="text-start p-2">
                {t("console_messaging.template_fields.locale")}
              </th>
              <th className="text-start p-2">
                {t("console_messaging.template_fields.is_active")}
              </th>
              <th className="text-start p-2" />
            </tr>
          </thead>
          <tbody>
            {data.templates.map((row) => (
              <tr key={row.id} className="border-t border-[var(--line)]">
                <td className="p-2">{row.event_key}</td>
                <td className="p-2">
                  {t("console_messaging.channels." + row.channel)}
                </td>
                <td className="p-2">{row.locale}</td>
                <td className="p-2">
                  {row.is_active
                    ? t("console_messaging.template_fields.active")
                    : t("console_messaging.template_fields.inactive")}
                </td>
                <td className="p-2">
                  <div className="flex gap-2">
                    {row.updateUrl ? (
                      <>
                        <button
                          type="button"
                          className="rounded-lg border border-[var(--line)] px-3 py-1"
                          onClick={() => startEdit(row)}
                        >
                          {t("console_messaging.template_edit")}
                        </button>
                        <button
                          type="button"
                          className="rounded-lg border border-red-700 text-red-700 px-3 py-1"
                          onClick={() => remove(row)}
                        >
                          {t("console_messaging.template_delete")}
                        </button>
                      </>
                    ) : (
                      <button
                        type="button"
                        className="rounded-lg border border-[var(--line)] px-3 py-1"
                        onClick={() => startCreate(row)}
                      >
                        {t("console_messaging.template_override")}
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
