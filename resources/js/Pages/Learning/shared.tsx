import { Link } from "@inertiajs/react";
import type { AnchorHTMLAttributes, PropsWithChildren, ReactNode } from "react";
import { useI18n } from "@/lib/i18n";
import type { Session } from "@/types";
import type { LearningKind } from "@/Layouts/LearningLayout";

export function Section({
  title,
  subtitle,
  action,
  children,
  id,
}: PropsWithChildren<{
  title: string;
  subtitle?: string;
  action?: ReactNode;
  id?: string;
}>) {
  return (
    <section className="learning-section" id={id}>
      <div className="learning-section-heading">
        <div>
          <h2>{title}</h2>
          {subtitle && <p>{subtitle}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}
export function Empty({ children }: PropsWithChildren) {
  return <p className="learning-empty">{children}</p>;
}
export function Errors({ errors }: { errors: Record<string, string> }) {
  return Object.keys(errors).length > 0 ? (
    <div className="learning-error" role="alert">
      <ul>
        {Object.entries(errors).map(([key, value]) => (
          <li key={key}>{value}</li>
        ))}
      </ul>
    </div>
  ) : null;
}
export function date(
  value: string,
  locale: string,
  timezone: string,
  time = false,
): string {
  return new Intl.DateTimeFormat(locale, {
    numberingSystem: "latn",
    timeZone: /^\d{4}-\d{2}-\d{2}$/.test(value) ? "UTC" : timezone,
    ...(time
      ? { hour: "2-digit", minute: "2-digit", hour12: false }
      : { day: "2-digit", month: "short", year: "numeric" }),
  }).format(new Date(value));
}
export function percent(value: number): string {
  return `${new Intl.NumberFormat("en", { maximumFractionDigits: 1 }).format(value > 1 ? value : value * 100)}%`;
}
export function SessionRows({
  sessions,
  kind,
  timezone,
}: {
  sessions: (Session & { attendanceStatus?: string | null })[];
  kind: LearningKind;
  timezone: string;
}) {
  const t = useI18n();
  const locale = "ar";
  if (!sessions.length) return <Empty>{t("learning.no_sessions")}</Empty>;
  return (
    <div className="learning-session-list">
      {sessions.map((session) => (
        <article className="learning-session-row" key={session.id}>
          <div className="learning-time">
            <strong>{date(session.startsAt, locale, timezone, true)}</strong>
            <small>{date(session.startsAt, locale, timezone)}</small>
          </div>
          <div className="learning-session-description">
            <Link href={`/learn/${kind}/sessions/${session.id}`}>
              {session.title || session.subject}
            </Link>
            <small>
              {session.subject}
              {kind === "student" && session.teacher
                ? ` · ${session.teacher.name}`
                : ""}
            </small>
          </div>
          <span className="learning-tag">
            {t(
              session.attendanceStatus
                ? `attendance.statuses.${session.attendanceStatus}`
                : `statuses.${session.status}`,
            )}
          </span>
          <Link
            className="learning-button secondary"
            href={`/learn/${kind}/sessions/${session.id}`}
          >
            {t("learning.lesson_details")}
          </Link>
        </article>
      ))}
    </div>
  );
}
export interface Track {
  id: string;
  name: string;
  kind: string;
}
export interface Learner {
  id: string;
  name: string;
  code: string;
  tracks: Track[];
}
export interface Program {
  id: string;
  title: string;
  status: string;
  levelName?: string | null;
  frozenReason?: string | null;
  expectedReturnDate?: string | null;
}

export function ServiceLink({
  children,
  ...props
}: PropsWithChildren<AnchorHTMLAttributes<HTMLAnchorElement>>) {
  const t = useI18n();
  return (
    <a
      {...props}
      target="_blank"
      rel="noopener noreferrer"
      title={t("learning.new_tab")}
    >
      {children}
      <span className="learning-new-tab" aria-hidden="true">
        ↗
      </span>
      <span className="learning-sr-only"> ({t("learning.new_tab")})</span>
    </a>
  );
}
