import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type PopupMediaRow = {
  id: string;
  kind: "image" | "video" | "audio" | "file";
  original_name: string;
  mime_type: string;
  size_bytes: number;
  has_sound: boolean | null;
  position: number;
  url: string;
};

export type PopupCampaignRow = {
  id: string;
  internal_name: string;
  type: string;
  status: "draft" | "published" | "paused" | "archived";
  title: Record<string, string>;
  body: Record<string, string>;
  audiences: string[];
  excluded_audiences: string[];
  placement: string;
  display_mode: "bottom_banner" | "fullscreen";
  page_key: string | null;
  frequency: string;
  is_dismissible: boolean;
  requires_acknowledgement: boolean;
  acknowledgement_label: Record<string, string> | null;
  auto_dismiss_seconds: number | null;
  action_type: string | null;
  action_target: string | null;
  action_label: Record<string, string> | null;
  links: { text: string; url: string }[];
  priority: number;
  starts_at: string;
  ends_at: string | null;
  media: PopupMediaRow[];
  can_transition_to: { published: boolean; paused: boolean; archived: boolean };
  editable: boolean;
  urls: { update: string; media: string; publish: string; pause: string; archive: string };
};

export type PopupMessageOptions = {
  types: Record<string, string>;
  displayModes: Record<string, string>;
  audiences: Record<string, string>;
  placements: Record<string, string>;
  pageKeys: Record<string, string>;
  frequencies: Record<string, string>;
  statuses: Record<string, string>;
};

export type PopupMessageLimits = {
  priorityMin: number;
  priorityMax: number;
  priorityDefault: number;
  autoDismissMinSeconds: number;
  autoDismissMaxSeconds: number;
  titleMax: number;
  bodyMax: number;
  internalNameMax: number;
  linkTextMax: number;
  linkUrlMax: number;
  maxLinks: number;
};

type FormShape = {
  internal_name: string;
  type: string;
  title: { ar: string; en: string; fr: string };
  body: { ar: string; en: string; fr: string };
  audiences: string[];
  excluded_audiences: string[];
  placement: string;
  display_mode: string;
  page_key: string;
  frequency: string;
  is_dismissible: boolean;
  requires_acknowledgement: boolean;
  auto_dismiss_seconds: string;
  acknowledgement_label: string;
  priority: number;
  starts_at: string;
  ends_at: string;
  action_type: string;
  internal_action_target: string;
  external_action_target: string;
  action_label: string;
  links: { text: string; url: string }[];
  reason: string;
};

const field = "w-full rounded-lg border border-[var(--line)] p-3";

function toLocaleDateTime(iso: string | null): string {
  if (!iso) {
    return "";
  }

  // datetime-local يحتاج "YYYY-MM-DDTHH:mm" بلا منطقة زمنية — القيمة هنا
  // محلية للمتصفح أصلًا (المتصفح يعرض/يحفظ محليًا)، والخادم يحوّلها UTC.
  return iso.slice(0, 16);
}

function emptyForm(options: PopupMessageOptions, limits: PopupMessageLimits): FormShape {
  return {
    internal_name: "",
    type: Object.keys(options.types)[0] ?? "general",
    title: { ar: "", en: "", fr: "" },
    body: { ar: "", en: "", fr: "" },
    audiences: [],
    excluded_audiences: [],
    placement: Object.keys(options.placements)[0] ?? "after_login",
    display_mode: Object.keys(options.displayModes)[0] ?? "bottom_banner",
    page_key: "",
    frequency: Object.keys(options.frequencies)[0] ?? "once",
    is_dismissible: true,
    requires_acknowledgement: false,
    auto_dismiss_seconds: "",
    acknowledgement_label: "",
    priority: limits.priorityDefault,
    starts_at: "",
    ends_at: "",
    action_type: "",
    internal_action_target: "",
    external_action_target: "",
    action_label: "",
    links: [],
    reason: "",
  };
}

function fromRow(row: PopupCampaignRow): FormShape {
  return {
    internal_name: row.internal_name,
    type: row.type,
    title: { ar: row.title.ar ?? "", en: row.title.en ?? "", fr: row.title.fr ?? "" },
    body: { ar: row.body.ar ?? "", en: row.body.en ?? "", fr: row.body.fr ?? "" },
    audiences: row.audiences,
    excluded_audiences: row.excluded_audiences,
    placement: row.placement,
    display_mode: row.display_mode,
    page_key: row.page_key ?? "",
    frequency: row.frequency,
    is_dismissible: row.is_dismissible,
    requires_acknowledgement: row.requires_acknowledgement,
    auto_dismiss_seconds: row.auto_dismiss_seconds === null ? "" : String(row.auto_dismiss_seconds),
    acknowledgement_label: row.acknowledgement_label?.ar ?? "",
    priority: row.priority,
    starts_at: toLocaleDateTime(row.starts_at),
    ends_at: toLocaleDateTime(row.ends_at),
    action_type: row.action_type ?? "",
    internal_action_target: row.action_type === "internal_page" ? (row.action_target ?? "") : "",
    external_action_target: row.action_type === "external_url" ? (row.action_target ?? "") : "",
    action_label: row.action_label?.ar ?? "",
    links: row.links,
    reason: "",
  };
}

export default function PopupMessageComposer({
  campaign,
  options,
  limits,
  storeUrl,
  onDone,
  onCancel,
}: {
  campaign: PopupCampaignRow | null;
  options: PopupMessageOptions;
  limits: PopupMessageLimits;
  storeUrl: string;
  onDone: () => void;
  onCancel: () => void;
}) {
  const t = useI18n();
  const form = useForm<FormShape>(campaign ? fromRow(campaign) : emptyForm(options, limits));
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [linkDraft, setLinkDraft] = useState<{ text: string; targetType: "external" | "media"; external: string; mediaId: string }>({
    text: "",
    targetType: "external",
    external: "",
    mediaId: "",
  });

  const fileMedia = (campaign?.media ?? []).filter((media) => media.kind === "file");
  const locked = campaign !== null && !campaign.editable;

  const toggleAudience = (list: "audiences" | "excluded_audiences", value: string) => {
    const current = form.data[list];
    form.setData(
      list,
      current.includes(value) ? current.filter((item) => item !== value) : [...current, value],
    );
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();

    const payload = {
      ...form.data,
      auto_dismiss_seconds: form.data.auto_dismiss_seconds === "" ? null : Number(form.data.auto_dismiss_seconds),
      acknowledgement_label: form.data.acknowledgement_label.trim() === "" ? null : { ar: form.data.acknowledgement_label.trim() },
      action_label: form.data.action_label.trim() === "" ? null : { ar: form.data.action_label.trim() },
    };

    if (campaign) {
      form.transform(() => payload);
      form.put(campaign.urls.update, { preserveScroll: true, onSuccess: onDone });
    } else {
      form.transform(() => payload);
      form.post(storeUrl, { preserveScroll: true, onSuccess: onDone });
    }
  };

  const uploadMedia = async (kind: PopupMediaRow["kind"], file: File) => {
    if (!campaign) {
      return;
    }

    setUploading(true);
    setUploadError(null);

    const body = new FormData();
    body.append("kind", kind);
    body.append("file", file);

    try {
      const response = await fetch(campaign.urls.media, {
        method: "POST",
        body,
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error(String(response.status));
      }

      onDone();
    } catch {
      setUploadError(t("console_popup_messages.media.upload_failed"));
    } finally {
      setUploading(false);
    }
  };

  const addLink = () => {
    const text = linkDraft.text.trim();
    const url = linkDraft.targetType === "external" ? linkDraft.external.trim() : `popup-media:${linkDraft.mediaId}`;

    if (text === "" || (linkDraft.targetType === "external" ? url === "" : linkDraft.mediaId === "")) {
      return;
    }

    form.setData("links", [...form.data.links, { text, url }]);
    setLinkDraft({ text: "", targetType: "external", external: "", mediaId: "" });
  };

  const removeLink = (index: number) => {
    form.setData("links", form.data.links.filter((_, i) => i !== index));
  };

  const errorFor = (key: string) => (form.errors as Record<string, string>)[key];

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      {locked && (
        <p className="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-4 text-sm">
          {t("console_popup_messages.status_hint.locked_while_published")}
        </p>
      )}

      <form onSubmit={submit} className="grid gap-4">
        <label className="block">
          <span className="block mb-1">{t("console_popup_messages.fields.internal_name")}</span>
          <input
            className={field}
            required
            maxLength={limits.internalNameMax}
            disabled={locked}
            value={form.data.internal_name}
            onChange={(event) => form.setData("internal_name", event.target.value)}
          />
          <span className="text-xs">{t("console_popup_messages.fields.internal_name_help")}</span>
          {errorFor("internal_name") && <p role="alert" className="text-red-700 text-sm">{errorFor("internal_name")}</p>}
        </label>

        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.type")}</span>
            <select
              className={field}
              disabled={locked}
              value={form.data.type}
              onChange={(event) => form.setData("type", event.target.value)}
            >
              {Object.entries(options.types).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.display_mode")}</span>
            <select
              className={field}
              disabled={locked}
              value={form.data.display_mode}
              onChange={(event) => form.setData("display_mode", event.target.value)}
            >
              {Object.entries(options.displayModes).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
        </div>

        <fieldset className="grid gap-3 rounded-lg border border-[var(--line)] p-4" disabled={locked}>
          <legend className="px-1 text-sm font-bold">{t("console_popup_messages.fields.title_locale")} / {t("console_popup_messages.fields.body_locale")}</legend>

          {(["ar", "en", "fr"] as const).map((locale) => (
            <div key={locale} className="grid gap-2">
              <label className="block">
                <span className="block mb-1">{t(`console_popup_messages.fields.title_${locale}`)}</span>
                <input
                  className={field}
                  required={locale === "ar"}
                  maxLength={limits.titleMax}
                  value={form.data.title[locale]}
                  onChange={(event) => form.setData("title", { ...form.data.title, [locale]: event.target.value })}
                />
                {errorFor(`title.${locale}`) && <p role="alert" className="text-red-700 text-sm">{errorFor(`title.${locale}`)}</p>}
              </label>
              <label className="block">
                <span className="block mb-1">{t(`console_popup_messages.fields.body_${locale}`)}</span>
                <textarea
                  className={field}
                  rows={3}
                  required={locale === "ar"}
                  maxLength={limits.bodyMax}
                  value={form.data.body[locale]}
                  onChange={(event) => form.setData("body", { ...form.data.body, [locale]: event.target.value })}
                />
                {errorFor(`body.${locale}`) && <p role="alert" className="text-red-700 text-sm">{errorFor(`body.${locale}`)}</p>}
              </label>
            </div>
          ))}
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <fieldset className="rounded-lg border border-[var(--line)] p-4" disabled={locked}>
            <legend className="px-1 text-sm font-bold">{t("console_popup_messages.fields.audiences")}</legend>
            {Object.entries(options.audiences).map(([value, label]) => (
              <label key={value} className="flex items-center gap-2 py-1">
                <input
                  type="checkbox"
                  checked={form.data.audiences.includes(value)}
                  onChange={() => toggleAudience("audiences", value)}
                />
                {label}
              </label>
            ))}
            {errorFor("audiences") && <p role="alert" className="text-red-700 text-sm">{errorFor("audiences")}</p>}
          </fieldset>

          <fieldset className="rounded-lg border border-[var(--line)] p-4" disabled={locked}>
            <legend className="px-1 text-sm font-bold">{t("console_popup_messages.fields.excluded_audiences")}</legend>
            <p className="text-xs mb-2">{t("console_popup_messages.fields.excluded_audiences_help")}</p>
            {Object.entries(options.audiences).map(([value, label]) => (
              <label key={value} className="flex items-center gap-2 py-1">
                <input
                  type="checkbox"
                  checked={form.data.excluded_audiences.includes(value)}
                  onChange={() => toggleAudience("excluded_audiences", value)}
                />
                {label}
              </label>
            ))}
          </fieldset>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              disabled={locked}
              checked={form.data.is_dismissible}
              onChange={(event) => form.setData("is_dismissible", event.target.checked)}
            />
            {t("console_popup_messages.fields.is_dismissible")}
          </label>

          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              disabled={locked}
              checked={form.data.requires_acknowledgement}
              onChange={(event) => form.setData("requires_acknowledgement", event.target.checked)}
            />
            {t("console_popup_messages.fields.requires_acknowledgement")}
          </label>

          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.auto_dismiss_seconds")}</span>
            <input
              type="number"
              className={field}
              disabled={locked || form.data.requires_acknowledgement}
              min={limits.autoDismissMinSeconds}
              max={limits.autoDismissMaxSeconds}
              value={form.data.auto_dismiss_seconds}
              onChange={(event) => form.setData("auto_dismiss_seconds", event.target.value)}
            />
            <span className="text-xs">
              {form.data.requires_acknowledgement
                ? t("console_popup_messages.fields.auto_dismiss_disabled_hint")
                : t("console_popup_messages.fields.auto_dismiss_help")}
            </span>
            {errorFor("auto_dismiss_seconds") && <p role="alert" className="text-red-700 text-sm">{errorFor("auto_dismiss_seconds")}</p>}
          </label>
        </div>
        <p className="text-xs">{t("console_popup_messages.fields.safe_exit_hint")}</p>

        <div className="grid gap-4 sm:grid-cols-3">
          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.priority")}</span>
            <input
              type="number"
              className={field}
              disabled={locked}
              min={limits.priorityMin}
              max={limits.priorityMax}
              value={form.data.priority}
              onChange={(event) => form.setData("priority", Number(event.target.value))}
            />
            <span className="text-xs">{t("console_popup_messages.fields.priority_help")}</span>
          </label>

          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.starts_at")}</span>
            <input
              type="datetime-local"
              className={field}
              required
              disabled={locked}
              value={form.data.starts_at}
              onChange={(event) => form.setData("starts_at", event.target.value)}
            />
          </label>

          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.ends_at")}</span>
            <input
              type="datetime-local"
              className={field}
              disabled={locked}
              value={form.data.ends_at}
              onChange={(event) => form.setData("ends_at", event.target.value)}
            />
          </label>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.placement")}</span>
            <select
              className={field}
              disabled={locked}
              value={form.data.placement}
              onChange={(event) => form.setData("placement", event.target.value)}
            >
              {Object.entries(options.placements).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          {form.data.placement === "specific_page" && (
            <label className="block">
              <span className="block mb-1">{t("console_popup_messages.fields.page_key")}</span>
              <select
                className={field}
                disabled={locked}
                value={form.data.page_key}
                onChange={(event) => form.setData("page_key", event.target.value)}
              >
                <option value="" />
                {Object.entries(options.pageKeys).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </label>
          )}

          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.frequency")}</span>
            <select
              className={field}
              disabled={locked}
              value={form.data.frequency}
              onChange={(event) => form.setData("frequency", event.target.value)}
            >
              {Object.entries(options.frequencies).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
        </div>

        <fieldset className="rounded-lg border border-[var(--line)] p-4" disabled={locked}>
          <legend className="px-1 text-sm font-bold">{t("console_popup_messages.fields.action_type")}</legend>
          <select
            className={field}
            value={form.data.action_type}
            onChange={(event) => form.setData("action_type", event.target.value)}
          >
            <option value="">{t("console_popup_messages.fields.action_type_none")}</option>
            <option value="internal_page">{t("console_popup_messages.fields.action_type_internal_page")}</option>
            <option value="external_url">{t("console_popup_messages.fields.action_type_external_url")}</option>
          </select>

          {form.data.action_type === "internal_page" && (
            <label className="block mt-3">
              <span className="block mb-1">{t("console_popup_messages.fields.action_target_internal")}</span>
              <select
                className={field}
                value={form.data.internal_action_target}
                onChange={(event) => form.setData("internal_action_target", event.target.value)}
              >
                <option value="" />
                {Object.entries(options.pageKeys).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </label>
          )}

          {form.data.action_type === "external_url" && (
            <label className="block mt-3">
              <span className="block mb-1">{t("console_popup_messages.fields.action_target_external")}</span>
              <input
                className={field}
                dir="ltr"
                placeholder="https://"
                value={form.data.external_action_target}
                onChange={(event) => form.setData("external_action_target", event.target.value)}
              />
            </label>
          )}

          {form.data.action_type !== "" && (
            <label className="block mt-3">
              <span className="block mb-1">{t("console_popup_messages.fields.action_label")}</span>
              <input
                className={field}
                value={form.data.action_label}
                onChange={(event) => form.setData("action_label", event.target.value)}
              />
            </label>
          )}
        </fieldset>

        {form.data.requires_acknowledgement && (
          <label className="block">
            <span className="block mb-1">{t("console_popup_messages.fields.acknowledgement_label")}</span>
            <input
              className={field}
              disabled={locked}
              value={form.data.acknowledgement_label}
              onChange={(event) => form.setData("acknowledgement_label", event.target.value)}
            />
          </label>
        )}

        {campaign && (
          <fieldset className="rounded-lg border border-[var(--line)] p-4">
            <legend className="px-1 text-sm font-bold">{t("console_popup_messages.media.title")}</legend>
            <p className="text-xs mb-2">{t("console_popup_messages.media.hint")}</p>

            <ul className="grid gap-2 mb-3">
              {campaign.media.length === 0 && <li className="text-sm">{t("console_popup_messages.media.empty")}</li>}
              {campaign.media.map((media) => (
                <li key={media.id} className="flex items-center justify-between rounded-lg border border-[var(--line)] p-2 text-sm">
                  <span>{media.original_name} — {t(`console_popup_messages.media.kind_${media.kind}`)}</span>
                  <a href={media.url} target="_blank" rel="noreferrer" className="underline">
                    {t("console_popup_messages.actions.view")}
                  </a>
                </li>
              ))}
            </ul>

            <div className="grid gap-3 sm:grid-cols-4">
              {(["image", "video", "audio", "file"] as const).map((kind) => (
                <label key={kind} className="block">
                  <span className="block mb-1 text-sm">{t(`console_popup_messages.media.kind_${kind}`)}</span>
                  <input
                    type="file"
                    className={field}
                    disabled={uploading}
                    onChange={(event) => {
                      const file = event.target.files?.[0];
                      event.target.value = "";
                      if (file) {
                        void uploadMedia(kind, file);
                      }
                    }}
                  />
                </label>
              ))}
            </div>
            {uploading && <p className="text-sm mt-2">{t("console_popup_messages.media.uploading")}</p>}
            {uploadError && <p role="alert" className="text-red-700 text-sm mt-2">{uploadError}</p>}
          </fieldset>
        )}

        <fieldset className="rounded-lg border border-[var(--line)] p-4">
          <legend className="px-1 text-sm font-bold">{t("console_popup_messages.links.title")}</legend>
          <p className="text-xs mb-2">{t("console_popup_messages.links.hint")}</p>

          <ul className="grid gap-2 mb-3">
            {form.data.links.length === 0 && <li className="text-sm">{t("console_popup_messages.links.empty")}</li>}
            {form.data.links.map((link, index) => (
              <li key={index} className="flex items-center justify-between rounded-lg border border-[var(--line)] p-2 text-sm">
                <span>“{link.text}” → {link.url}</span>
                <button type="button" className="text-red-700 underline" disabled={locked} onClick={() => removeLink(index)}>
                  {t("console_popup_messages.actions.remove_link")}
                </button>
              </li>
            ))}
          </ul>

          {!locked && (
            <div className="grid gap-3 sm:grid-cols-4 items-end">
              <label className="block">
                <span className="block mb-1 text-sm">{t("console_popup_messages.links.text")}</span>
                <input
                  className={field}
                  maxLength={limits.linkTextMax}
                  placeholder={t("console_popup_messages.links.text_placeholder")}
                  value={linkDraft.text}
                  onChange={(event) => setLinkDraft({ ...linkDraft, text: event.target.value })}
                />
              </label>
              <label className="block">
                <span className="block mb-1 text-sm">{t("console_popup_messages.links.target_type")}</span>
                <select
                  className={field}
                  value={linkDraft.targetType}
                  onChange={(event) => setLinkDraft({ ...linkDraft, targetType: event.target.value as "external" | "media" })}
                >
                  <option value="external">{t("console_popup_messages.links.target_external")}</option>
                  <option value="media">{t("console_popup_messages.links.target_media")}</option>
                </select>
              </label>
              {linkDraft.targetType === "external" ? (
                <label className="block sm:col-span-2">
                  <span className="block mb-1 text-sm">{t("console_popup_messages.links.external_url")}</span>
                  <input
                    className={field}
                    dir="ltr"
                    maxLength={limits.linkUrlMax}
                    placeholder="https://"
                    value={linkDraft.external}
                    onChange={(event) => setLinkDraft({ ...linkDraft, external: event.target.value })}
                  />
                </label>
              ) : (
                <label className="block sm:col-span-2">
                  <span className="block mb-1 text-sm">{t("console_popup_messages.links.target_media")}</span>
                  {fileMedia.length === 0 ? (
                    <p className="text-sm">{t("console_popup_messages.links.target_media_empty")}</p>
                  ) : (
                    <select
                      className={field}
                      value={linkDraft.mediaId}
                      onChange={(event) => setLinkDraft({ ...linkDraft, mediaId: event.target.value })}
                    >
                      <option value="" />
                      {fileMedia.map((media) => (
                        <option key={media.id} value={media.id}>{media.original_name}</option>
                      ))}
                    </select>
                  )}
                </label>
              )}
              <button type="button" className="rounded-lg border border-[var(--line)] px-3 py-2 text-sm sm:col-span-4 sm:w-fit" onClick={addLink}>
                {t("console_popup_messages.actions.add_link")}
              </button>
            </div>
          )}
        </fieldset>

        <label className="block">
          <span className="block mb-1">{t("console_popup_messages.reason.label")}</span>
          <textarea
            className={field}
            rows={2}
            required
            minLength={3}
            maxLength={500}
            placeholder={t("console_popup_messages.reason.placeholder")}
            value={form.data.reason}
            onChange={(event) => form.setData("reason", event.target.value)}
          />
        </label>

        {errorFor("internal_name") === undefined && form.hasErrors && (
          <ul className="text-sm text-red-700">
            {Object.entries(form.errors).map(([key, message]) => (
              <li key={key} role="alert">{message}</li>
            ))}
          </ul>
        )}

        <div className="flex gap-3">
          <button
            type="submit"
            disabled={form.processing}
            className="rounded-lg bg-[var(--brand)] text-white px-4 py-2 disabled:opacity-50"
          >
            {t("console_popup_messages.actions.save")}
          </button>
          <button type="button" onClick={onCancel} className="rounded-lg border border-[var(--line)] px-4 py-2">
            {t("console_popup_messages.actions.cancel")}
          </button>
        </div>
      </form>
    </section>
  );
}
