import { usePage } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
import NotificationsList from "../Learning/Notifications";

export default function Notifications() {
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
      <section className="console-panel console-notifications">
        <NotificationsList timezone={context.timezone} kind="admin" full />
      </section>
    </ConsoleLayout>
  );
}
