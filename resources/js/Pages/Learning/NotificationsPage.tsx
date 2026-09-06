import { Link } from "@inertiajs/react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import Notifications from "./Notifications";
export default function NotificationsPage({
  kind,
  timezone,
}: {
  kind: LearningKind;
  timezone: string;
}) {
  const t = useI18n();
  return (
    <LearningLayout kind={kind} title={t("learning.notifications")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.notices_kicker")}
          </div>
          <h1>{t("learning.notifications")}</h1>
        </div>
        <Link className="lp-text-action" href={`/learn/${kind}#notices`}>
          {t("learning.back_portal")}
        </Link>
      </div>
      <Notifications kind={kind} timezone={timezone} full />
    </LearningLayout>
  );
}
