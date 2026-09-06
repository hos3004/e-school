import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import axios from "axios";
import { useI18n } from "@/lib/i18n";
import type { Defaults, Placement, Student } from "./QuranTypes";

export function QuranSheet({
  title,
  children,
  onClose,
}: {
  title: string;
  children: ReactNode;
  onClose: () => void;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const id = useId();
  useEffect(() => {
    const dialog = ref.current;
    dialog?.showModal();
    return () => dialog?.close();
  }, []);
  return (
    <dialog
      ref={ref}
      className="quran-sheet"
      aria-labelledby={id}
      onCancel={onClose}
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <header>
        <h2 id={id}>{title}</h2>
        <button
          type="button"
          className="console-button quran-close"
          aria-label={useI18n()("console_quran.close")}
          onClick={onClose}
        >
          ×
        </button>
      </header>
      <div className="quran-sheet-body">{children}</div>
    </dialog>
  );
}
export function QuranDays({
  value,
  onChange,
  disabled = false,
}: {
  value: Placement;
  onChange: (draft: Placement) => void;
  disabled?: boolean;
}) {
  const t = useI18n();
  const id = useId();
  return (
    <details className="quran-days">
      <summary
        className="console-control"
        aria-label={t("console_quran.choose_days")}
      >
        {value.weekly_slots.length
          ? value.weekly_slots
              .map((slot) => t("console_quran.days." + slot.weekday))
              .join("، ")
          : t("console_quran.choose_days")}
      </summary>
      <fieldset>
        <legend className="quran-visually-hidden">
          {t("console_quran.choose_days")}
        </legend>
        {Array.from({ length: 7 }, (_, day) => (
          <label key={day} htmlFor={id + day}>
            <input
              id={id + day}
              type="checkbox"
              disabled={disabled}
              checked={value.weekly_slots.some((slot) => slot.weekday === day)}
              onChange={(event) =>
                onChange({
                  ...value,
                  weekly_slots: event.target.checked
                    ? [
                        ...value.weekly_slots,
                        { weekday: day, start_time: "" },
                      ].sort((a, b) => a.weekday - b.weekday)
                    : value.weekly_slots.filter((slot) => slot.weekday !== day),
                })
              }
            />
            {t("console_quran.days." + day)}
          </label>
        ))}
      </fieldset>
    </details>
  );
}
export function requestError(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const errors = error.response?.data?.errors as
      Record<string, string[]> | undefined;
    if (errors) return Object.values(errors).flat().join(" ");
  }
  return fallback;
}
export function QuranTimes({
  student,
  value,
  onChange,
  defaults,
  disabled = false,
  compact = false,
}: {
  student: Student;
  value: Placement;
  onChange: (draft: Placement) => void;
  defaults: Defaults;
  disabled?: boolean;
  compact?: boolean;
}) {
  const t = useI18n();
  const [suggestions, setSuggestions] = useState<
    Record<number, { key: string; times: string[] }>
  >({});
  const [checking, setChecking] = useState<number | null>(null);
  const [error, setError] = useState("");
  const queryKey = JSON.stringify([
    value.staff_profile_id,
    value.duration_minutes,
    value.interval_weeks,
    value.timezone,
    value.starts_on,
    value.ends_on,
    student.schedule?.id,
  ]);
  const find = async (day: number) => {
    setChecking(day);
    setError("");
    try {
      const result = await axios.get<{ available_start_times: string[] }>(
        "/manage/quran/availability",
        {
          params: {
            staff_profile_id: value.staff_profile_id,
            weekdays: [day],
            duration_minutes: value.duration_minutes,
            interval_weeks: value.interval_weeks,
            timezone: value.timezone,
            starts_on: value.starts_on,
            ends_on: value.ends_on || null,
            ...(student.schedule
              ? { student_id: student.id, schedule_id: student.schedule.id }
              : {}),
          },
        },
      );
      setSuggestions((old) => ({
        ...old,
        [day]: { key: queryKey, times: result.data.available_start_times },
      }));
    } catch (error) {
      setError(requestError(error, t("console_quran.failed")));
    } finally {
      setChecking(null);
    }
  };
  return (
    <div className={compact ? "quran-time" : "quran-editor-times"}>
      {value.weekly_slots.length === 0 && (
        <span className="cell-sub">{t("console_quran.choose_days_first")}</span>
      )}
      {value.weekly_slots.map((slot) => {
        const times =
          suggestions[slot.weekday]?.key === queryKey
            ? suggestions[slot.weekday]?.times
            : undefined;
        const change = (time: string) =>
          onChange({
            ...value,
            weekly_slots: value.weekly_slots.map((item) =>
              item.weekday === slot.weekday
                ? { ...item, start_time: time }
                : item,
            ),
          });
        return (
          <div key={slot.weekday} className="quran-day-time">
            <label>
              <span>{t("console_quran.days." + slot.weekday)}</span>
              <input
                className="console-control"
                type="time"
                dir="ltr"
                step={defaults.time_step}
                value={slot.start_time}
                disabled={disabled}
                onChange={(event) => change(event.target.value)}
                aria-label={
                  t("console_quran.start_time") +
                  " " +
                  t("console_quran.days." + slot.weekday) +
                  " " +
                  student.name
                }
              />
            </label>
            {!compact && !disabled && (
              <button
                type="button"
                className="inline-link"
                disabled={
                  checking !== null ||
                  !value.staff_profile_id ||
                  !value.starts_on
                }
                onClick={() => void find(slot.weekday)}
              >
                {checking === slot.weekday
                  ? t("console_quran.checking")
                  : t("console_quran.suggest")}
              </button>
            )}
            {times !== undefined &&
              !compact &&
              (times.length ? (
                <select
                  className="console-control"
                  aria-label={
                    t("console_quran.available_times") +
                    " " +
                    t("console_quran.days." + slot.weekday)
                  }
                  value=""
                  onChange={(event) => change(event.target.value)}
                >
                  <option value="">{t("console_quran.choose_time")}</option>
                  {times.map((time) => (
                    <option key={time} value={time}>
                      {time}
                    </option>
                  ))}
                </select>
              ) : (
                <p className="cell-sub">{t("console_quran.no_times")}</p>
              ))}
          </div>
        );
      })}
      {error && (
        <p role="alert" className="quran-error">
          {error}
        </p>
      )}
    </div>
  );
}
export default function QuranEditor({
  student,
  value,
  onChange,
  defaults,
  teachers,
  busy,
  error,
  onSave,
  editLockHours,
}: {
  student: Student;
  value: Placement;
  onChange: (draft: Placement) => void;
  defaults: Defaults;
  teachers: Record<string, string>;
  busy: boolean;
  error: string;
  onSave: () => void;
  editLockHours: number;
}) {
  const t = useI18n();
  const id = useId();
  const field = (key: "starts_on" | "ends_on" | "timezone", type: string) => (
    <div className="field">
      <label htmlFor={id + key}>
        {t("console_quran." + key)}
        {key === "ends_on" && <small> · {t("console_quran.optional")}</small>}
      </label>
      <input
        className="console-control"
        id={id + key}
        type={type}
        dir="ltr"
        value={value[key] ?? ""}
        min={
          key === "starts_on" && !student.schedule
            ? defaults.starts_on
            : key === "ends_on"
              ? value.starts_on
              : undefined
        }
        required={key !== "ends_on"}
        onChange={(event) => onChange({ ...value, [key]: event.target.value })}
      />
    </div>
  );
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSave();
      }}
      className="quran-editor"
    >
      {student.schedule && (
        <p className="detail-note">
          {t("console_quran.edit_protection").replace(
            ":hours",
            String(editLockHours),
          )}
        </p>
      )}
      <fieldset disabled={busy}>
        <div className="field">
          <label htmlFor={id + "teacher"}>
            {t("console_quran.primary_teacher")}
          </label>
          <select
            id={id + "teacher"}
            className="console-control"
            required
            value={value.staff_profile_id}
            onChange={(event) =>
              onChange({ ...value, staff_profile_id: event.target.value })
            }
          >
            <option value="">{t("console_quran.choose_teacher")}</option>
            {!teachers[value.staff_profile_id] && value.staff_profile_id && (
              <option value={value.staff_profile_id}>
                {student.teacher_name ?? t("console_quran.teacher_unavailable")}
              </option>
            )}
            {Object.entries(teachers).map(([key, name]) => (
              <option key={key} value={key}>
                {name}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label>{t("console_quran.choose_days")}</label>
          <QuranDays value={value} onChange={onChange} />
        </div>
        <QuranTimes
          student={student}
          value={value}
          onChange={onChange}
          defaults={defaults}
        />
        <div className="field-grid">
          <div className="field">
            <label htmlFor={id + "duration"}>
              {t("console_quran.duration")}
            </label>
            <select
              id={id + "duration"}
              className="console-control"
              value={value.duration_minutes}
              onChange={(event) =>
                onChange({
                  ...value,
                  duration_minutes: Number(event.target.value),
                })
              }
            >
              {defaults.durations.map((duration) => (
                <option key={duration} value={duration}>
                  {duration}
                </option>
              ))}
            </select>
          </div>
          <div className="field">
            <label htmlFor={id + "interval"}>
              {t("console_quran.interval")}
            </label>
            <input
              id={id + "interval"}
              className="console-control"
              dir="ltr"
              type="number"
              min={1}
              max={defaults.max_interval}
              value={value.interval_weeks}
              onChange={(event) =>
                onChange({
                  ...value,
                  interval_weeks: Number(event.target.value),
                })
              }
            />
          </div>
          {field("starts_on", "date")}
          {field("ends_on", "date")}
        </div>
        {field("timezone", "text")}
      </fieldset>
      <p className="detail-note">{t("console_quran.availability_help")}</p>
      {error && (
        <div role="alert" className="quran-error">
          {error}
        </div>
      )}
      <button
        type="submit"
        className="console-button primary"
        disabled={
          busy ||
          !value.staff_profile_id ||
          value.weekly_slots.length === 0 ||
          value.weekly_slots.some((slot) => !slot.start_time)
        }
      >
        {busy
          ? t("console_quran.saving")
          : t(
              student.schedule
                ? "console_quran.save_changes"
                : "console_quran.save",
            )}
      </button>
    </form>
  );
}
