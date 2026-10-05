import { usePage } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import AudienceMessaging, {
  type AudienceMessagingData,
} from "@/Components/Console/AudienceMessaging";
import TemplateManager, {
  type TemplateRow,
} from "@/Components/Console/TemplateManager";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";
import type { AppPageProps } from "@/types";
import NotificationsList from "../Learning/Notifications";

type ScheduledRow = {
  id: string;
  event_name: string;
  channel: string;
  category: string;
  scheduled_for: string | null;
  subject: string | null;
};

type Props = {
  messaging?: AudienceMessagingData | null;
  templates: TemplateRow[];
  templateLocales: string[];
  templateChannels: string[];
  scheduled: ScheduledRow[];
  abilities: { send: boolean; templates: boolean };
  templateUrls: { store: string } | null;
  displayTimezone: string;
};

export default function Notifications({
  messaging,
  templates,
  templateLocales,
  templateChannels,
  scheduled,
  abilities,
  templateUrls,
  displayTimezone,
}: Props) {
  const t = useI18n();
  const { console: context } = usePage<
    AppPageProps & { console: { timezone: string } }
  >().props;

  return (
    <ConsoleLayout
      section="during"
      title={t("notifications.title")}
      description={t("notifications.subtitle")}
    >
      {messaging && <AudienceMessaging messaging={messaging} />}

      {abilities.send && scheduled.length > 0 && (
        <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
          <h2 className="font-bold text-lg">
            {t("console_messaging.scheduled_title")}
          </h2>
          <div className="overflow-x-auto mt-3">
            <table className="w-full text-sm">
              <thead>
                <tr>
                  <th className="text-start p-2">
                    {t("console_messaging.fields.scheduled_for")}
                  </th>
                  <th className="text-start p-2">
                    {t("console_messaging.fields.channel")}
                  </th>
                  <th className="text-start p-2">
                    {t("console_messaging.fields.subject")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {scheduled.map((row) => (
                  <tr key={row.id} className="border-t border-[var(--line)]">
                    <td className="p-2">
                      {row.scheduled_for
                        ? formatDate(row.scheduled_for, displayTimezone)
                        : "—"}
                    </td>
                    <td className="p-2">
                      {t("console_messaging.channels." + row.channel)}
                    </td>
                    <td className="p-2">{row.subject ?? row.event_name}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {abilities.templates && templateUrls && (
        <TemplateManager
          data={{
            templates,
            locales: templateLocales,
            channels: templateChannels,
            storeUrl: templateUrls.store,
          }}
        />
      )}

      <section className="console-panel console-notifications">
        <NotificationsList timezone={context.timezone} kind="admin" full />
      </section>
    </ConsoleLayout>
  );
}
