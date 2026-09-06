import { Head, useForm } from "@inertiajs/react";
import type { Page } from "@inertiajs/core";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useState, type FormEvent, type ReactNode } from "react";
import { useI18n } from "@/lib/i18n";
import {
  inputClass,
  buttonClass,
  primaryClass,
  type Kind,
  type Value,
  type RecordData,
  type AcademicItem,
  type GroupItem,
  type Props,
} from "./Courses";

export default function SetupEditor({
  kind,
  item,
  context,
  onClose,
  onSaved,
  ...props
}: Props & {
  kind: Kind;
  item?: AcademicItem | GroupItem;
  context?: RecordData;
  onClose: () => void;
  onSaved?: (id: string) => void;
}) {
  const t = useI18n();
  const isTeacher = kind === "teachers";
  const isEdit = !!item && !isTeacher;
  const record = item as AcademicItem | undefined;
  const [suggestedCode] = useState(
    () =>
      kind.slice(0, 1).toUpperCase() +
      "-" +
      crypto.randomUUID().replaceAll("-", "").slice(0, 10).toUpperCase(),
  );
  const initial: RecordData = isTeacher
    ? {
        course_id: context?.course_id ?? "",
        staff_profile_id: "",
        role: "lead",
        assigned_from: props.defaults.today,
        assigned_to: "",
      }
    : {
        code: suggestedCode,
        name: { ar: "" },
        description: { ar: "" },
        sort_order: 0,
        ...(kind === "groups"
          ? {
              capacity: "",
              timezone: props.defaults.timezone,
              starts_on: "",
              ends_on: "",
              program_ids: [],
            }
          : kind === "levels"
            ? { program_id: "" }
            : {
                is_active: true,
                target_gender: kind === "programs" ? "all" : "",
                age_from: "",
                age_to: "",
                ...(kind === "programs"
                  ? {
                      program_type: "ongoing",
                      start_date: "",
                      end_date: "",
                      duration_weeks: "",
                      default_session_minutes: props.defaults.sessionMinutes,
                      default_rate_amount: "",
                      currency: props.defaults.currency,
                      language: "",
                    }
                  : {
                      level_id: "",
                      session_mode: "group",
                      total_sessions: "",
                      default_duration_minutes: "",
                      sessions_per_week: "",
                      first_group_name: "",
                      first_group_capacity: "",
                      first_group_starts_on: "",
                      first_group_ends_on: "",
                    }),
              }),
        ...context,
      };
  if (record && !isTeacher) {
    Object.keys(initial).forEach((key) => {
      if (record[key] !== undefined) initial[key] = record[key] ?? "";
    });
    initial.name = { ar: record.name?.ar ?? "" };
    initial.description = {
      ar: (record.description as Record<string, string> | null)?.ar ?? "",
    };
  }
  const form = useForm<RecordData>(initial);
  const [selectedProgram, setSelectedProgram] = useState(
    props.levels.find((level) => level.id === initial.level_id)?.program_id ??
      "",
  );
  const [nested, setNested] = useState<"programs" | "levels" | null>(null);
  const close = () => {
    if (!form.isDirty || window.confirm(t("console_courses.discard_confirm")))
      onClose();
  };
  const error = (field: string) => form.errors[field];
  const val = (field: string) => String(form.data[field] ?? "");
  const set = (field: string, value: Value) => form.setData(field, value);
  const translated = (field: string) =>
    t("console_courses.fields." + field.replaceAll(".", "_"));
  const optionalLabel = (
    <span className="required-label">({t("console_courses.optional")})</span>
  );
  const errors = (name: string) =>
    error(name) ? (
      <small role="alert" id={"error-" + name} className="text-red-700">
        {error(name)}
      </small>
    ) : null;
  const field = (name: string, type = "text", optional = true) => (
    <div className="field" key={name}>
      <label htmlFor={"setup-" + name}>
        {translated(name)}{" "}
        {name === "default_rate_amount" && <bdi>{val("currency")}</bdi>}{" "}
        {optional && optionalLabel}
      </label>
      <input
        id={"setup-" + name}
        type={type}
        value={val(name)}
        onChange={(e) => set(name, e.target.value)}
        className={inputClass}
        required={!optional}
        inputMode={name === "default_rate_amount" ? "decimal" : undefined}
        dir={
          ["number", "date"].includes(type) ||
          ["code", "currency", "timezone", "default_rate_amount"].includes(name)
            ? "ltr"
            : undefined
        }
        aria-invalid={!!error(name)}
        aria-describedby={error(name) ? "error-" + name : undefined}
        min={
          type === "number"
            ? name === "capacity"
              ? props.defaults.capacityMin
              : 0
            : undefined
        }
        max={name === "capacity" ? props.defaults.capacityMax : undefined}
      />
      {errors(name)}
    </div>
  );
  const select = (
    name: string,
    options: { id: string; name: string }[],
    optional = false,
    disabled = false,
  ) => (
    <div className="field" key={name}>
      <label htmlFor={"setup-" + name}>
        {translated(name)} {optional && optionalLabel}
      </label>
      <select
        id={"setup-" + name}
        value={val(name)}
        className={inputClass + " select-control"}
        required={!optional}
        disabled={disabled}
        aria-invalid={!!error(name)}
        aria-describedby={error(name) ? "error-" + name : undefined}
        onChange={(e) => {
          set(name, e.target.value);
          if (name === "course_id") set("staff_profile_id", "");
          if (name === "program_type" && e.target.value === "ongoing")
            set("end_date", "");
        }}
      >
        <option value="">{t("console_courses.choose")}</option>
        {options.map((option) => (
          <option value={option.id} key={option.id}>
            {option.name}
          </option>
        ))}
      </select>
      {errors(name)}
    </div>
  );
  const choices = (names: string[]) =>
    names.map((name) => ({
      id: name,
      name: t("console_courses.options." + name),
    }));
  const nameField = !isTeacher && (
    <div className="field span2">
      <label htmlFor="setup-name">
        {t(
          "console_courses." +
            {
              items: "course_name",
              groups: "group_name",
              programs: "program_name",
              levels: "level_name",
              teachers: "group_name",
            }[kind],
        )}
      </label>
      <input
        id="setup-name"
        value={(form.data.name as Record<string, string>).ar}
        required
        maxLength={kind === "groups" ? 120 : 255}
        dir="rtl"
        className={inputClass}
        aria-invalid={!!error("name.ar")}
        aria-describedby={error("name.ar") ? "error-name.ar" : undefined}
        onChange={(e) => set("name", { ar: e.target.value })}
      />
      {errors("name.ar")}
    </div>
  );
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const endpoint =
      kind === "groups"
        ? "/manage/groups" + (item ? "/" + item.id : "")
        : kind === "teachers"
          ? "/manage/groups/" + item?.id + "/teachers"
          : "/manage/courses/" + kind + (item ? "/" + item.id : "");
    const options = {
      preserveScroll: true,
      onSuccess: (page: Page) => {
        if (onSaved)
          onSaved(
            new URL(page.url, window.location.origin).searchParams.get(
              "focus",
            ) ?? "",
          );
        else if (
          kind === "items" &&
          !isEdit &&
          form.data.first_group_name &&
          form.data.session_mode !== "individual"
        )
          window.location.assign(page.url);
        else onClose();
      },
    };
    form.transform((values) => ({
      ...values,
      ...(kind === "items" && values.session_mode === "individual"
        ? {
            first_group_name: "",
            first_group_capacity: "",
            first_group_starts_on: "",
            first_group_ends_on: "",
          }
        : {}),
    }));
    if (isEdit) form.patch(endpoint, options);
    else form.post(endpoint, options);
  };
  const programLabel =
    props.programs.find(
      (program) =>
        program.id === (kind === "items" ? selectedProgram : val("program_id")),
    )?.label ?? t("console_courses.unspecified");
  const levelLabel =
    props.levels.find((level) => level.id === val("level_id"))?.label ??
    t("console_courses.unspecified");
  const summary = [
    [
      translated("name"),
      isTeacher
        ? item?.label
        : (form.data.name as Record<string, string>)?.ar ||
          t("console_courses.unspecified"),
    ],
    [translated("code"), val("code") || t("console_courses.unspecified")],
    ...(kind === "items"
      ? [
          [translated("program_id"), programLabel],
          [translated("level_id"), levelLabel],
          ...(!isEdit &&
          val("first_group_name") &&
          val("session_mode") !== "individual"
            ? [[t("console_courses.group_name"), val("first_group_name")]]
            : []),
          [
            translated("session_mode"),
            t("console_courses.options." + val("session_mode")),
          ],
        ]
      : []),
    ...(kind === "levels" ? [[translated("program_id"), programLabel]] : []),
    ...(kind === "programs"
      ? [
          [
            translated("program_type"),
            t("console_courses.options." + val("program_type")),
          ],
          [
            translated("default_rate_amount"),
            val("default_rate_amount")
              ? val("default_rate_amount") + " " + val("currency")
              : t("console_courses.unspecified"),
          ],
        ]
      : []),
    ...(kind === "groups"
      ? [
          [
            translated("program_ids"),
            props.programs
              .filter((program) =>
                (form.data.program_ids as string[]).includes(program.id),
              )
              .map((program) => program.label)
              .join("، ") || t("console_courses.unspecified"),
          ],
          [
            translated("capacity"),
            val("capacity") || t("console_courses.unspecified"),
          ],
          [translated("timezone"), val("timezone")],
        ]
      : []),
  ];
  if (nested) {
    return (
      <SetupEditor
        key={nested}
        {...props}
        kind={nested}
        context={
          nested === "levels" ? { program_id: selectedProgram } : undefined
        }
        onClose={() => setNested(null)}
        onSaved={(id) => {
          if (id) {
            if (nested === "programs") {
              setSelectedProgram(id);
              set("level_id", "");
            } else set("level_id", id);
          }
          setNested(null);
        }}
      />
    );
  }
  const title = t(
    "console_courses.editor." + (isEdit ? "edit_" : "create_") + kind,
  );
  return (
    <ConsoleLayout
      title={title}
      section="before"
      description={t(
        onSaved
          ? "console_courses.context_help"
          : "console_courses.editor_help",
      )}
      actions={
        <button type="button" className={buttonClass} onClick={close}>
          {t(
            onSaved
              ? "console_courses.back_to_course"
              : "console_courses.back_to_center",
          )}
        </button>
      }
    >
      <Head title={title} />
      <div className="setup-layout console-setup-layout">
        <form onSubmit={submit} className="panel console-setup-panel">
          {Object.keys(form.errors).length > 0 && (
            <div
              role="alert"
              tabIndex={-1}
              className="feedback mx-6 border-red-200 bg-red-50 text-red-900"
            >
              <p>{t("console_courses.correct_errors")}</p>
              <ul>
                {Object.entries(form.errors).map(([key, message]) => (
                  <li key={key}>{message}</li>
                ))}
              </ul>
            </div>
          )}
          {!isTeacher && (
            <Section
              title={t(
                "console_courses." +
                  (kind === "items"
                    ? "course_identity"
                    : kind === "groups"
                      ? "group_identity"
                      : "identity"),
              )}
              step={1}
            >
              {nameField}
              {kind === "items" && (
                <>
                  <div className="field">
                    <label htmlFor="setup-program">
                      {translated("program_id")}
                    </label>
                    <select
                      id="setup-program"
                      value={selectedProgram}
                      required
                      className={inputClass + " select-control"}
                      onChange={(e) => {
                        setSelectedProgram(e.target.value);
                        set("level_id", "");
                      }}
                    >
                      <option value="">{t("console_courses.choose")}</option>
                      {props.programs.map((program) => (
                        <option key={program.id} value={program.id}>
                          {program.label}
                        </option>
                      ))}
                    </select>
                    {props.abilities.programs && (
                      <button
                        type="button"
                        className="inline-link"
                        onClick={() => setNested("programs")}
                      >
                        <span aria-hidden="true">+</span>
                        {t("console_courses.create_program")}
                      </button>
                    )}
                  </div>
                  <div className="field">
                    {select(
                      "level_id",
                      props.levels
                        .filter((level) => level.program_id === selectedProgram)
                        .map((level) => ({ id: level.id, name: level.label })),
                    )}
                    {props.abilities.programs && (
                      <button
                        type="button"
                        className="inline-link"
                        disabled={!selectedProgram}
                        onClick={() => setNested("levels")}
                      >
                        <span aria-hidden="true">+</span>
                        {t("console_courses.create_level")}
                      </button>
                    )}
                  </div>
                  {props.levels.length === 0 && (
                    <p className="span2 cell-sub">
                      {t("console_courses.need_level")}
                    </p>
                  )}
                </>
              )}
              {kind === "levels" &&
                select(
                  "program_id",
                  props.programs.map((program) => ({
                    id: program.id,
                    name: program.label,
                  })),
                  false,
                  isEdit,
                )}
              {field("code", "text", false)}
              {!isEdit && (
                <p className="cell-sub">
                  {t("console_courses.code_suggestion")}
                </p>
              )}
              {kind === "levels" && field("sort_order", "number", false)}
            </Section>
          )}
          {kind === "items" && (
            <Section title={t("console_courses.study_settings")} step={2}>
              {select("session_mode", choices(["group", "individual", "both"]))}
              {field("total_sessions", "number")}
              {field("default_duration_minutes", "number")}
              {field("sessions_per_week", "number")}
            </Section>
          )}
          {kind === "items" && !isEdit && props.abilities.groups && (
            <Section title={t("console_courses.first_group_title")} step={3}>
              <p className="span2 cell-sub">
                {t("console_courses.first_group_help")}
              </p>
              {val("session_mode") !== "individual" ? (
                <>
                  {field("first_group_name")}
                  {field("first_group_capacity", "number")}
                  {field("first_group_starts_on", "date")}
                  {field("first_group_ends_on", "date")}
                  <p className="span2 cell-sub">
                    {t("console_courses.first_group_teacher_help")}
                  </p>
                </>
              ) : (
                <p className="span2 cell-sub">
                  {t("console_courses.individual_setup_help")}
                </p>
              )}
            </Section>
          )}
          {kind === "programs" && (
            <Section title={t("console_courses.program_settings")} step={2}>
              {select("program_type", choices(["ongoing", "fixed_duration"]))}
              {field("duration_weeks", "number")}
              {field(
                "start_date",
                "date",
                val("program_type") !== "fixed_duration",
              )}
              {val("program_type") === "fixed_duration" &&
                field("end_date", "date", false)}
              {field("default_session_minutes", "number", false)}
              {field("currency", "text", false)}
              {field("default_rate_amount")}
              <p className="span2 cell-sub">{t("console_courses.rate_help")}</p>
              {field("language")}
              {field("sort_order", "number", false)}
            </Section>
          )}
          {["programs", "items"].includes(kind) && (
            <Section
              title={t("console_courses.audience")}
              step={
                kind === "items" && !isEdit && props.abilities.groups ? 4 : 3
              }
            >
              {select(
                "target_gender",
                choices(["all", "male", "female"]),
                kind === "items",
              )}
              {field("age_from", "number")}
              {field("age_to", "number")}
              <div className="field span2">
                <label htmlFor="setup-description">
                  {translated("description")} {optionalLabel}
                </label>
                <textarea
                  id="setup-description"
                  dir="rtl"
                  maxLength={2000}
                  value={(form.data.description as Record<string, string>).ar}
                  onChange={(e) => set("description", { ar: e.target.value })}
                  className={inputClass + " !min-h-24"}
                />
              </div>
              <label className="span2 flex min-h-11 items-center gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={!!form.data.is_active}
                  onChange={(e) => set("is_active", e.target.checked)}
                  className="size-4 accent-teal-800"
                />
                {t("console_courses.fields.is_active")}
              </label>
            </Section>
          )}
          {kind === "groups" && (
            <>
              <Section title={t("console_courses.academic_path")} step={2}>
                <fieldset className="span2 space-y-3">
                  <legend className="mb-3 text-sm font-semibold">
                    {t("console_courses.fields.program_ids")}
                  </legend>
                  {props.programs.map((program) => {
                    const checked = (
                      form.data.program_ids as string[]
                    ).includes(program.id);
                    const existing =
                      !!item &&
                      ((item as GroupItem).program_ids ?? []).includes(
                        program.id,
                      );
                    return (
                      <label
                        key={program.id}
                        className="flex min-h-10 items-center gap-3 text-sm"
                      >
                        <input
                          type="checkbox"
                          className="size-4 accent-teal-800"
                          checked={checked}
                          disabled={existing}
                          onChange={(e) =>
                            set(
                              "program_ids",
                              e.target.checked
                                ? [
                                    ...(form.data.program_ids as string[]),
                                    program.id,
                                  ]
                                : (form.data.program_ids as string[]).filter(
                                    (id) => id !== program.id,
                                  ),
                            )
                          }
                        />
                        {program.label}
                      </label>
                    );
                  })}
                  <p className="cell-sub">
                    {t("console_courses.program_links_help")}
                  </p>
                </fieldset>
              </Section>
              <Section title={t("console_courses.group_settings")} step={3}>
                {field("capacity", "number")}
                {field("timezone", "text", false)}
                {field("starts_on", "date")}
                {field("ends_on", "date")}
                <p className="span2 cell-sub">
                  {t("console_courses.draft_help")}
                </p>
              </Section>
            </>
          )}
          {isTeacher && (
            <Section title={t("console_courses.assign_teacher")} step={1}>
              <p className="span2 cell-title">{item?.label}</p>
              {select(
                "course_id",
                props.courses
                  .filter(
                    (course) =>
                      (item as GroupItem).program_ids.includes(
                        course.program_id ?? "",
                      ) &&
                      course.is_active &&
                      course.session_mode !== "individual",
                  )
                  .map((course) => ({ id: course.id, name: course.label })),
              )}
              {select(
                "staff_profile_id",
                props.teacherOptions[val("course_id")] ?? [],
              )}
              {val("course_id") &&
                (props.teacherOptions[val("course_id")] ?? []).length === 0 && (
                  <p className="span2 cell-sub">
                    {t("console_courses.no_teachers")}
                  </p>
                )}
              {select("role", choices(["lead", "assistant", "substitute"]))}
              {field("assigned_from", "date", false)}
              {field("assigned_to", "date")}
              <p className="span2 cell-sub">
                {t("console_courses.teacher_help")}
              </p>
            </Section>
          )}
          <footer className="savebar">
            <p>{t("console_courses.save_help")}</p>
            <div className="actions">
              <button type="button" className={buttonClass} onClick={close}>
                {t("console_courses.cancel")}
              </button>
              <button
                type="submit"
                className={primaryClass}
                disabled={form.processing}
              >
                {t(
                  "console_courses." +
                    (form.processing
                      ? "saving"
                      : kind === "groups" && !isEdit
                        ? "save_draft"
                        : "save"),
                )}
              </button>
            </div>
          </footer>
        </form>
        <aside className="panel summary">
          <div className="panel-head">
            <h2>{t("console_courses.summary")}</h2>
          </div>
          <div className="summary-content">
            <dl>
              {summary.map(([label, value]) => (
                <div className="definition" key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <div className="summary-note">
              {t("console_courses.summary_help")}
            </div>
          </div>
        </aside>
      </div>
    </ConsoleLayout>
  );
}
function Section({
  title,
  step,
  children,
}: {
  title: string;
  step: number;
  children: ReactNode;
}) {
  return (
    <section className="form-section">
      <h2>
        <span className="step-number">{String(step).padStart(2, "0")}</span>
        {title}
      </h2>
      <div className="field-grid">{children}</div>
    </section>
  );
}
