import { router } from "@inertiajs/react";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import PopupMessageComposer, {
  type PopupCampaignRow,
  type PopupMessageLimits,
  type PopupMessageOptions,
} from "@/Components/Console/PopupMessageComposer";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

type Abilities = {
  create: boolean;
  update: boolean;
  publish: boolean;
  pause: boolean;
  archive: boolean;
  viewAnalytics: boolean;
};

type Props = {
  items: PopupCampaignRow[];
  limits: PopupMessageLimits;
  options: PopupMessageOptions;
  abilities: Abilities;
  urls: { store: string };
  displayTimezone: string;
};

type PendingAction = { campaign: PopupCampaignRow; ability: "publish" | "pause" | "archive" };

const STATUS_COLOR: Record<PopupCampaignRow["status"], string> = {
  draft: "bg-gray-100 text-gray-800",
  published: "bg-green-100 text-green-800",
  paused: "bg-amber-100 text-amber-800",
  archived: "bg-red-100 text-red-800",
};

export default function PopupMessages({ items, limits, options, abilities, urls, displayTimezone }: Props) {
  const t = useI18n();
  const [composer, setComposer] = useState<"new" | PopupCampaignRow | null>(null);
  const [pending, setPending] = useState<PendingAction | null>(null);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);

  const audienceLabel = (value: string) => options.audiences[value] ?? value;

  const audiencesSummary = (row: PopupCampaignRow) => {
    if (row.audiences.length === 0) {
      return "—";
    }

    const [first, ...rest] = row.audiences;

    return rest.length === 0
      ? audienceLabel(first as string)
      : `${audienceLabel(first as string)} ${t("console_popup_messages.list.audiences_more").replace(":count", String(rest.length))}`;
  };

  const startAction = (campaign: PopupCampaignRow, ability: PendingAction["ability"]) => {
    setReason("");
    setPending({ campaign, ability });
  };

  const confirmAction = () => {
    if (!pending || reason.trim().length < 3) {
      return;
    }

    const confirmText = t(`console_popup_messages.confirm.${pending.ability}`);
    if (!window.confirm(confirmText)) {
      return;
    }

    setBusy(true);
    router.post(
      pending.campaign.urls[pending.ability],
      { reason },
      {
        preserveScroll: true,
        onFinish: () => setBusy(false),
        onSuccess: () => setPending(null),
      },
    );
  };

  const composerCampaign = composer === "new" ? null : composer;

  return (
    <ConsoleLayout
      section="during"
      title={t("console_popup_messages.title")}
      description={t("console_popup_messages.subtitle")}
    >
      {abilities.create && composer === null && (
        <div className="my-4">
          <button
            type="button"
            className="rounded-lg bg-[var(--brand)] text-white px-4 py-2"
            onClick={() => setComposer("new")}
          >
            {t("console_popup_messages.actions.create")}
          </button>
        </div>
      )}

      {composer !== null && (
        <PopupMessageComposer
          campaign={composerCampaign}
          options={options}
          limits={limits}
          storeUrl={urls.store}
          onDone={() => {
            setComposer(null);
            router.reload({ only: ["items"] });
          }}
          onCancel={() => setComposer(null)}
        />
      )}

      {pending && (
        <div className="rounded-xl border border-amber-300 bg-amber-50 p-5 my-4">
          <p className="font-bold mb-2">
            {t(`console_popup_messages.actions.${pending.ability}`)}: {pending.campaign.internal_name}
          </p>
          <label className="block mb-3">
            <span className="block mb-1">{t("console_popup_messages.reason.label")}</span>
            <textarea
              className="w-full rounded-lg border border-[var(--line)] p-3"
              rows={2}
              minLength={3}
              maxLength={500}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              placeholder={t("console_popup_messages.reason.placeholder")}
            />
          </label>
          <div className="flex gap-3">
            <button
              type="button"
              disabled={busy || reason.trim().length < 3}
              onClick={confirmAction}
              className="rounded-lg bg-[var(--brand)] text-white px-4 py-2 disabled:opacity-50"
            >
              {t(`console_popup_messages.actions.${pending.ability}`)}
            </button>
            <button
              type="button"
              onClick={() => setPending(null)}
              className="rounded-lg border border-[var(--line)] px-4 py-2"
            >
              {t("console_popup_messages.actions.cancel")}
            </button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-[var(--line)] bg-white my-4">
        {items.length === 0 ? (
          <p className="p-5 text-sm">{t("console_popup_messages.list.empty")}</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--line)] text-start">
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.internal_name")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.type")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.status")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.audiences")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.display_mode")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.priority")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.window")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.media")}</th>
                <th className="p-3 text-start">{t("console_popup_messages.list.columns.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {items.map((row) => (
                <tr key={row.id} className="border-b border-[var(--line)]">
                  <td className="p-3 font-medium">{row.internal_name}</td>
                  <td className="p-3">{options.types[row.type] ?? row.type}</td>
                  <td className="p-3">
                    <span className={"rounded-full px-2 py-1 text-xs " + STATUS_COLOR[row.status]}>
                      {options.statuses[row.status] ?? row.status}
                    </span>
                  </td>
                  <td className="p-3">{audiencesSummary(row)}</td>
                  <td className="p-3">{options.displayModes[row.display_mode] ?? row.display_mode}</td>
                  <td className="p-3">{row.priority}</td>
                  <td className="p-3 text-xs">
                    {formatDate(row.starts_at, displayTimezone)} – {row.ends_at ? formatDate(row.ends_at, displayTimezone) : "∞"}
                  </td>
                  <td className="p-3 text-xs">
                    {row.media.length === 0 ? t("console_popup_messages.list.no_media") : row.media.length}
                  </td>
                  <td className="p-3">
                    <div className="flex flex-wrap gap-2">
                      {abilities.update && (
                        <button
                          type="button"
                          className="rounded-lg border border-[var(--line)] px-3 py-1"
                          onClick={() => setComposer(row)}
                        >
                          {t("console_popup_messages.actions.edit")}
                        </button>
                      )}
                      {abilities.publish && row.can_transition_to.published && (
                        <button
                          type="button"
                          className="rounded-lg border border-[var(--line)] px-3 py-1"
                          onClick={() => startAction(row, "publish")}
                        >
                          {t("console_popup_messages.actions.publish")}
                        </button>
                      )}
                      {abilities.pause && row.can_transition_to.paused && (
                        <button
                          type="button"
                          className="rounded-lg border border-[var(--line)] px-3 py-1"
                          onClick={() => startAction(row, "pause")}
                        >
                          {t("console_popup_messages.actions.pause")}
                        </button>
                      )}
                      {abilities.archive && row.can_transition_to.archived && (
                        <button
                          type="button"
                          className="rounded-lg border border-red-300 text-red-700 px-3 py-1"
                          onClick={() => startAction(row, "archive")}
                        >
                          {t("console_popup_messages.actions.archive")}
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </ConsoleLayout>
  );
}
