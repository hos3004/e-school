import type { ReactNode } from "react";
import { useI18n } from "@/lib/i18n";

export type Choice = {
  value: string;
  label: string;
  iso2?: string;
  timezones?: string[];
};
export type PersonKind = "students" | "teachers";
export const fieldClass = "console-control";
export const primaryClass = "console-button primary";
export const secondaryClass = "console-button";

export function Field({
  name,
  label,
  error,
  hint,
  optional = false,
  children,
}: {
  name: string;
  label: string;
  error?: string;
  hint?: string;
  optional?: boolean;
  children: ReactNode;
}) {
  const t = useI18n();
  return (
    <div
      className={
        "field" +
        (["full_name", "notes", "bio", "qualification_notes"].includes(name)
          ? " span2"
          : "")
      }
    >
      <label htmlFor={name}>
        {label}
        {optional && (
          <span className="ms-2">{t("console_people.optional")}</span>
        )}
      </label>
      {hint && <small id={name + "-hint"}>{hint}</small>}
      {children}
      {error && (
        <p id={name + "-error"} className="text-sm text-red-700" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
export function Section({
  id,
  number,
  title,
  description,
  children,
}: {
  id: string;
  number: string;
  title: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <section id={id} className="form-section">
      <h2>
        <span className="step-number">{number}</span>
        {title}
      </h2>
      {description && <p className="mb-5 text-slate-500">{description}</p>}
      <div className="field-grid">{children}</div>
    </section>
  );
}
