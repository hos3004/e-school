import type { ReactNode } from "react";

export type TeacherIconName =
  "calendar" | "check" | "clock" | "document" | "group" | "profile" | "wallet";
interface TeacherIconProps {
  className?: string;
  name: TeacherIconName;
}

export function TeacherIcon({ className = "size-5", name }: TeacherIconProps) {
  const common = {
    "aria-hidden": true,
    className,
    fill: "none",
    stroke: "currentColor",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 1.8,
    viewBox: "0 0 24 24",
  } as const;
  switch (name) {
    case "calendar":
      return (
        <svg {...common}>
          <rect height="16" rx="3" width="18" x="3" y="5" />
          <path d="M3 10h18M8 3v4M16 3v4M8 14h3M14 14h2M8 18h2" />
        </svg>
      );
    case "check":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="m8 12 2.5 2.5L16.5 9" />
        </svg>
      );
    case "clock":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 2" />
        </svg>
      );
    case "document":
      return (
        <svg {...common}>
          <path d="M6 3h8l4 4v14H6z" />
          <path d="M14 3v5h5M9 13h6M9 17h4" />
        </svg>
      );
    case "group":
      return (
        <svg {...common}>
          <circle cx="9" cy="9" r="3" />
          <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
          <circle cx="17" cy="10" r="2.5" />
          <path d="M15 15a4.5 4.5 0 0 1 5.5 4" />
        </svg>
      );
    case "profile":
      return (
        <svg {...common}>
          <circle cx="12" cy="8" r="4" />
          <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
        </svg>
      );
    case "wallet":
      return (
        <svg {...common}>
          <path d="M4 7.5h16v12H4a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2h13v3" />
          <path d="M15 12h5v4h-5a2 2 0 0 1 0-4Z" />
        </svg>
      );
  }
}

interface MetricTileProps {
  icon: TeacherIconName;
  label: ReactNode;
  value: ReactNode;
  emphasis?: "default" | "attention" | "brand";
}
const metricTone = {
  default: "border-[var(--line)] bg-[var(--surface-raised)] text-[var(--ink)]",
  attention:
    "border-[var(--accent)]/35 bg-[var(--accent-soft)] text-[var(--ink)]",
  brand: "border-[var(--brand)]/30 bg-[var(--brand-soft)] text-[var(--ink)]",
} as const;

export function MetricTile({
  emphasis = "default",
  icon,
  label,
  value,
}: MetricTileProps) {
  return (
    <div
      className={`flex min-w-0 items-center gap-3 rounded-[var(--radius-lg)] border px-4 py-3.5 ${metricTone[emphasis]}`}
    >
      <span className="grid size-10 shrink-0 place-items-center rounded-[var(--radius-md)] bg-[var(--surface)] text-[var(--brand)] shadow-[0_1px_2px_rgb(20_37_54/0.08)]">
        <TeacherIcon name={icon} />
      </span>
      <div className="min-w-0">
        <p className="text-xs font-semibold leading-5 text-[var(--ink-muted)] [text-wrap:pretty]">
          {label}
        </p>
        <p className="mt-0.5 text-xl font-semibold tabular-nums leading-none text-[var(--ink)]">
          {value}
        </p>
      </div>
    </div>
  );
}

interface SectionHeadingProps {
  description?: ReactNode;
  id?: string;
  title: ReactNode;
  trailing?: ReactNode;
}
export function SectionHeading({
  description,
  id,
  title,
  trailing,
}: SectionHeadingProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div className="min-w-0">
        <h2
          className="text-lg font-semibold leading-snug text-[var(--ink)] [text-wrap:balance] sm:text-xl"
          id={id}
        >
          {title}
        </h2>
        {description ? (
          <p className="mt-1 max-w-3xl text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty]">
            {description}
          </p>
        ) : null}
      </div>
      {trailing ? <div className="shrink-0">{trailing}</div> : null}
    </div>
  );
}

export const teacherFieldClasses =
  "mt-1.5 min-h-11 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-3.5 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] transition-[border-color,box-shadow] duration-150 placeholder:text-[var(--ink-muted)] focus-visible:border-[var(--brand)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)] sm:text-sm";
