import { useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import { useI18n } from "@/lib/i18n";
import { ulid } from "@/lib/ulid";

export type MessagingChannel = {
  value: string;
  enabled: boolean;
  reason: string | null;
};

export type AudienceMessagingData = {
  sendUrl: string;
  targetsUrl: string;
  templatesUrl: string;
  channels: MessagingChannel[];
  defaultChannel: string;
  /** الهدف المثبّت حين يُفتح الزر من صفحة فصل بعينها. */
  fixedTarget?: { type: "group" | "course" | "schedule"; id: string; label: string } | null;
  /**
   * رابط المحاكاة. وجوده يجعل المعاينة شرطًا قبل الإرسال: لا يُفتح زر الإرسال
   * حتى يرى المرسِل النص النهائي وقائمة المستلمين الفعليين لنفس المدخلات.
   */
  previewUrl?: string | null;
};

type PreviewPerson = { id: string; name: string; phone: string | null; reason?: string };

type PreviewResult = {
  label: string;
  text: string;
  resolved_count: number;
  reachable: PreviewPerson[];
  unreachable: PreviewPerson[];
  channel_enabled: boolean;
};

type TargetOption = { value: string; label: string };

/** أنواع الهدف التي يعرضها المؤلِّف، مرتبة من الأضيق إلى الأوسع. */
const RECIPIENT_TYPES = [
  "student",
  "teacher",
  "guardian",
  "people",
  "course",
  "schedule",
  "group",
  "students_all",
  "teachers_all",
  "guardians_all",
] as const;

type RecipientType = (typeof RECIPIENT_TYPES)[number];

/** جمهوره المؤسسة كلها: لا هدف يُختار، وtarget_id هو اسم النوع نفسه. */
const WHOLE_ORGANIZATION: readonly RecipientType[] = [
  "students_all",
  "teachers_all",
  "guardians_all",
];

/** يُختار فيه شخص واحد أو أكثر بالاسم. */
const PICKS_PEOPLE: readonly RecipientType[] = ["student", "teacher", "guardian", "people"];

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
  const [search, setSearch] = useState("");
  const [picked, setPicked] = useState<TargetOption[]>([]);
  const [templates, setTemplates] = useState<TemplateOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState<PreviewResult | null>(null);
  const [previewSignature, setPreviewSignature] = useState("");
  const [previewing, setPreviewing] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const form = useForm({
    recipient_type: (messaging.fixedTarget?.type ?? "course") as RecipientType,
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

  const recipientType = form.data.recipient_type as RecipientType;
  const wholeOrganization = WHOLE_ORGANIZATION.includes(recipientType);
  const picksPeople = PICKS_PEOPLE.includes(recipientType);
  const multiPick = recipientType === "people";
  const audienceScoped = (["group", "course", "schedule"] as readonly RecipientType[]).includes(
    recipientType,
  );

  useEffect(() => {
    if (!open || fixed || wholeOrganization) {
      return;
    }

    let cancelled = false;
    setLoading(true);

    fetch(
      `${messaging.targetsUrl}?${new URLSearchParams({
        type: form.data.recipient_type,
        search,
      })}`,
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
  }, [open, fixed, wholeOrganization, search, form.data.recipient_type, messaging.targetsUrl]);

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

  /*
   * بصمة المدخلات التي تؤثر في نتيجة المحاكاة. تغيّر أي منها يُبطل المعاينة
   * السابقة، فلا تُجيز معاينةُ رسالةٍ إرسالَ رسالة أخرى لجمهور آخر.
   */
  const signature = JSON.stringify([
    form.data.recipient_type,
    form.data.target_id,
    form.data.audience,
    form.data.subject,
    form.data.body,
  ]);
  const previewStale = preview !== null && previewSignature !== signature;
  const previewRequired = Boolean(messaging.previewUrl);

  const runPreview = () => {
    if (!messaging.previewUrl) {
      return;
    }

    setPreviewing(true);
    setPreviewError(null);

    fetch(messaging.previewUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN":
          document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? "",
      },
      body: JSON.stringify({
        recipient_type: form.data.recipient_type,
        target_id: form.data.target_id,
        audience: form.data.audience,
        subject: form.data.subject,
        body: form.data.body,
      }),
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        return (await response.json()) as PreviewResult;
      })
      .then((result) => {
        setPreview(result);
        setPreviewSignature(signature);
      })
      .catch(() => {
        setPreview(null);
        setPreviewError(t("console_whatsapp.preview.failed"));
      })
      .finally(() => setPreviewing(false));
  };

  /**
   * تبديل النوع يعيد ضبط الهدف كليًا: قائمة مختارة من نوع سابق لا تصلح هدفًا
   * لنوع آخر، و«كل الطلاب» هدفه اسم النوع لا معرّف.
   */
  const changeRecipientType = (next: RecipientType) => {
    form.setData("recipient_type", next);
    setPicked([]);
    setSearch("");
    form.setData("target_id", WHOLE_ORGANIZATION.includes(next) ? next : "");
  };

  const togglePicked = (option: TargetOption) => {
    setPicked((current) => {
      const next = current.some((person) => person.value === option.value)
        ? current.filter((person) => person.value !== option.value)
        : [...current, option];

      form.setData("target_id", next.map((person) => person.value).join(","));

      return next;
    });
  };

  const clearPicked = () => {
    setPicked([]);
    form.setData("target_id", "");
  };

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
        setPreview(null);
        setPreviewSignature("");
        setOpen(false);
      },
    });
  };

  const ready =
    form.data.target_id !== "" &&
    form.data.subject.trim() !== "" &&
    form.data.body.trim() !== "" &&
    form.data.reason.trim().length >= 3 &&
    (!previewRequired || (preview !== null && !previewStale));

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
            <div className="my-3">
              <label className="block md:w-1/2">
                <span className="block mb-2">
                  {t("console_messaging.fields.recipient_type")}
                </span>
                <select
                  className="w-full rounded-lg border border-[var(--line)] p-3"
                  value={form.data.recipient_type}
                  onChange={(event) =>
                    changeRecipientType(event.target.value as RecipientType)
                  }
                >
                  {RECIPIENT_TYPES.map((type) => (
                    <option key={type} value={type}>
                      {t("console_messaging.targets." + type)}
                    </option>
                  ))}
                </select>
              </label>

              {wholeOrganization && (
                <p className="text-sm mt-3">
                  {t("console_messaging.whole_org_hint")}
                </p>
              )}

              {!wholeOrganization && !multiPick && (
                <label className="block md:w-1/2 mt-3">
                  <span className="block mb-2">
                    {t("console_messaging.fields.target")}
                  </span>
                  {picksPeople && (
                    <input
                      type="search"
                      className="w-full rounded-lg border border-[var(--line)] p-3 mb-2"
                      placeholder={t("console_messaging.people_search")}
                      value={search}
                      onChange={(event) => setSearch(event.target.value)}
                    />
                  )}
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
              )}

              {multiPick && (
                <fieldset className="mt-3 rounded-lg border border-[var(--line)] p-3">
                  <legend className="px-1">
                    {t("console_messaging.people_label")}
                  </legend>
                  <input
                    type="search"
                    className="w-full rounded-lg border border-[var(--line)] p-3"
                    placeholder={t("console_messaging.people_search")}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                  />

                  <div className="max-h-56 overflow-y-auto mt-2">
                    {targets.length === 0 && !loading ? (
                      <p className="text-sm">
                        {t("console_messaging.no_targets")}
                      </p>
                    ) : (
                      targets.map((target) => {
                        const chosen = picked.some(
                          (person) => person.value === target.value,
                        );

                        return (
                          <label
                            key={target.value}
                            className="flex items-center gap-2 py-1"
                          >
                            <input
                              type="checkbox"
                              checked={chosen}
                              onChange={() => togglePicked(target)}
                            />
                            <span>{target.label}</span>
                          </label>
                        );
                      })
                    )}
                  </div>

                  <div className="flex flex-wrap items-center justify-between gap-2 mt-2">
                    <p className="text-sm" role="status">
                      {picked.length === 0
                        ? t("console_messaging.people_empty")
                        : t("console_messaging.people_selected").replace(
                            ":count",
                            String(picked.length),
                          )}
                    </p>
                    {picked.length > 0 && (
                      <button
                        type="button"
                        className="rounded-lg border border-[var(--line)] px-3 py-1"
                        onClick={clearPicked}
                      >
                        {t("console_messaging.people_clear")}
                      </button>
                    )}
                  </div>

                  {picked.length > 0 && (
                    <ul className="text-sm mt-2">
                      {picked.map((person) => (
                        <li key={person.value}>{person.label}</li>
                      ))}
                    </ul>
                  )}
                </fieldset>
              )}
            </div>
          )}

          <div className="grid gap-3 md:grid-cols-3 my-3">
            {/*
              تقييد الجمهور يخص الهدف الذي يحمل أكثر من طرف — المجموعة والكورس
              والجدول. اختيار شخص أو قائمة أو كل الطلاب يحدد جمهوره بنفسه.
            */}
            <label className={audienceScoped ? "block" : "hidden"}>
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

          {previewRequired && (
            <section className="rounded-lg border border-[var(--line)] p-4 my-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 className="font-bold">
                    {t("console_whatsapp.preview.title")}
                  </h3>
                  <p className="text-sm">
                    {t("console_whatsapp.preview.hint")}
                  </p>
                </div>
                <button
                  type="button"
                  className="rounded-lg border border-[var(--line)] px-4 py-2 disabled:opacity-50"
                  onClick={runPreview}
                  disabled={previewing || form.data.target_id === ""}
                >
                  {t("console_whatsapp.preview.run")}
                </button>
              </div>

              {previewError && (
                <p role="alert" className="text-red-700 mt-2">
                  {previewError}
                </p>
              )}

              {preview === null && !previewError && (
                <p className="text-sm mt-2">
                  {t("console_whatsapp.preview.empty")}
                </p>
              )}

              {preview !== null && (
                <div className="mt-3">
                  {previewStale && (
                    <p role="alert" className="text-amber-800">
                      {t("console_whatsapp.preview.stale")}
                    </p>
                  )}

                  {!preview.channel_enabled && (
                    <p role="alert" className="text-amber-800">
                      {t("console_whatsapp.preview.channel_off_warning")}
                    </p>
                  )}

                  <p className="mt-2">
                    <strong>{t("console_whatsapp.preview.message_preview")}</strong>
                  </p>
                  <pre className="whitespace-pre-wrap rounded-lg bg-[var(--surface,#f6f6f6)] p-3 mt-1">
                    {preview.text}
                  </pre>

                  <p className="mt-3">
                    {t("console_whatsapp.preview.count_summary")
                      .replace(":reachable", String(preview.reachable.length))
                      .replace(":total", String(preview.resolved_count))}
                  </p>

                  <div className="grid gap-3 md:grid-cols-2 mt-2">
                    <div>
                      <p className="font-bold">
                        {t("console_whatsapp.preview.reachable")} (
                        {preview.reachable.length})
                      </p>
                      <ul className="text-sm">
                        {preview.reachable.map((person) => (
                          <li key={person.id}>
                            {person.name} — {person.phone}
                          </li>
                        ))}
                      </ul>
                    </div>
                    <div>
                      <p className="font-bold">
                        {t("console_whatsapp.preview.unreachable")} (
                        {preview.unreachable.length})
                      </p>
                      <ul className="text-sm">
                        {preview.unreachable.map((person) => (
                          <li key={person.id}>
                            {person.name} — {person.reason}
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>
                </div>
              )}
            </section>
          )}

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
