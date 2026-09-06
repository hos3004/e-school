import { Link, router, usePage } from "@inertiajs/react";
import { useId, useState, type KeyboardEvent, type ReactNode } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Award,
  BookOpen,
  CalendarDays,
  Clock3,
  FileText,
  Globe2,
  GraduationCap,
  Mail,
  MapPin,
  Pencil,
  Phone,
  Printer,
  ShieldCheck,
  UserRound,
  Users,
} from "lucide-react";
import { useI18n } from "@/lib/i18n";
import "../../../css/console-profiles.css";

export type ProfileRow = Record<string, string | number | null>;
export type ProfileHub = Record<string, ProfileRow[]>;
export interface ProfilePerson {
  name: string;
  code: string;
  status: string;
  statusTone?: string;
  email?: string | null;
  phone?: string | null;
  username?: string | null;
  timezone?: string;
  location?: string;
  joinedAt?: string | null;
  avatarUrl?: string | null;
  bio?: string | null;
  notes?: string | null;
  specializations?: string[];
  birthDate?: string | null;
  gender?: string | null;
  nationality?: string | null;
}
export interface ProfileSession {
  id: string;
  title: string;
  course: string;
  group: string | null;
  teacher: string;
  status: string;
  statusLabel: string;
  start: string;
  end: string;
  attendance: string | null;
  attendanceLabel: string | null;
  attendedMinutes: number | null;
  url: string | null;
}
export interface ProfileWorkspace {
  month: string;
  until: string;
  generatedAt: string;
  sessions: ProfileSession[];
  counts: {
    completed: number;
    recorded: number;
    absent: number;
    attended: number;
  };
  individual: ProfileRow[];
  students: { id: string; name: string; code: string; url: string | null }[];
  assignments: {
    id: string;
    title: string;
    courseName: string;
    dueAt: string;
    submissionStatus: string;
    submissionStatusLabel: string;
    submittedAt: string | null;
    submissionContent: string | null;
    gradedAt: string | null;
    score: number | null;
    maxScore: number;
    feedback: string | null;
    url?: string | null;
  }[];
  learningReports: {
    id: string;
    sessionId: string;
    submittedAt: string;
    participation: number | null;
    performance: number | null;
    commitment: number | null;
    strengths: string | null;
    weaknesses: string | null;
    note: string | null;
    maxScore: number;
  }[];
  reports: {
    id: string;
    title: string;
    issuedAt: string | null;
    summary: string | null;
  }[];
}
interface Props {
  kind: "student" | "teacher";
  audience: "admin" | "self" | "teacher";
  person: ProfilePerson;
  hub: ProfileHub;
  workspace: ProfileWorkspace;
  timezone: string;
  backUrl: string;
  editUrl?: string | null;
  onEdit?: () => void;
  onPassword?: () => void;
  followupUrl?: string | null;
  duesUrl?: string | null;
  availabilityUrl?: string | null;
}
type Tab = "overview" | "study" | "schedule" | "activity";
function Card({
  title,
  icon,
  children,
  hint,
  action,
}: {
  title: string;
  icon: ReactNode;
  children: ReactNode;
  hint?: string;
  action?: ReactNode;
}) {
  return (
    <section className="pp-card">
      <div className="pp-card-heading">
        <div>
          <h3>
            {icon}
            {title}
          </h3>
          {hint && <p>{hint}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}
function Empty({ text }: { text?: string }) {
  const t = useI18n();
  const tr = (key: string) => t("console_profiles." + key);
  return (
    <div className="pp-empty-state">
      <BookOpen size={25} />
      <p>{text || tr("empty")}</p>
    </div>
  );
}
function Details({ rows }: { rows: [string, ReactNode][] }) {
  const t = useI18n();
  const tr = (key: string) => t("console_profiles." + key);
  return (
    <dl className="pp-details">
      {rows.map(([key, value], i) => (
        <div key={i}>
          <dt>{key}</dt>
          <dd>{value || tr("unknown")}</dd>
        </div>
      ))}
    </dl>
  );
}
function Records({
  rows,
  titleKey,
  format,
}: {
  rows: ProfileRow[];
  titleKey: string;
  format: (value: string | number | null, key: string) => ReactNode;
}) {
  const t = useI18n();
  const tr = (key: string) => t("console_profiles." + key);
  return rows.length ? (
    <div>
      {rows.map((row, index) => (
        <div className="pp-feature-row" key={String(row.id ?? index)}>
          <span className="pp-square">
            {titleKey === "group" ? (
              <Users size={19} />
            ) : (
              <BookOpen size={19} />
            )}
          </span>
          <div>
            <h4>{row[titleKey] || row.title || row.name || tr("unknown")}</h4>
            <dl className="pp-details pp-record-details">
              {Object.entries(row)
                .filter(
                  ([key, value]) =>
                    !["id", titleKey, "basis_value"].includes(key) &&
                    value !== null &&
                    value !== "",
                )
                .map(([key, value]) => (
                  <div key={key}>
                    <dt>{t("console_people.columns." + key)}</dt>
                    <dd>
                      <bdi>{format(value, key)}</bdi>
                    </dd>
                  </div>
                ))}
            </dl>
          </div>
        </div>
      ))}
    </div>
  ) : (
    <Empty />
  );
}

export default function ProfileView({
  kind,
  audience,
  person,
  hub,
  workspace,
  timezone,
  backUrl,
  editUrl,
  onEdit,
  onPassword,
  followupUrl,
  duesUrl,
  availabilityUrl,
}: Props) {
  const t = useI18n();
  const base = useId();
  const page = usePage();
  const [active, setActive] = useState<Tab>("overview");
  const [month, setMonth] = useState(workspace.month);
  const periodLabel = new Intl.DateTimeFormat("ar-u-nu-latn", {
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(workspace.month + "-01T12:00:00Z"));
  const teacher = kind === "teacher";
  const educational = audience === "teacher";
  const tr = (key: string) => t("console_profiles." + key);
  const tabs: [Tab, string][] = [
    ["overview", tr("overview")],
    ["study", tr(teacher ? "teacher_study" : "student_study")],
    ["schedule", tr("schedule")],
    ["activity", tr("activity")],
  ];
  function date(
    value: string | number | null | undefined,
    withTime = false,
  ): string {
    if (value === null || value === undefined || value === "")
      return tr("unknown");
    const raw = String(value);
    const onlyDate = /^\d{4}-\d{2}-\d{2}$/.test(raw);
    const parsed = new Date(onlyDate ? raw + "T12:00:00Z" : raw);
    return Number.isNaN(parsed.getTime())
      ? raw
      : new Intl.DateTimeFormat("ar-u-nu-latn", {
          dateStyle: "medium",
          ...(withTime && !onlyDate ? { timeStyle: "short" as const } : {}),
          timeZone: onlyDate ? "UTC" : timezone,
        }).format(parsed);
  }
  function recordValue(value: string | number | null, key: string) {
    if (key === "session_type" && typeof value === "string")
      return tr("session_types." + value);
    return /(_at|_from|_to|_date|_on)$/.test(key) && value
      ? date(value)
      : (value ?? tr("unknown"));
  }
  function keyDown(event: KeyboardEvent<HTMLButtonElement>, tab: Tab) {
    const index = tabs.findIndex(([key]) => key === tab);
    let next = index;
    if (event.key === "ArrowLeft") next = (index + 1) % tabs.length;
    else if (event.key === "ArrowRight")
      next = (index + tabs.length - 1) % tabs.length;
    else if (event.key === "Home") next = 0;
    else if (event.key === "End") next = tabs.length - 1;
    else return;
    event.preventDefault();
    setActive(tabs[next]![0]);
    document.getElementById(base + "-tab-" + tabs[next]![0])?.focus();
  }
  const groups = hub.groups || [];
  const enrollments = hub.enrollments || [];
  const qualifications = hub.qualifications || [];
  const upcoming = workspace.sessions.find(
    (session) =>
      ["scheduled", "confirmed", "in_progress"].includes(session.status) &&
      new Date(session.end).getTime() >
        new Date(workspace.generatedAt).getTime(),
  );
  const attendanceRate = workspace.counts.recorded
    ? Math.round((workspace.counts.attended / workspace.counts.recorded) * 100)
    : null;
  const subtitle =
    [
      ...groups.map((row) => row.group),
      ...workspace.individual.map((row) => row.course),
      ...qualifications.map((row) => row.course),
    ]
      .filter(Boolean)
      .slice(0, 2)
      .join(" · ") || tr(teacher ? "teacher" : "student");
  const edit = editUrl ? (
    <Link href={editUrl} className="pp-button pp-primary pp-no-print">
      <Pencil size={15} />
      {tr("edit")}
    </Link>
  ) : onEdit ? (
    <button className="pp-button pp-primary pp-no-print" onClick={onEdit}>
      <Pencil size={15} />
      {tr("edit")}
    </button>
  ) : null;
  const schedule = (
    <Card
      title={tr("schedule")}
      icon={<CalendarDays size={18} />}
      hint={tr("period_note")}
    >
      <form
        className="pp-period pp-no-print"
        onSubmit={(event) => {
          event.preventDefault();
          router.get(
            page.url.split("?")[0]!,
            {
              ...Object.fromEntries(
                new URLSearchParams(page.url.split("?")[1] || ""),
              ),
              profile_month: month,
            },
            { preserveScroll: true, preserveState: true },
          );
        }}
      >
        <label htmlFor={base + "-month"}>{tr("period")}</label>
        <input
          id={base + "-month"}
          type="month"
          required
          value={month}
          onChange={(event) => setMonth(event.target.value)}
          dir="ltr"
        />
        <button className="pp-button" type="submit">
          {tr("apply")}
        </button>
      </form>
      <p className="pp-section-subtitle">
        {periodLabel} · {tr("display_timezone")} <bdi>{timezone}</bdi>
      </p>
      {workspace.sessions.length ? (
        <div className="pp-table-wrap">
          <table className="pp-table">
            <thead>
              <tr>
                <th>{tr("date")}</th>
                <th>{tr("lesson")}</th>
                {!teacher && <th>{tr("teacher_column")}</th>}
                <th>{tr("session_status")}</th>
                {!teacher && <th>{tr("attendance_status")}</th>}
              </tr>
            </thead>
            <tbody>
              {workspace.sessions.map((session) => (
                <tr key={session.id}>
                  <td>
                    {date(session.start, true)}
                    <span className="pp-small-text">
                      {new Intl.DateTimeFormat("ar-u-nu-latn", {
                        hour: "2-digit",
                        minute: "2-digit",
                        timeZone: timezone,
                      }).format(new Date(session.end))}
                    </span>
                  </td>
                  <td>
                    {session.url ? (
                      <Link className="pp-attachment-link" href={session.url}>
                        {session.title || session.course}
                      </Link>
                    ) : (
                      session.title || session.course
                    )}
                    <span className="pp-small-text">
                      {session.group || tr("individual")}
                    </span>
                  </td>
                  {!teacher && <td>{session.teacher}</td>}
                  <td>
                    <span className="pp-badge pp-badge-neutral">
                      {session.statusLabel}
                    </span>
                  </td>
                  {!teacher && (
                    <td>
                      <span
                        className={
                          "pp-badge " +
                          (session.attendance === "absent"
                            ? "pp-badge-amber"
                            : "pp-badge-green")
                        }
                      >
                        {session.attendanceLabel || tr("unrecorded")}
                      </span>
                      {session.attendedMinutes !== null && (
                        <span className="pp-small-text">
                          {session.attendedMinutes} {tr("minutes")}
                        </span>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <Empty text={tr("empty_schedule")} />
      )}
      <p className="pp-table-note">{tr("attendance_hint")}</p>
    </Card>
  );
  const assignmentCard = (
    <Card title={tr("assignments")} icon={<FileText size={18} />}>
      {workspace.assignments.length ? (
        workspace.assignments.map((item) => (
          <div className="pp-line-item" key={item.id}>
            <span className="pp-square">
              <FileText size={18} />
            </span>
            <div>
              <h4>
                {item.url ? (
                  <Link className="pp-attachment-link" href={item.url}>
                    {item.title}
                  </Link>
                ) : (
                  item.title
                )}
              </h4>
              <p>{item.courseName}</p>
              <span className="pp-small-text">
                {tr("due")} · {date(item.dueAt, true)}
              </span>
              {item.submittedAt && (
                <p className="pp-small-text">
                  {tr("assignment_submitted_at")} ·{" "}
                  {date(item.submittedAt, true)}
                </p>
              )}
              {item.submissionContent && (
                <p className="pp-prose">
                  <strong>{tr("assignment_answer")}</strong> ·{" "}
                  {item.submissionContent}
                </p>
              )}
              {item.score !== null && (
                <p>
                  {tr("score")} ·{" "}
                  <bdi>
                    {item.score} / {item.maxScore}
                  </bdi>
                </p>
              )}
              {item.gradedAt && (
                <p className="pp-small-text">
                  {tr("assignment_graded_at")} · {date(item.gradedAt, true)}
                </p>
              )}
              {item.feedback && <p className="pp-prose">{item.feedback}</p>}
            </div>
            <span className="pp-badge pp-badge-neutral">
              {item.submissionStatusLabel}
            </span>
          </div>
        ))
      ) : (
        <Empty />
      )}
    </Card>
  );
  const progress = (
    <Card
      title={tr("progress")}
      icon={<GraduationCap size={18} />}
      hint={tr("period_note")}
    >
      {workspace.learningReports.length ? (
        workspace.learningReports.map((report) => (
          <div key={report.id} className="pp-progress-item">
            <h4>
              {workspace.sessions.find(
                (session) => session.id === report.sessionId,
              )?.title || tr("lesson")}
            </h4>
            <p>{date(report.submittedAt, true)}</p>
            {(["participation", "performance", "commitment"] as const).map(
              (key) =>
                report[key] !== null && (
                  <div key={key} className="pp-learning-score">
                    <span>{tr(key)}</span>
                    <bdi>
                      {report[key]} / {report.maxScore}
                    </bdi>
                    <progress
                      value={report[key]}
                      max={report.maxScore}
                      aria-label={tr(key)}
                    />
                  </div>
                ),
            )}
            {(["strengths", "weaknesses", "note"] as const).map(
              (key) =>
                report[key] && (
                  <p key={key} className="pp-prose">
                    <strong>
                      {tr(key === "note" ? "educational_note" : key)}:{" "}
                    </strong>
                    {report[key]}
                  </p>
                ),
            )}
          </div>
        ))
      ) : (
        <Empty />
      )}
    </Card>
  );
  const reports = (
    <Card title={tr("reports")} icon={<Award size={18} />}>
      {workspace.reports.length ? (
        workspace.reports.map((report) => (
          <div className="pp-line-item" key={report.id}>
            <span className="pp-square">
              <FileText size={18} />
            </span>
            <div>
              <h4>{report.title}</h4>
              {report.issuedAt && (
                <span className="pp-small-text">
                  {tr("issued")} · {date(report.issuedAt)}
                </span>
              )}
              {report.summary && <p className="pp-prose">{report.summary}</p>}
            </div>
          </div>
        ))
      ) : (
        <Empty />
      )}
    </Card>
  );
  const study = (
    <>
      <Card
        title={tr(educational ? "study_with_you" : "groups")}
        icon={<Users size={18} />}
      >
        <Records format={recordValue} rows={groups} titleKey="group" />
      </Card>
      <Card title={tr("individual")} icon={<BookOpen size={18} />}>
        <Records
          format={recordValue}
          rows={workspace.individual}
          titleKey="course"
        />
      </Card>
      {!educational && (
        <Card
          title={tr(teacher ? "qualifications" : "student_study")}
          icon={<GraduationCap size={18} />}
        >
          <Records
            format={recordValue}
            rows={teacher ? qualifications : enrollments}
            titleKey={teacher ? "course" : "program"}
          />
        </Card>
      )}
      {teacher && (
        <>
          <Card
            title={tr("availability")}
            icon={<Clock3 size={18} />}
            hint={tr("availability_hint")}
            action={
              availabilityUrl ? (
                <Link
                  className="pp-small-action pp-no-print"
                  href={availabilityUrl}
                >
                  {tr("edit_availability")}
                  <ArrowLeft size={13} />
                </Link>
              ) : undefined
            }
          >
            <div className="pp-availability">
              {(hub.availability || []).map((row) => (
                <div key={String(row.id)}>
                  <strong>
                    {row.weekday}
                    <span className="pp-small-text">
                      <bdi>{row.timezone}</bdi>
                    </span>
                  </strong>
                  <bdi>{row.time}</bdi>
                  <span className="pp-badge pp-badge-neutral">
                    {row.status}
                  </span>
                </div>
              ))}
            </div>
            {!hub.availability?.length && <Empty />}
          </Card>
          <Card title={tr("teacher_roster")} icon={<Users size={18} />}>
            {workspace.students.length ? (
              workspace.students.map((student) => (
                <div className="pp-line-item" key={student.id}>
                  <span className="pp-mini-avatar">
                    <UserRound size={18} />
                  </span>
                  <div>
                    <h4>{student.name}</h4>
                    <p>
                      <bdi>{student.code}</bdi>
                    </p>
                  </div>
                  {student.url && (
                    <Link
                      href={student.url}
                      className="pp-text-button pp-no-print"
                    >
                      {tr("open_profile")}
                      <ArrowLeft size={14} />
                    </Link>
                  )}
                </div>
              ))
            ) : (
              <Empty />
            )}
          </Card>
        </>
      )}
    </>
  );
  return (
    <article
      className={"people-profile" + (educational ? " pp-teaching-view" : "")}
      dir="rtl"
      lang="ar"
    >
      <div className="pp-toolbar pp-no-print">
        <Link href={backUrl} className="pp-back">
          <ArrowRight size={16} />
          {tr(
            educational
              ? "back_teacher"
              : audience === "self"
                ? "back_self"
                : "back_admin",
          )}
        </Link>
        <button className="pp-button" onClick={() => window.print()}>
          <Printer size={16} />
          {tr(educational ? "print_educational" : "print")}
        </button>
      </div>
      <header className="pp-hero">
        <div className="pp-cover">
          <span>
            <ShieldCheck size={13} />
            {tr(
              educational
                ? "educational"
                : audience === "self"
                  ? "self"
                  : "internal",
            )}
          </span>
          <BookOpen size={88} strokeWidth={0.7} className="pp-cover-mark" />
        </div>
        <div className="pp-identity">
          <div className="pp-avatar">
            {person.avatarUrl ? (
              <img src={person.avatarUrl} alt="" />
            ) : (
              person.name
                .split(/\s+/)
                .slice(0, 2)
                .map((part) => part[0])
                .join(" ")
            )}
          </div>
          <div className="pp-identity-copy">
            <div className="pp-name-line">
              <h1>{person.name}</h1>
              <span
                className={
                  "pp-badge " +
                  (educational || person.statusTone === "active"
                    ? "pp-badge-green"
                    : "pp-badge-amber")
                }
              >
                {educational ? tr("assigned") : person.status}
              </span>
            </div>
            <p>{subtitle}</p>
            <div className="pp-identity-meta">
              <span>
                <UserRound size={13} />
                <bdi>{person.code}</bdi>
              </span>
              {person.location && (
                <span>
                  <MapPin size={13} />
                  {person.location}
                </span>
              )}
              {person.joinedAt && (
                <span>
                  <CalendarDays size={13} />
                  {tr("joined")} {date(person.joinedAt)}
                </span>
              )}
            </div>
          </div>
          {!educational && edit}
        </div>
        <div className="pp-hero-foot">
          <div className="pp-quick-facts">
            <span>
              <b>
                {teacher
                  ? workspace.students.length
                  : groups.length + workspace.individual.length}
              </b>
              {tr(teacher ? "students" : "studies")}
            </span>
            <span>
              <b>{workspace.sessions.length}</b>
              {tr("sessions")}
            </span>
            <span>
              <b dir="ltr">
                {teacher
                  ? workspace.counts.completed
                  : attendanceRate === null
                    ? "—"
                    : attendanceRate + "%"}
              </b>
              {tr(teacher ? "completed" : "attendance")}
            </span>
          </div>
          <span className="pp-demo-label">
            <bdi>{periodLabel}</bdi>
          </span>
        </div>
      </header>
      {!educational && (
        <nav
          className="pp-tabs pp-no-print"
          role="tablist"
          aria-label={tr("profile_tabs")}
        >
          {tabs.map(([tab, label]) => (
            <button
              key={tab}
              id={base + "-tab-" + tab}
              role="tab"
              aria-selected={active === tab}
              aria-controls={base + "-panel-" + tab}
              tabIndex={active === tab ? 0 : -1}
              onClick={() => setActive(tab)}
              onKeyDown={(event) => keyDown(event, tab)}
            >
              {label}
            </button>
          ))}
        </nav>
      )}
      <div className="pp-layout">
        <div className="pp-main">
          {educational ? (
            <>
              {study}
              {progress}
              {schedule}
              {assignmentCard}
            </>
          ) : (
            <>
              <div
                className="pp-tab-panel"
                role="tabpanel"
                id={base + "-panel-overview"}
                aria-labelledby={base + "-tab-overview"}
                hidden={active !== "overview"}
              >
                <h2 className="pp-print-heading">{tr("overview")}</h2>
                <Card
                  title={tr(teacher ? "teacher_summary" : "summary")}
                  icon={<UserRound size={18} />}
                >
                  <p className="pp-prose">
                    {person.bio || tr("summary_empty")}
                  </p>
                  {!!person.specializations?.length && (
                    <div className="pp-tags">
                      {person.specializations.map((item) => (
                        <span key={item}>{item}</span>
                      ))}
                    </div>
                  )}
                </Card>
                <Card
                  title={tr(teacher ? "qualifications" : "current_study")}
                  icon={<GraduationCap size={18} />}
                  action={
                    <button
                      onClick={() => setActive("study")}
                      className="pp-small-action pp-no-print"
                    >
                      {tr("show_study")}
                      <ArrowLeft size={13} />
                    </button>
                  }
                >
                  {(teacher
                    ? qualifications
                    : [...groups, ...workspace.individual]
                  ).length ? (
                    (teacher
                      ? qualifications
                      : [...groups, ...workspace.individual]
                    )
                      .slice(0, 3)
                      .map((row, index) => (
                        <div
                          className="pp-feature-row"
                          key={String(row.id ?? index)}
                        >
                          <span className="pp-square">
                            <BookOpen size={18} />
                          </span>
                          <div>
                            <h4>{row.group || row.course}</h4>
                            <p>{row.program || row.teacher || row.code}</p>
                          </div>
                          {row.status && (
                            <span className="pp-badge pp-badge-green">
                              {row.status}
                            </span>
                          )}
                        </div>
                      ))
                  ) : (
                    <Empty />
                  )}
                </Card>
                <Card
                  title={tr("next")}
                  icon={<CalendarDays size={18} />}
                  action={
                    <button
                      className="pp-small-action pp-no-print"
                      onClick={() => setActive("schedule")}
                    >
                      {tr("show_schedule")}
                      <ArrowLeft size={13} />
                    </button>
                  }
                >
                  {upcoming ? (
                    <div className="pp-next-lesson">
                      <span className="pp-date-block">
                        <strong>
                          {new Intl.DateTimeFormat("ar-u-nu-latn", {
                            day: "numeric",
                            timeZone: timezone,
                          }).format(new Date(upcoming.start))}
                        </strong>
                        <span>
                          {new Intl.DateTimeFormat("ar-u-nu-latn", {
                            month: "short",
                            timeZone: timezone,
                          }).format(new Date(upcoming.start))}
                        </span>
                      </span>
                      <div>
                        <h4>{upcoming.title || upcoming.course}</h4>
                        <p>
                          {date(upcoming.start, true)} · <bdi>{timezone}</bdi>
                        </p>
                        <p>
                          {upcoming.teacher} ·{" "}
                          {upcoming.group || tr("individual")}
                        </p>
                      </div>
                      <span className="pp-badge pp-badge-green">
                        {upcoming.statusLabel}
                      </span>
                    </div>
                  ) : (
                    <Empty text={tr("no_next")} />
                  )}
                </Card>
                {followupUrl && (
                  <section className="pp-attention">
                    <Clock3 size={21} className="pp-attention-icon" />
                    <div>
                      <h3>
                        {tr(workspace.counts.absent ? "attention" : "followup")}
                      </h3>
                      <p>{tr("attention_hint")}</p>
                    </div>
                    <Link href={followupUrl} className="pp-button pp-no-print">
                      {tr("followup")}
                    </Link>
                  </section>
                )}
              </div>
              <div
                className="pp-tab-panel"
                role="tabpanel"
                id={base + "-panel-study"}
                aria-labelledby={base + "-tab-study"}
                hidden={active !== "study"}
              >
                <h2 className="pp-print-heading">
                  {tr(teacher ? "teacher_study" : "student_study")}
                </h2>
                {study}
                {!teacher && progress}
                {!teacher && assignmentCard}
                {!teacher && reports}
              </div>
              <div
                className="pp-tab-panel"
                role="tabpanel"
                id={base + "-panel-schedule"}
                aria-labelledby={base + "-tab-schedule"}
                hidden={active !== "schedule"}
              >
                <h2 className="pp-print-heading">{tr("schedule")}</h2>
                {schedule}
                {duesUrl && (
                  <Card title={tr("contracts")} icon={<Award size={18} />}>
                    <p className="pp-prose">{tr("dues_hint")}</p>
                    <Link href={duesUrl} className="pp-button pp-no-print">
                      {tr("dues")}
                    </Link>
                  </Card>
                )}
              </div>
              <div
                className="pp-tab-panel"
                role="tabpanel"
                id={base + "-panel-activity"}
                aria-labelledby={base + "-tab-activity"}
                hidden={active !== "activity"}
              >
                <h2 className="pp-print-heading">{tr("activity")}</h2>
                <Card
                  title={tr("files")}
                  icon={<FileText size={18} />}
                  hint={tr("files_hint")}
                >
                  {workspace.reports.length ? (
                    workspace.reports.map((item) => (
                      <div className="pp-line-item" key={item.id}>
                        <span className="pp-square">
                          <FileText size={18} />
                        </span>
                        <div>
                          <h4>{item.title}</h4>
                          <p>{date(item.issuedAt)}</p>
                          {item.summary && (
                            <p className="pp-prose">{item.summary}</p>
                          )}
                        </div>
                      </div>
                    ))
                  ) : (
                    <Empty />
                  )}
                </Card>
                <Card title={tr("timeline")} icon={<Clock3 size={18} />}>
                  <ol className="pp-timeline">
                    {person.joinedAt && (
                      <li>
                        <span className="pp-timeline-dot" />
                        <div>
                          <time>{date(person.joinedAt)}</time>
                          <h4>{tr("joined")}</h4>
                        </div>
                      </li>
                    )}
                    {[...enrollments, ...groups].map((row, index) => (
                      <li key={String(row.id ?? index)}>
                        <span className="pp-timeline-dot" />
                        <div>
                          <time>
                            {date(
                              row.activated_at ||
                                row.joined_at ||
                                row.assigned_from,
                            )}
                          </time>
                          <h4>{row.program || row.group}</h4>
                          <p>{row.status || row.role}</p>
                          {row.expected_return_date && (
                            <p>
                              {tr("return_date")} ·{" "}
                              {date(row.expected_return_date)}
                            </p>
                          )}
                        </div>
                      </li>
                    ))}
                  </ol>
                  {!person.joinedAt &&
                    !enrollments.length &&
                    !groups.length && <Empty />}
                </Card>
                {(person.notes ||
                  person.birthDate ||
                  person.gender ||
                  person.nationality) && (
                  <Card title={tr("personal")} icon={<UserRound size={18} />}>
                    <Details
                      rows={[
                        [
                          tr("birth_date"),
                          person.birthDate ? date(person.birthDate) : null,
                        ],
                        [tr("gender"), person.gender],
                        [tr("nationality"), person.nationality],
                      ]}
                    />
                    {person.notes && (
                      <div className="pp-local-details">
                        <h4>{tr("notes")}</h4>
                        <p className="pp-prose">{person.notes}</p>
                      </div>
                    )}
                  </Card>
                )}
              </div>
            </>
          )}
        </div>
        <aside className="pp-side">
          {educational ? (
            <>
              <Card title={tr("scope")} icon={<ShieldCheck size={17} />}>
                <p className="pp-prose">{tr("scope_hint")}</p>
                <div className="pp-inline-note">
                  <Globe2 size={15} />
                  <span>
                    {tr("display_timezone")} <bdi>{timezone}</bdi>
                  </span>
                </div>
              </Card>
              <Card title={tr("study_with_you")} icon={<Users size={17} />}>
                <Link href={backUrl} className="pp-text-button pp-no-print">
                  {tr("back_teacher")}
                  <ArrowLeft size={14} />
                </Link>
              </Card>
            </>
          ) : (
            <>
              <Card title={tr("contact")} icon={<Phone size={17} />}>
                {[
                  [Mail, "email", person.email],
                  [Phone, "phone", person.phone],
                  [MapPin, "location", person.location],
                  [Globe2, "timezone", person.timezone],
                ].map(([Icon, key, value], i) => {
                  const ContactIcon = Icon as typeof Mail;
                  return (
                    <div className="pp-contact-line" key={i}>
                      <ContactIcon size={16} />
                      <div>
                        <span>{tr(String(key))}</span>
                        <bdi>{String(value || tr("unknown"))}</bdi>
                      </div>
                    </div>
                  );
                })}
                <div className="pp-inline-note">
                  <Clock3 size={14} />
                  <span>
                    {tr("display_timezone")} <bdi>{timezone}</bdi>
                    <br />
                    {tr("time_hint")}
                  </span>
                </div>
              </Card>
              {hub.guardians && (
                <Card title={tr("guardian")} icon={<Users size={17} />}>
                  {hub.guardians.length ? (
                    hub.guardians.map((row) => (
                      <div key={String(row.id)}>
                        <div className="pp-guardian">
                          <span className="pp-mini-avatar">
                            <UserRound size={18} />
                          </span>
                          <div>
                            <h4>{row.name}</h4>
                            <p>{row.relationship}</p>
                          </div>
                        </div>
                        <Details
                          rows={[
                            [tr("phone"), <bdi>{row.phone}</bdi>],
                            [
                              t("console_people.columns.is_primary"),
                              row.is_primary,
                            ],
                          ]}
                        />
                      </div>
                    ))
                  ) : (
                    <Empty />
                  )}
                </Card>
              )}
              {(hub.contracts || hub.rates) && (
                <Card title={tr("contracts")} icon={<Award size={17} />}>
                  <Records
                    format={recordValue}
                    rows={hub.contracts || []}
                    titleKey="basis"
                  />
                  {!!hub.rates?.length && (
                    <div className="pp-local-details">
                      <h4>{tr("rates")}</h4>
                      <Records
                        format={recordValue}
                        rows={hub.rates}
                        titleKey="scope"
                      />
                    </div>
                  )}
                  {duesUrl && (
                    <Link
                      href={duesUrl}
                      className="pp-button pp-full-button pp-no-print"
                    >
                      {tr("dues")}
                    </Link>
                  )}
                </Card>
              )}
              <Card title={tr("account")} icon={<ShieldCheck size={17} />}>
                <Details
                  rows={[
                    [
                      tr("username"),
                      <bdi>{person.username || tr("unknown")}</bdi>,
                    ],
                    [tr("status"), person.status],
                    [tr("language"), tr("arabic")],
                  ]}
                />
                <p className="pp-small-text">{tr("account_hint")}</p>
                {onPassword && (
                  <button
                    className="pp-text-button pp-no-print"
                    onClick={onPassword}
                  >
                    {tr("password")}
                  </button>
                )}
              </Card>
            </>
          )}
        </aside>
      </div>
      <footer className="pp-print-footer">
        <div className="pp-print-brand">
          <img src="/images/academy-brand.png" alt={t("learning.brand")} />
          <span>
            {tr("print_footer")}
            <br />
            {person.name} · <bdi>{person.code}</bdi> ·{" "}
            {new Intl.DateTimeFormat("ar-u-nu-latn", {
              dateStyle: "medium",
              timeZone: timezone,
            }).format(new Date(workspace.generatedAt))}
          </span>
        </div>
      </footer>
    </article>
  );
}
