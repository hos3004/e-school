import { Link } from "@inertiajs/react";
import { useState } from "react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { Session } from "@/types";
import { SessionRows } from "./shared";
export default function Schedule({
  kind,
  timezone,
  sessions,
}: {
  kind: LearningKind;
  timezone: string;
  sessions: Session[];
}) {
  const t = useI18n();
  const [query, setQuery] = useState("");
  const visible = sessions.filter((session) =>
    [session.title, session.subject, session.teacher?.name]
      .join(" ")
      .includes(query.trim()),
  );
  return (
    <LearningLayout kind={kind} title={t("learning.full_schedule")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.schedule_kicker")}
          </div>
          <h1>{t("learning.full_schedule")}</h1>
          <p>
            {t("learning.times_in")} {timezone}
          </p>
        </div>
        <Link className="lp-text-action" href={`/learn/${kind}#schedule`}>
          {t("learning.back_portal")}
        </Link>
      </div>
      <label className="lp-field lp-compact-filter console-filter-single">
        <span>{t("learning.services.search_lessons")}</span>
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
      </label>
      <section className="lp-section">
        <SessionRows sessions={visible} kind={kind} timezone={timezone} />
      </section>
    </LearningLayout>
  );
}
