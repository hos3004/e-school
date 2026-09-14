import { Link } from "@inertiajs/react";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import AudienceMessaging, {
  type AudienceMessagingData,
} from "@/Components/Console/AudienceMessaging";
import SessionTable, {
  type ReportRow,
} from "@/Components/Console/SessionTable";
import { Metric, Panel } from "@/Components/Console/DashboardParts";
import type { GroupItem } from "./Courses";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber } from "@/lib/console-format";
import "../../../css/console-dashboard.css";

type Props = {
  group: GroupItem;
  programs: { id: string; label: string }[];
  courses: Record<string, string>;
  members: {
    id: string;
    studentId: string;
    name: string;
    code: string;
    status: string;
    statusLabel: string;
    joinedAt: string | null;
    leftAt: string | null;
  }[];
  teachers: {
    id: string;
    staffId: string;
    name: string;
    courseId: string | null;
    role: string;
    from: string | null;
    until: string | null;
  }[];
  schedules: {
    id: string;
    course_id: string;
    teacher: string;
    timezone: string;
    starts_on: string;
    ends_on: string | null;
    interval_weeks: number;
    duration_minutes: number;
    weekly_slots: { weekday: number; start_time: string }[];
  }[];
  sessions: ReportRow[];
  summary: Record<string, number> | null;
  timezone: string;
  limitExceeded: boolean;
  abilities: {
    edit: boolean;
    schedule: boolean;
    students: boolean;
    teachers: boolean;
    placement: boolean;
    report: boolean;
  };
  messaging?: AudienceMessagingData | null;
};
export default function GroupProfile({
  group,
  programs,
  courses,
  members,
  teachers,
  schedules,
  sessions,
  summary,
  timezone,
  abilities,
  limitExceeded,
  messaging,
}: Props) {
  const t = useI18n();
  const [tab, setTab] = useState("overview");
  const days = [
    "sunday",
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
  ];
  const d = (value: string | null) =>
    value ? formatDate(value, timezone) : t("console_group.ongoing");
  return (
    <ConsoleLayout
      section="during"
      title={group.label}
      description={t("console_group.description")}
      actions={
        <>
          <Link href="/manage/groups" className="console-button">
            {t("console_group.back")}
          </Link>
          {abilities.edit && (
            <Link
              href={"/manage/groups?group=" + group.id}
              className="console-button primary"
            >
              {t("console_group.edit")}
            </Link>
          )}
          <button
            className="console-button"
            onClick={() => {
              setTab("overview");
              requestAnimationFrame(() => window.print());
            }}
          >
            {t("console_group.print")}
          </button>
        </>
      }
    >
      <div className="console-dashboard">
        <div className="console-group-identity">
          <span className="course-mark">
            <ConsoleIcon name="groups" />
          </span>
          <bdi>{group.code}</bdi>
          <span
            className={
              "console-status " +
              (group.status === "active" ? "success" : "warning")
            }
          >
            {t("console_courses.status." + group.status)}
          </span>
          <span>
            {t("console_group.group_timezone")}: <bdi>{group.timezone}</bdi>
          </span>
        </div>
        <div className="console-metrics">
          <Metric
            icon="students"
            label={t("console_group.current_members")}
            value={group.occupied_seats}
          />
          <Metric
            icon="groups"
            label={t("console_group.capacity")}
            value={group.capacity}
            note={group.capacity === null ? t("console.not_set") : undefined}
          />
          <Metric
            icon="today"
            label={t("console_group.schedules")}
            value={schedules.length}
          />
          {summary && (
            <Metric
              icon="check"
              label={t("console_group.sessions")}
              value={summary.total ?? 0}
            />
          )}
        </div>
        <div
          className="dashboard-tabs console-group-tabs"
          role="group"
          aria-label={t("console_group.file")}
        >
          {["overview", "members", "teachers", "schedule"].map((value) => (
            <button
              key={value}
              className={tab === value ? "current" : ""}
              aria-pressed={tab === value}
              onClick={() => setTab(value)}
            >
              {t("console_group." + value)}
            </button>
          ))}
        </div>
        <div className="console-group-grid">
          <div>
            {(tab === "overview" || tab === "members") && (
              <Panel
                title={t("console_group.members")}
                action={
                  abilities.placement && (
                    <Link className="console-link" href="/manage/placement">
                      {t("console_group.placement")} ←
                    </Link>
                  )
                }
              >
                {!abilities.students ? (
                  <div className="console-empty">
                    {t("console_group.no_permission")}
                  </div>
                ) : !members.length ? (
                  <div className="console-empty">
                    {t("console_group.no_members")}
                  </div>
                ) : (
                  <div className="console-table-wrap">
                    <table className="console-table">
                      <thead>
                        <tr>
                          {["name", "status", "joined", "left"].map((key) => (
                            <th key={key}>{t("console_group." + key)}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {members.map((member) => (
                          <tr key={member.id}>
                            <td>
                              <Link
                                className="console-link"
                                href={"/manage/students/" + member.studentId}
                              >
                                {member.name}
                              </Link>
                              <small>
                                <bdi>{member.code}</bdi>
                              </small>
                            </td>
                            <td>
                              <span
                                className={
                                  "console-status " +
                                  (member.status === "active" ? "success" : "")
                                }
                              >
                                {member.statusLabel}
                              </span>
                            </td>
                            <td>{d(member.joinedAt)}</td>
                            <td>{member.leftAt ? d(member.leftAt) : "—"}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
                <div className="console-settings-note">
                  {t("console_group.history")}
                </div>
              </Panel>
            )}
            {(tab === "overview" || tab === "teachers") && (
              <Panel
                title={t("console_group.teachers")}
                action={
                  abilities.edit && (
                    <Link
                      href={
                        "/manage/groups?group=" + group.id + "&action=assign"
                      }
                      className="console-link"
                    >
                      {t("console_group.assign")} ←
                    </Link>
                  )
                }
              >
                {teachers.length ? (
                  <div className="console-table-wrap">
                    <table className="console-table">
                      <thead>
                        <tr>
                          {["teacher", "course", "role", "dates"].map((key) => (
                            <th key={key}>{t("console_group." + key)}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {teachers.map((teacher) => (
                          <tr key={teacher.id}>
                            <td>
                              {abilities.teachers ? (
                                <Link
                                  className="console-link"
                                  href={"/manage/teachers/" + teacher.staffId}
                                >
                                  {teacher.name}
                                </Link>
                              ) : (
                                teacher.name
                              )}
                            </td>
                            <td>
                              {teacher.courseId
                                ? (courses[teacher.courseId] ??
                                  t("console.not_set"))
                                : "—"}
                            </td>
                            <td>{teacher.role}</td>
                            <td>
                              {d(teacher.from)}
                              <small>{d(teacher.until)}</small>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="console-empty">
                    {t("console_group.no_teachers")}
                  </div>
                )}
              </Panel>
            )}
            {(tab === "overview" || tab === "schedule") && (
              <>
                <Panel
                  title={t("console_group.schedule")}
                  action={
                    abilities.schedule && (
                      <Link
                        className="console-link"
                        href={"/manage/schedules/create?group=" + group.id}
                      >
                        {t("console_sessions.add")}
                      </Link>
                    )
                  }
                >
                  {schedules.length ? (
                    schedules.map((schedule) => (
                      <div className="console-group-schedule" key={schedule.id}>
                        <h3>
                          {courses[schedule.course_id] ?? t("console.not_set")}{" "}
                          · {schedule.teacher}
                        </h3>
                        <p>
                          {schedule.weekly_slots
                            .map(
                              (slot) =>
                                t("console.days." + days[slot.weekday]) +
                                " " +
                                slot.start_time,
                            )
                            .join(" · ")}
                        </p>
                        <div className="console-group-schedule-meta">
                          <span>
                            {formatNumber(schedule.duration_minutes)}{" "}
                            {t("console.minutes")}
                          </span>
                          <span>
                            {schedule.interval_weeks === 1
                              ? t("console_group.weekly")
                              : t("console_group.interval").replace(
                                  ":count",
                                  String(schedule.interval_weeks),
                                )}
                          </span>
                          <span>
                            {d(schedule.starts_on)} — {d(schedule.ends_on)}
                          </span>
                        </div>
                        <small>
                          {t("console_group.schedule_timezone")}{" "}
                          <bdi>{schedule.timezone}</bdi>
                        </small>
                        {abilities.schedule && (
                          <Link
                            className="console-link"
                            href={"/manage/schedules/" + schedule.id + "/edit"}
                          >
                            {t("console_sessions.edit")}
                          </Link>
                        )}
                      </div>
                    ))
                  ) : (
                    <div className="console-empty">
                      {t("console_group.no_schedules")}
                    </div>
                  )}
                </Panel>
                {abilities.report && (
                  <Panel
                    title={t("console_group.reports")}
                    subtitle={t("console_group.report_help")}
                    action={
                      <Link
                        href={
                          "/manage/reports?group_id=" +
                          group.id +
                          "&preset=this_month"
                        }
                        className="console-link"
                      >
                        {t("console.open")} ←
                      </Link>
                    }
                  >
                    {limitExceeded && (
                      <div className="console-feedback">
                        {t("console.limit_exceeded")}
                      </div>
                    )}
                    <SessionTable rows={sessions} timezone={timezone} compact />
                  </Panel>
                )}
              </>
            )}
          </div>
          <aside>
            <Panel title={t("console_group.programs")}>
              {programs.map((program) => (
                <div className="dashboard-activity" key={program.id}>
                  <strong>{program.label}</strong>
                </div>
              ))}
            </Panel>
            <Panel title={t("console_group.complete")}>
              <div className="console-panel-body">
                <dl className="console-group-summary">
                  <div>
                    <dt>{t("console_group.start")}</dt>
                    <dd>
                      {group.starts_on
                        ? d(group.starts_on)
                        : t("console.not_set")}
                    </dd>
                  </div>
                  <div>
                    <dt>{t("console_group.end")}</dt>
                    <dd>{d(group.ends_on)}</dd>
                  </div>
                </dl>
                {group.missing.length > 0 ? (
                  <div className="dashboard-quiet">
                    {t("console_courses.readiness_missing")}{" "}
                    {group.missing
                      .map((key) => t("console_courses.fields." + key))
                      .join("، ")}
                  </div>
                ) : (
                  <span className="console-status success">
                    {t("console_group.ready")}
                  </span>
                )}
              </div>
            </Panel>
          </aside>
        </div>
        {messaging && <AudienceMessaging messaging={messaging} />}
      </div>
    </ConsoleLayout>
  );
}
