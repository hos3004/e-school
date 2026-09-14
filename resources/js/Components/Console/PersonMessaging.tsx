import { useForm } from "@inertiajs/react";
import { useState } from "react";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import { useI18n } from "@/lib/i18n";
import { ulid } from "@/lib/ulid";

export type MessagingChannel = {
  value: string;
  enabled: boolean;
  reason: string | null;
};

export type PersonMessagingData = {
  sendUrl: string;
  channels: MessagingChannel[];
  defaultChannel: string;
};

type Kind = "credentials" | "schedule" | "free_text";

const CREDENTIAL_FIELDS = ["name", "email", "username", "password"] as const;

/**
 * أيقونة المراسلة داخل الملف الشخصي.
 *
 * ثلاثة أنواع: بيانات الحساب بحقول تُختار يدويًا، وجدول المواعيد، ونص حر.
 * اختيار «كلمة المرور» ليس عرضًا لكلمة قائمة — القائمة مخزّنة مشفّرة — بل
 * إصدار كلمة مؤقتة تبطل الحالية، ولذلك يظهر تحذيره صراحةً قبل الإرسال.
 */
export default function PersonMessaging({
  messaging,
}: {
  messaging: PersonMessagingData;
}) {
  const t = useI18n();
  const [open, setOpen] = useState(false);
  const form = useForm({
    kind: "free_text" as Kind,
    channel: messaging.defaultChannel,
    reason: "",
    request_id: ulid(),
    scheduled_for: "",
    fields: ["name", "username"] as string[],
    subject: "",
    body: "",
  });

  const toggleField = (field: string) => {
    form.setData(
      "fields",
      form.data.fields.includes(field)
        ? form.data.fields.filter((value) => value !== field)
        : [...form.data.fields, field],
    );
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post(messaging.sendUrl, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset("subject", "body", "reason", "scheduled_for");
        // معرّف جديد للإرسالة التالية، وإلا اعتبرها الخادم تكرارًا للسابقة.
        form.setData("request_id", ulid());
        setOpen(false);
      },
    });
  };

  const ready =
    form.data.reason.trim().length >= 3 &&
    (form.data.kind !== "free_text" ||
      (form.data.subject.trim() !== "" && form.data.body.trim() !== "")) &&
    (form.data.kind !== "credentials" || form.data.fields.length > 0);

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-bold text-lg">{t("console_messaging.title")}</h2>
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
          <div className="flex flex-wrap gap-2">
            {(["credentials", "schedule", "free_text"] as Kind[]).map((kind) => (
              <button
                key={kind}
                type="button"
                onClick={() => form.setData("kind", kind)}
                className={
                  "rounded-lg px-4 py-2 border border-[var(--line)]" +
                  (form.data.kind === kind
                    ? " bg-[var(--surface-2,#f3f4f6)] font-bold"
                    : "")
                }
              >
                {t("console_messaging.kinds." + kind)}
              </button>
            ))}
          </div>

          {form.data.kind === "credentials" && (
            <fieldset className="my-4">
              <legend className="mb-2">
                {t("console_messaging.fields.fields")}
              </legend>
              <div className="flex flex-wrap gap-4">
                {CREDENTIAL_FIELDS.map((field) => (
                  <label key={field} className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={form.data.fields.includes(field)}
                      onChange={() => toggleField(field)}
                    />
                    {t("console_messaging.credentials.fields." + field)}
                  </label>
                ))}
              </div>
              {form.data.fields.includes("password") && (
                <p role="note" className="text-sm text-red-700 mt-2">
                  {t("console_messaging.hints.password")}
                </p>
              )}
            </fieldset>
          )}

          {form.data.kind === "free_text" && (
            <>
              <label className="block my-3">
                <span className="block mb-2">
                  {t("console_messaging.fields.subject")}
                </span>
                <input
                  className="w-full rounded-lg border border-[var(--line)] p-3"
                  value={form.data.subject}
                  onChange={(event) =>
                    form.setData("subject", event.target.value)
                  }
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
              </label>
              <p className="text-sm">
                {t("console_messaging.hints.variables").replace(
                  ":variables",
                  "{{name}} {{username}} {{email}} {{login_url}}",
                )}
              </p>
            </>
          )}

          <div className="grid gap-3 md:grid-cols-2 my-3">
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
                  <option
                    key={channel.value}
                    value={channel.value}
                    disabled={!channel.enabled}
                  >
                    {t("console_messaging.channels." + channel.value)}
                    {channel.enabled
                      ? ""
                      : " — " +
                        t("console_messaging.channel_reasons." + channel.reason)}
                  </option>
                ))}
              </select>
            </label>
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
          </div>

          <label className="block my-3">
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
            <span className="text-sm">
              {t("console_messaging.hints.reason")}
            </span>
          </label>

          {Object.values(form.errors).map((error, index) => (
            <p key={index} role="alert" className="text-red-700">
              {error}
            </p>
          ))}

          <div className="flex gap-2">
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
