import { Link } from "@inertiajs/react";
import { useState } from "react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { Session } from "@/types";
import {
  StudentScheduleChangeRequests,
  TeacherScheduleChange,
  type StudentChangeRequest,
  type TeacherSchedule,
} from "./ScheduleChange";
import { SessionRows } from "./shared";
export default function Schedule({
  kind,
  timezone,
  sessions,
  permanentSchedules = [],
  scheduleChangeRequests = [],
  canRequestScheduleChange = false,
}: {
  kind: LearningKind;
  timezone: string;
  sessions: Session[];
  permanentSchedules?: TeacherSchedule[];
  scheduleChangeRequests?: StudentChangeRequest[];
  canRequestScheduleChange?: boolean;
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
      {kind === "student" && (
        <StudentScheduleChangeRequests requests={scheduleChangeRequests} />
      )}
      {kind === "teacher" && canRequestScheduleChange && (
        <TeacherScheduleChange schedules={permanentSchedules} />
      )}
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
