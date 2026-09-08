import { Link, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps, Assignment, MonthlyReport, Session } from "@/types";
import {
  date,
  Empty,
  percent,
  ServiceLink,
  type Learner,
  type Program,
} from "./shared";
import Icon from "./Icon";
import AssignmentDrawer from "./AssignmentDrawer";
import Notifications from "./Notifications";
import LibraryPreview, { type LibraryPreviewData } from "./LibraryPreview";
interface Props {
  libraryPreview: LibraryPreviewData;
  teachingAssignments?: {
    id: string;
    title: string;
    dueAt: string | null;
    studentsCount: number;
  }[];
  kind: LearningKind;
  timezone: string;
  nextSession: Session | null;
  sessions: Session[];
  students: Learner[];
  programs: Program[];
  assignments: Assignment[];
  allAssignments?: Assignment[];
  attendanceRate: number | null;
  pendingAttendance: Session[];
  lateReports: Session[];
  capabilities: Record<string, boolean>;
  payrollEnabled: boolean;
  reports?: MonthlyReport[];
  availability?: {
    id: string;
    weekday: number;
    startTime: string;
    endTime: string;
    timezone: string;
    approvalStatus: string;
  }[];
  earnings?: {
    year: number;
    month: number;
    currency: string;
    netMinorUnits: number;
    sessionsCount: number;
  } | null;
}
function dayKey(value: string, zone: string) {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: zone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date(value));
}
function duration(session: Session) {
  return Math.round(
    (Date.parse(session.endsAt) - Date.parse(session.startsAt)) / 60000,
  );
}
export default function Dashboard({
  kind,
  timezone,
  nextSession,
  sessions,
  students,
  programs,
  assignments,
  allAssignments = assignments,
  attendanceRate,
  pendingAttendance,
  lateReports,
  capabilities,
  payrollEnabled,
  reports = [],
  availability = [],
  earnings,
  libraryPreview,
  teachingAssignments = [],
}: Props) {
  const t = useI18n();
  const { auth, console: context } = usePage<
    AppPageProps & { console?: { school?: { timezone?: string } } }
  >().props;
  const teacher = kind === "teacher";
  const home = `/learn/${kind}`;
  const [zone, setZone] = useState(timezone);
  const [range, setRange] = useState("today");
  const [assignment, setAssignment] = useState<Assignment | null>(null);
  const [track, setTrack] = useState<string | null>(null);
  const [now, setNow] = useState(() => new Date().toISOString());
  useEffect(() => {
    const timer = window.setInterval(
      () => setNow(new Date().toISOString()),
      30000,
    );
    return () => window.clearInterval(timer);
  }, []);
  const schoolZone = context?.school?.timezone ?? timezone;
  const zones = Array.from(
    new Set([
      timezone,
      schoolZone,
      "Africa/Cairo",
      "Asia/Riyadh",
      "Europe/Istanbul",
      "Europe/London",
      "America/New_York",
      "UTC",
    ]),
  );
  const visible = sessions.filter(
    (session) =>
      range === "week" || dayKey(session.startsAt, zone) === dayKey(now, zone),
  );
  const nextCanJoin = Boolean(
    nextSession &&
    capabilities["session.join"] &&
    ["scheduled", "confirmed", "in_progress"].includes(nextSession.status) &&
    Date.parse(now) >=
      Date.parse(nextSession.canJoinAt ?? nextSession.startsAt) &&
    Date.parse(now) <=
      Date.parse(nextSession.canJoinUntil ?? nextSession.endsAt),
  );
  const tracks = Array.from(
    new Map(
      students.flatMap((student) =>
        student.tracks.map((item) => [item.id, item] as const),
      ),
    ).values(),
  );
  const roster = track
    ? students.filter((student) =>
        student.tracks.some((item) => item.id === track),
      )
    : students;
  const taskCount = teacher
    ? pendingAttendance.length + lateReports.length
    : assignments.length;
  const attendancePercent =
    attendanceRate === null
      ? null
      : attendanceRate > 1
        ? attendanceRate
        : attendanceRate * 100;
  const money = (minor: number) =>
    `${minor < 0 ? "-" : ""}${Math.floor(Math.abs(minor) / 100).toLocaleString("en-US")}.${String(Math.abs(minor) % 100).padStart(2, "0")}`;
  const sectionHead = (
    title: string,
    kicker: string,
    action?: React.ReactNode,
  ) => (
    <div className="lp-section-head">
      <div>
        <div className="lp-kicker">{t(kicker)}</div>
        <h2>{t(title)}</h2>
      </div>
      {action}
    </div>
  );
  return (
    <LearningLayout kind={kind} title={t(`learning.${kind}_portal`)}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t(
              teacher
                ? "learning.workspace.teacher_kicker"
                : "learning.workspace.student_kicker",
            )}
          </div>
          <h1>
            {t("learning.workspace.hello")}{" "}
            {auth.user?.name?.trim().split(/\s+/)[0]}
            <span>
              ،{" "}
              {t(
                teacher
                  ? "learning.workspace.teacher_greeting"
                  : "learning.workspace.student_greeting",
              )}
            </span>
          </h1>
          <p>
            {t(teacher ? "learning.teacher_intro" : "learning.student_intro")}
          </p>
        </div>
        <label className="lp-zone">
          <Icon name="clock" size={17} />
          <span>
            {t("learning.workspace.display_timezone")}
            <select
              value={zone}
              onChange={(e) => setZone(e.target.value)}
              aria-label={t("learning.timezone")}
            >
              {zones.map((value) => (
                <option key={value} value={value}>
                  {value}
                </option>
              ))}
            </select>
          </span>
        </label>
      </div>
      <div className="lp-opening">
        <section className="lp-next">
          <div className="lp-next-meta">
            <span>
              <span className="lp-live-dot" />
              {t("learning.next_lesson")}
            </span>
            {nextSession && (
              <span className="lp-tag">
                {t(`statuses.${nextSession.status}`)}
              </span>
            )}
          </div>
          {nextSession ? (
            <>
              <div className="lp-next-content">
                <div>
                  <div className="lp-next-track">{nextSession.subject}</div>
                  <h2>{nextSession.title || nextSession.subject}</h2>
                  <p>
                    <Icon name="user" size={16} />
                    {nextSession.teacher?.name}
                  </p>
                </div>
                <div className="lp-next-clock">
                  <b dir="ltr">
                    {date(nextSession.startsAt, "ar", zone, true)}
                  </b>
                  <span>
                    {duration(nextSession)} {t("learning.workspace.minutes")} ·{" "}
                    {date(nextSession.startsAt, "ar", zone)}
                  </span>
                </div>
              </div>
              <div className="lp-next-actions">
                {nextCanJoin ? (
                  <ServiceLink
                    className="lp-btn"
                    href={`${home}/sessions/${nextSession.id}/join`}
                  >
                    <Icon name="play" size={17} />
                    {t("learning.join_lesson")}
                  </ServiceLink>
                ) : (
                  <Link
                    className="lp-btn"
                    href={`${home}/sessions/${nextSession.id}`}
                  >
                    <Icon name="calendar" size={17} />
                    {t("learning.open_lesson")}
                  </Link>
                )}
                <Link
                  className="lp-text-action"
                  href={`${home}/sessions/${nextSession.id}`}
                >
                  {t("learning.lesson_details")}
                </Link>
              </div>
              <div className="lp-next-note">
                <Icon name="clock" size={14} />
                <span>
                  {t("learning.workspace.school_time")}{" "}
                  <bdi>
                    {date(nextSession.startsAt, "ar", schoolZone, true)}
                  </bdi>{" "}
                  · {schoolZone}{" "}
                  {nextSession.canJoinAt && !nextCanJoin && (
                    <>
                      {" "}
                      · {t("learning.join_window")}{" "}
                      <bdi>{date(nextSession.canJoinAt, "ar", zone, true)}</bdi>
                    </>
                  )}
                </span>
              </div>
            </>
          ) : (
            <Empty>{t("learning.no_next_lesson")}</Empty>
          )}
        </section>
        <aside className="lp-priorities">
          <div className="lp-section-title">
            <h2>
              {t(
                teacher
                  ? "learning.workspace.finish_day"
                  : "learning.workspace.next_step",
              )}
            </h2>
            <span className="lp-count">
              {taskCount} {t("learning.workspace.tasks")}
            </span>
          </div>
          {teacher ? (
            <>
              {pendingAttendance[0] && (
                <Link
                  className="lp-priority-row"
                  href={`${home}/sessions/${pendingAttendance[0].id}#attendance`}
                >
                  <Icon name="check" />
                  <span>
                    <b>{t("learning.pending_attendance")}</b>
                    <small>
                      {pendingAttendance[0].title ||
                        pendingAttendance[0].subject}
                    </small>
                  </span>
                  <Icon name="arrow" size={16} />
                </Link>
              )}
              {lateReports[0] && (
                <Link
                  className="lp-priority-row"
                  href={`${home}/sessions/${lateReports[0].id}#report`}
                >
                  <Icon name="file" />
                  <span>
                    <b>{t("learning.late_reports")}</b>
                    <small>
                      {lateReports[0].title || lateReports[0].subject}
                    </small>
                  </span>
                  <Icon name="arrow" size={16} />
                </Link>
              )}
            </>
          ) : (
            assignments[0] && (
              <button
                className="lp-priority-row"
                onClick={() => setAssignment(assignments[0] ?? null)}
              >
                <Icon name="file" />
                <span>
                  <b>{assignments[0].title}</b>
                  <small>
                    {t("learning.workspace.due")}{" "}
                    {date(assignments[0].dueAt, "ar", zone)}
                  </small>
                </span>
                <Icon name="arrow" size={16} />
              </button>
            )
          )}
          {taskCount === 0 && (
            <div className="lp-priority-note">
              <Icon name="check" />
              <p>{t("learning.workspace.caught_up")}</p>
            </div>
          )}
          <div className="lp-priority-note">
            <Icon name="book" size={18} />
            <p>
              {t(
                teacher
                  ? "learning.workspace.teacher_priority_hint"
                  : "learning.workspace.student_priority_hint",
              )}
            </p>
          </div>
          <a className="lp-text-action" href="#tasks">
            {t("learning.workspace.view_tasks")}
            <Icon name="arrow" size={15} />
          </a>
        </aside>
      </div>
      <section id="schedule" className="lp-section">
        {sectionHead(
          "learning.schedule",
          "learning.workspace.schedule_kicker",
          <div
            className="lp-segmented"
            role="group"
            aria-label={t("learning.workspace.schedule_range")}
          >
            {["today", "week"].map((value) => (
              <button
                key={value}
                aria-pressed={range === value}
                onClick={() => setRange(value)}
              >
                {t(`learning.workspace.${value}`)}
              </button>
            ))}
          </div>,
        )}
        <div className="lp-session-list">
          {visible.length ? (
            visible.map((session) => (
              <article className="lp-session-row" key={session.id}>
                <div className="lp-session-time">
                  <b dir="ltr">{date(session.startsAt, "ar", zone, true)}</b>
                  <small>
                    {duration(session)} {t("learning.workspace.minutes")}
                  </small>
                </div>
                <div className="lp-session-description">
                  <Link href={`${home}/sessions/${session.id}`}>
                    {session.title || session.subject}
                  </Link>
                  <span>{session.subject}</span>
                  <small>
                    {date(session.startsAt, "ar", zone)}
                    {!teacher && session.teacher
                      ? ` · ${session.teacher.name}`
                      : ""}
                  </small>
                </div>
                <span className="lp-tag">
                  {t(`statuses.${session.status}`)}
                </span>
                <div className="lp-session-action">
                  <Link
                    className="lp-btn lp-btn-secondary"
                    href={`${home}/sessions/${session.id}`}
                  >
                    {t("learning.lesson_details")}
                    <Icon name="arrow" size={15} />
                  </Link>
                </div>
              </article>
            ))
          ) : (
            <Empty>{t("learning.no_sessions")}</Empty>
          )}
        </div>
        {capabilities["schedule.view"] && (
          <ServiceLink
            className="lp-text-action"
            href={`/learn/${kind}/schedule`}
          >
            {t("learning.full_schedule")}
          </ServiceLink>
        )}
      </section>
      <section id="studies" className="lp-section">
        {sectionHead(
          teacher
            ? "learning.workspace.teacher_studies"
            : "learning.my_studies",
          "learning.workspace.studies_kicker",
          <Link className="lp-text-action" href={`${home}/profile`}>
            {t("learning.my_profile")}
          </Link>,
        )}
        <div className="lp-study-list">
          {teacher
            ? tracks.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  className="lp-study-row"
                  aria-pressed={track === item.id}
                  onClick={() => setTrack(track === item.id ? null : item.id)}
                >
                  <span className="lp-study-icon">
                    <Icon name={item.kind === "individual" ? "book" : "user"} />
                  </span>
                  <span>
                    <b>{item.name}</b>
                    <small>
                      {t(
                        item.kind === "individual"
                          ? "learning.individual_quran"
                          : "learning.group",
                      )}
                    </small>
                  </span>
                  <span className="lp-study-meta">
                    {
                      students.filter((student) =>
                        student.tracks.some((value) => value.id === item.id),
                      ).length
                    }{" "}
                    {t("learning.workspace.students")}
                    <Icon name="arrow" size={17} />
                  </span>
                </button>
              ))
            : programs.map((program) => (
                <Link
                  href={`${home}/profile`}
                  className="lp-study-row"
                  key={program.id}
                >
                  <span className="lp-study-icon">
                    <Icon name="book" />
                  </span>
                  <span>
                    <b>{program.title}</b>
                    <small>{program.levelName}</small>
                  </span>
                  <span className="lp-study-meta">
                    <span className="lp-tag">
                      {t(`statuses.${program.status}`)}
                    </span>
                    <Icon name="arrow" size={17} />
                  </span>
                </Link>
              ))}
        </div>
        {(teacher ? !tracks.length : !programs.length) && (
          <Empty>
            {t(teacher ? "learning.no_students" : "learning.no_studies")}
          </Empty>
        )}
        {teacher && students.length > 0 && (
          <div className="lp-student-list lp-roster">
            <div className="lp-roster-heading">
              <b>
                {track
                  ? tracks.find((item) => item.id === track)?.name
                  : t("learning.my_students")}
              </b>
              {track && (
                <button
                  className="lp-text-action"
                  onClick={() => setTrack(null)}
                >
                  {t("learning.workspace.all_students")}
                </button>
              )}
            </div>
            {roster.map((student) => (
              <div key={student.id}>
                <span className="lp-avatar">{student.name.slice(0, 1)}</span>
                <span>
                  <Link
                    className="lp-student-name"
                    href={`/learn/teacher/students/${student.id}`}
                  >
                    {student.name}
                  </Link>
                  <small>
                    {student.tracks.map((item) => item.name).join(" · ")}
                  </small>
                </span>
                <Link
                  className="lp-student-profile-link"
                  href={`/learn/teacher/students/${student.id}`}
                >
                  {t("learning.view_profile")}
                  <Icon name="arrow" size={15} />
                </Link>
              </div>
            ))}
          </div>
        )}
        {!teacher && (
          <div className="lp-inline-details">
            <Link className="lp-text-action" href={`${home}/profile`}>
              {t("learning.workspace.study_status")}
            </Link>
          </div>
        )}
      </section>
      <section id="tasks" className="lp-section">
        {sectionHead(
          teacher
            ? "learning.workspace.teacher_tasks"
            : "learning.workspace.student_tasks",
          "learning.workspace.tasks_kicker",
          teacher &&
            (capabilities["assignment.manage"] ||
              capabilities["assignment.grade"]) ? (
            <Link className="lp-text-action" href="/learn/teacher/assignments">
              {t("learning.teaching.title")} <Icon name="arrow" size={16} />
            </Link>
          ) : undefined,
        )}
        <div className="lp-learning-columns">
          <div>
            <h3 className="lp-subheading">{t("learning.assignments")}</h3>
            {teacher
              ? teachingAssignments.map((item) => (
                  <Link
                    key={item.id}
                    className="lp-resource-row"
                    href={`/learn/teacher/assignments?assignment=${item.id}`}
                  >
                    <Icon name="file" />
                    <span>
                      <b>{item.title}</b>
                      <small>
                        {item.studentsCount}{" "}
                        {t("learning.teaching.submissions")}
                      </small>
                      {item.dueAt && (
                        <span className="lp-resource-state">
                          {date(item.dueAt, "ar", zone)}
                        </span>
                      )}
                    </span>
                    <Icon name="arrow" />
                  </Link>
                ))
              : allAssignments.map((item) => (
                  <button
                    key={item.id}
                    className="lp-resource-row"
                    onClick={() => setAssignment(item)}
                  >
                    <Icon name="file" />
                    <span>
                      <b>{item.title}</b>
                      <small>{item.courseName}</small>
                      <span className="lp-resource-state">
                        {t(`statuses.${item.submissionStatus}`)} ·{" "}
                        {date(item.dueAt, "ar", zone)}
                      </span>
                      {item.feedback && <small>{item.feedback}</small>}
                    </span>
                    <Icon name="arrow" />
                  </button>
                ))}
            {(teacher
              ? !teachingAssignments.length
              : !allAssignments.length) && (
              <Empty>{t("learning.no_assignments")}</Empty>
            )}
            {teacher && capabilities["assignment.manage"] && (
              <Link
                className="lp-text-action"
                href="/learn/teacher/assignments?create=1"
              >
                {t("learning.teaching.new")} <Icon name="arrow" size={16} />
              </Link>
            )}
          </div>
          {capabilities["content.view"] && (
            <LibraryPreview data={libraryPreview} />
          )}
        </div>
      </section>
      {teacher && (
        <section className="lp-section" id="teaching-work">
          {sectionHead(
            "learning.workspace.teaching_work",
            "learning.workspace.tasks_kicker",
            capabilities["session.postpone.approve"] ? (
              <Link
                className="lp-text-action"
                href="/learn/teacher/postponements"
              >
                {t("learning.requests.title")} <Icon name="arrow" size={16} />
              </Link>
            ) : undefined,
          )}
          <div className="lp-learning-columns">
            {[
              ["learning.pending_attendance", pendingAttendance, "attendance"],
              ["learning.late_reports", lateReports, "report"],
            ].map(([title, items, anchor]) => (
              <div key={String(title)}>
                <h3 className="lp-subheading">{t(String(title))}</h3>
                {(items as Session[]).map((item) => (
                  <Link
                    key={item.id}
                    href={`${home}/sessions/${item.id}#${anchor}`}
                    className="lp-resource-row"
                  >
                    <Icon name="check" />
                    <span>
                      <b>{item.title || item.subject}</b>
                      <small>{date(item.startsAt, "ar", zone)}</small>
                    </span>
                    <Icon name="arrow" />
                  </Link>
                ))}
                {!(items as Session[]).length && (
                  <Empty>{t("learning.workspace.no_pending_work")}</Empty>
                )}
              </div>
            ))}
          </div>
        </section>
      )}
      <section id="progress" className="lp-section">
        {sectionHead(
          teacher
            ? "learning.workspace.professional"
            : "learning.workspace.progress",
          teacher
            ? "learning.workspace.professional_kicker"
            : "learning.workspace.progress_kicker",
        )}
        {teacher ? (
          <div className="lp-personal-columns">
            <div>
              <Icon name="clock" size={21} />
              <h3>{t("learning.availability")}</h3>
              {availability.length ? (
                availability.map((slot) => (
                  <p key={slot.id}>
                    {t(`learning.workspace.weekdays.${slot.weekday}`)} ·{" "}
                    <bdi>
                      {slot.startTime.slice(0, 5)}–{slot.endTime.slice(0, 5)}
                    </bdi>{" "}
                    · {slot.timezone}{" "}
                    <span className="lp-tag">
                      {t(
                        slot.approvalStatus === "approved"
                          ? "console_quran.availability_live"
                          : `statuses.${slot.approvalStatus}`,
                      )}
                    </span>
                  </p>
                ))
              ) : (
                <p>{t("learning.workspace.no_availability")}</p>
              )}
              <ServiceLink
                className="lp-text-action"
                href="/learn/teacher/availability"
              >
                {t("learning.workspace.manage_availability")}
                <Icon name="arrow" size={16} />
              </ServiceLink>
            </div>
            <div>
              <Icon name="wallet" size={21} />
              <h3>{t("learning.earnings")}</h3>
              {payrollEnabled && capabilities["payroll.view"] ? (
                <>
                  {earnings ? (
                    <>
                      <p>
                        {earnings.sessionsCount}{" "}
                        {t("learning.workspace.ledger_sessions")} ·{" "}
                        <bdi>
                          {earnings.year}-
                          {String(earnings.month).padStart(2, "0")}
                        </bdi>
                      </p>
                      <div className="lp-amount">
                        <b dir="ltr">{money(earnings.netMinorUnits)}</b>
                        <span>{earnings.currency}</span>
                      </div>
                    </>
                  ) : (
                    <p>{t("learning.workspace.no_earnings")}</p>
                  )}
                  <ServiceLink
                    href="/learn/teacher/earnings"
                    className="lp-text-action"
                  >
                    {t("learning.workspace.earnings_details")}
                    <Icon name="arrow" size={16} />
                  </ServiceLink>
                </>
              ) : (
                <p>{t("learning.workspace.earnings_unavailable")}</p>
              )}
            </div>
          </div>
        ) : (
          <div className="lp-progress-grid">
            <div className="lp-progress-overview">
              <span className="lp-progress-icon">
                <Icon name="check" size={23} />
              </span>
              <h3>{t("learning.workspace.progress_title")}</h3>
              <p>{t("learning.workspace.attendance_hint")}</p>
              <Link className="lp-text-action" href={`${home}/profile`}>
                {t("learning.lessons_attendance")}
              </Link>
            </div>
            <div className="lp-progress-tracks">
              <div>
                <label htmlFor="attendance-progress">
                  <b>{t("learning.attendance_rate")}</b>
                  <span dir="ltr">
                    {attendanceRate === null
                      ? t("learning.no_data")
                      : percent(attendanceRate)}
                  </span>
                </label>
                {attendancePercent !== null && (
                  <progress
                    id="attendance-progress"
                    value={attendancePercent}
                    max={100}
                  />
                )}
              </div>
              {reports[0] ? (
                <div>
                  <b>{reports[0].title}</b>
                  <p>{reports[0].summary}</p>
                  {capabilities["session_report.view"] && (
                    <ServiceLink
                      href="/learn/student/reports"
                      className="lp-text-action"
                    >
                      {t("learning.progress_reports")}
                    </ServiceLink>
                  )}
                </div>
              ) : (
                <p>{t("learning.workspace.no_reports")}</p>
              )}
            </div>
          </div>
        )}
      </section>
      <section id="notices" className="lp-section">
        {sectionHead(
          "learning.notifications",
          "learning.workspace.notices_kicker",
          <Icon name="bell" size={21} />,
        )}
        <Notifications kind={kind} timezone={zone} />
      </section>
      {assignment && (
        <AssignmentDrawer
          key={assignment.id}
          assignment={assignment}
          timezone={zone}
          onClose={() => setAssignment(null)}
        />
      )}
    </LearningLayout>
  );
}
