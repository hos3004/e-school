import { Head, Link, router, useRemember } from "@inertiajs/react";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import SetupEditor from "./CoursesEditor";

export type Kind = "programs" | "levels" | "items" | "groups" | "teachers";
export type Value =
  string | number | boolean | null | string[] | Record<string, string>;
export type RecordData = Record<string, Value>;
export type AcademicItem = RecordData & {
  id: string;
  code: string;
  name: Record<string, string>;
  label: string;
  program_id?: string;
  level_id?: string;
  is_active?: boolean;
};
export interface GroupItem {
  id: string;
  code: string;
  name: Record<string, string>;
  label: string;
  status: string;
  program_ids: string[];
  capacity: number | null;
  starts_on: string | null;
  ends_on: string | null;
  timezone: string;
  occupied_seats: number;
  missing: string[];
  can_activate: boolean;
  teachers: { id: string; name: string; course_id: string; role: string }[];
}
export interface Props {
  programs: AcademicItem[];
  levels: AcademicItem[];
  courses: AcademicItem[];
  groups: GroupItem[];
  teacherOptions: Record<string, { id: string; name: string }[]>;
  initialTab: "courses" | "programs" | "groups";
  initialGroupId?: string | null;
  initialCourseId?: string | null;
  initialGroupAction?: string | null;
  abilities: {
    programs: boolean;
    courses: boolean;
    groups: boolean;
    viewGroups: boolean;
    registration?: boolean;
  };
  defaults: {
    timezone: string;
    currency: string;
    sessionMinutes: number;
    capacityMin: number;
    capacityMax: number;
    today: string;
  };
}
export const inputClass = "console-control";
export const buttonClass = "console-button";
export const primaryClass = "console-button primary";

export default function Courses(props: Props) {
  const t = useI18n();
  const [search, setSearch] = useRemember("", "console.courses.search");
  const [programFilter, setProgramFilter] = useRemember(
    "",
    "console.courses.program",
  );
  const [studyFilter, setStudyFilter] = useRemember(
    "all",
    "console.courses.study",
  );
  const [statusFilter, setStatusFilter] = useRemember(
    "all",
    "console.groups.status",
  );
  const [editor, setEditor] = useState<{
    kind: Kind;
    item?: AcademicItem | GroupItem;
    context?: RecordData;
  } | null>(() => {
    const item =
      props.abilities.groups && props.initialTab === "groups"
        ? props.groups.find((group) => group.id === props.initialGroupId)
        : undefined;
    return item
      ? {
          kind: props.initialGroupAction === "assign" ? "teachers" : "groups",
          item,
          context: props.initialCourseId
            ? { course_id: props.initialCourseId }
            : undefined,
        }
      : null;
  });
  const [activationError, setActivationError] = useState("");
  const [activating, setActivating] = useState<string | null>(null);
  const tab = props.initialTab;
  const programName = (id?: string) =>
    props.programs.find((item) => item.id === id)?.label ??
    t("console_courses.unspecified");
  const levelName = (id?: string) =>
    props.levels.find((item) => item.id === id)?.label ??
    t("console_courses.unspecified");
  const matches = (text: string) =>
    text.toLowerCase().includes(search.trim().toLowerCase());
  const courses = props.courses.filter(
    (item) =>
      (!programFilter || item.program_id === programFilter) &&
      (studyFilter === "all" ||
        item.session_mode === studyFilter ||
        item.session_mode === "both") &&
      matches(
        [
          item.label,
          item.code,
          programName(item.program_id),
          levelName(item.level_id),
        ].join(" "),
      ),
  );
  const groups = props.groups.filter(
    (item) =>
      (!programFilter || item.program_ids.includes(programFilter)) &&
      (statusFilter === "all" || item.status === statusFilter) &&
      matches(
        [
          item.label,
          item.code,
          ...item.program_ids.map(programName),
          ...item.teachers.map((teacher) => teacher.name),
        ].join(" "),
      ),
  );
  const programs = props.programs.filter(
    (item) =>
      (!programFilter || item.id === programFilter) &&
      matches(
        [
          item.label,
          item.code,
          ...props.levels
            .filter((level) => level.program_id === item.id)
            .map((level) => level.label),
        ].join(" "),
      ),
  );
  const open = (
    kind: Kind,
    item?: AcademicItem | GroupItem,
    context?: RecordData,
  ) => setEditor({ kind, item, context });
  const activate = (group: GroupItem) => {
    if (!window.confirm(t("console_courses.activate_confirm"))) return;
    setActivationError("");
    setActivating(group.id);
    router.post(
      "/manage/groups/" + group.id + "/activate",
      {},
      {
        preserveScroll: true,
        onError: (errors) =>
          setActivationError(Object.values(errors).join(" ")),
        onFinish: () => setActivating(null),
      },
    );
  };
  if (editor) {
    return (
      <SetupEditor
        key={editor.kind + (editor.item?.id ?? "new")}
        {...props}
        {...editor}
        onClose={() => setEditor(null)}
      />
    );
  }
  const empty = (
    <div className="console-empty">
      <h2>{t("console_courses.empty")}</h2>
      <p>{t("console_courses.empty_help")}</p>
    </div>
  );
  const count =
    tab === "courses"
      ? courses.length
      : tab === "groups"
        ? groups.length
        : programs.length;
  const total =
    tab === "courses"
      ? props.courses.length
      : tab === "groups"
        ? props.groups.length
        : props.programs.length;
  const courseHeaders = [
    "course_identity",
    "study",
    "fields.total_sessions",
    "availability",
    "actions",
  ];
  const groupHeaders = [
    "group_name",
    "fields.program_ids",
    "fields.teacher",
    "seats",
    "readiness",
    "actions",
  ];
  const filterValues =
    tab === "courses"
      ? ["all", "group", "individual"]
      : ["all", "planning", "active", "completed"];
  return (
    <ConsoleLayout
      title={t("console_courses.title")}
      section="before"
      description={t("console_courses.description")}
      actions={
        <div className="actions">
          {props.abilities.programs && (
            <button
              className={tab === "programs" ? primaryClass : buttonClass}
              onClick={() => open("programs")}
            >
              <span aria-hidden="true">+</span>
              {t("console_courses.create_program")}
            </button>
          )}
          {tab === "groups" && props.abilities.groups ? (
            <button className={primaryClass} onClick={() => open("groups")}>
              <span aria-hidden="true">+</span>
              {t("console_courses.create_group")}
            </button>
          ) : (
            props.abilities.courses && (
              <button
                className={tab === "programs" ? buttonClass : primaryClass}
                onClick={() => open("items")}
              >
                <span aria-hidden="true">+</span>
                {t("console_courses.create_course")}
              </button>
            )
          )}
        </div>
      }
    >
      <Head title={t("console_courses.title")} />
      {activationError && (
        <p role="alert" className="console-flash error">
          {activationError}
        </p>
      )}
      <section className="panel console-catalog">
        <nav
          aria-label={t("console_courses.sections")}
          className="console-subtabs"
        >
          {(
            [
              {
                id: "courses",
                href: "/manage/courses",
                allowed: props.abilities.courses,
                count: props.courses.length,
              },
              {
                id: "groups",
                href: "/manage/groups",
                allowed: props.abilities.viewGroups,
                count: props.groups.length,
              },
              {
                id: "programs",
                href: "/manage/courses/programs",
                allowed: props.abilities.programs,
                count: props.programs.length,
              },
            ] as const
          )
            .filter((item) => item.allowed)
            .map((item) => (
              <Link
                key={item.id}
                href={item.href}
                preserveScroll
                aria-current={tab === item.id ? "page" : undefined}
              >
                {t("console_courses.tabs." + item.id)}
                <span className="console-label-count">
                  {String(item.count).padStart(2, "0")}
                </span>
              </Link>
            ))}
          {props.abilities.registration && (
            <Link href="/manage/registration">
              {t("console.nav.registration")}
            </Link>
          )}
        </nav>
        <div className="console-toolbar console-filter-bar">
          <div className="field console-field catalog-search console-filter-search">
            <label htmlFor="catalog-search">
              {t("console_courses.search")}
            </label>
            <input
              id="catalog-search"
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className={inputClass}
            />
          </div>
          <div className="field console-field catalog-program-filter">
            <label htmlFor="catalog-program">
              {t("console_courses.program_filter")}
            </label>
            <select
              id="catalog-program"
              value={programFilter}
              onChange={(e) => setProgramFilter(e.target.value)}
              className={inputClass + " select-control"}
            >
              <option value="">{t("console_courses.all_programs")}</option>
              {props.programs.map((program) => (
                <option key={program.id} value={program.id}>
                  {program.label}
                </option>
              ))}
            </select>
          </div>
          {tab !== "programs" && (
            <div
              className="filters console-filter-pills console-filter-span-three"
              aria-label={t("console_courses.filter_results")}
            >
              {filterValues.map((value) => (
                <button
                  key={value}
                  type="button"
                  className={
                    "pill " +
                    ((tab === "courses" ? studyFilter : statusFilter) === value
                      ? "current"
                      : "")
                  }
                  aria-pressed={
                    (tab === "courses" ? studyFilter : statusFilter) === value
                  }
                  onClick={() =>
                    tab === "courses"
                      ? setStudyFilter(value)
                      : setStatusFilter(value)
                  }
                >
                  {t(
                    "console_courses." +
                      (value === "all"
                        ? "all"
                        : (tab === "courses" ? "options." : "status.") + value),
                  )}
                </button>
              ))}
            </div>
          )}
        </div>
        {tab === "courses" &&
          (courses.length === 0 ? (
            empty
          ) : (
            <div
              className="console-table-wrap"
              tabIndex={0}
              role="region"
              aria-label={t("console_courses.tabs.courses")}
            >
              <table className="console-table console-course-table">
                <caption className="sr-only">
                  {t("console_courses.tabs.courses")}
                </caption>
                <thead>
                  <tr>
                    {courseHeaders.map((header) => (
                      <th key={header} scope="col">
                        {t("console_courses." + header)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {courses.map((course) => (
                    <tr key={course.id}>
                      <td>
                        <div className="course-name">
                          <span className="course-mark">
                            <ConsoleIcon name="courses" />
                          </span>
                          <div>
                            <button
                              className="cell-title"
                              onClick={() => open("items", course)}
                            >
                              {course.label}
                            </button>
                            <span className="cell-sub">
                              {programName(course.program_id)} /{" "}
                              {levelName(course.level_id)}
                            </span>
                            <small>
                              <bdi>{course.code}</bdi>
                            </small>
                          </div>
                        </div>
                      </td>
                      <td>
                        {t("console_courses.options." + course.session_mode)}
                        {course.default_duration_minutes && (
                          <span className="cell-sub">
                            <bdi>{String(course.default_duration_minutes)}</bdi>{" "}
                            {t("console_courses.minutes")}
                          </span>
                        )}
                      </td>
                      <td>
                        <bdi>
                          {course.total_sessions
                            ? String(course.total_sessions)
                            : t("console_courses.ongoing")}
                        </bdi>
                      </td>
                      <td>
                        <span
                          className={
                            "console-status " +
                            (course.is_active ? "success" : "")
                          }
                        >
                          {t(
                            "console_courses." +
                              (course.is_active ? "active" : "inactive"),
                          )}
                        </span>
                      </td>
                      <td>
                        <div className="course-row-actions">
                          {props.abilities.registration && (
                            <Link
                              className="inline-link"
                              href={
                                "/manage/registration?course=" +
                                encodeURIComponent(course.id)
                              }
                            >
                              {t("console_registration.forms_for_course")}
                            </Link>
                          )}
                          <button
                            className="inline-link"
                            onClick={() => open("items", course)}
                          >
                            {t("console_courses.edit_course")}
                            <span aria-hidden="true">←</span>
                          </button>
                          {props.abilities.groups &&
                            course.session_mode !== "individual" && (
                              <button
                                className="inline-link"
                                onClick={() =>
                                  open("groups", undefined, {
                                    program_ids: [course.program_id ?? ""],
                                  })
                                }
                              >
                                {t("console_courses.create_group")}
                              </button>
                            )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ))}
        {tab === "groups" &&
          (groups.length === 0 ? (
            empty
          ) : (
            <div
              className="console-table-wrap"
              tabIndex={0}
              role="region"
              aria-label={t("console_courses.tabs.groups")}
            >
              <table className="console-table console-course-table">
                <caption className="sr-only">
                  {t("console_courses.tabs.groups")}
                </caption>
                <thead>
                  <tr>
                    {groupHeaders.map((header) => (
                      <th key={header} scope="col">
                        {t("console_courses." + header)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {groups.map((group) => (
                    <tr key={group.id}>
                      <td>
                        <div className="course-name">
                          <span className="course-mark">
                            <ConsoleIcon name="students" />
                          </span>
                          <div>
                            <Link
                              className="cell-title"
                              href={"/manage/groups/" + group.id}
                            >
                              {group.label}
                            </Link>
                            <small>
                              <bdi>{group.code}</bdi>
                            </small>
                            <span className="cell-sub">
                              {t("console_courses.fields.starts_on")}:{" "}
                              <bdi>
                                {group.starts_on ||
                                  t("console_courses.unspecified")}
                              </bdi>
                            </span>
                          </div>
                        </div>
                      </td>
                      <td>
                        {group.program_ids
                          .map((id) => programName(id))
                          .join(" · ") || t("console_courses.unspecified")}
                      </td>
                      <td>
                        {group.teachers
                          .map((teacher) => teacher.name)
                          .join(" · ") || t("console_courses.unassigned")}
                      </td>
                      <td>
                        <bdi>
                          {String(group.occupied_seats)} /{" "}
                          {group.capacity === null
                            ? t("console_courses.unspecified")
                            : String(group.capacity)}
                        </bdi>
                      </td>
                      <td>
                        <span
                          className={
                            "console-status " +
                            (group.status === "active"
                              ? "success"
                              : group.status === "planning"
                                ? "warning"
                                : "")
                          }
                        >
                          {t("console_courses.status." + group.status)}
                        </span>
                        {group.status === "planning" && (
                          <span className="cell-sub">
                            {group.missing.length
                              ? t("console_courses.readiness_missing") +
                                " " +
                                group.missing
                                  .map((field) =>
                                    t("console_courses.fields." + field),
                                  )
                                  .join("، ")
                              : t("console_courses.readiness_ready")}
                          </span>
                        )}
                      </td>
                      <td>
                        <Link
                          className="inline-link"
                          href={"/manage/groups/" + group.id}
                        >
                          {t("console_group.file")} ←
                        </Link>
                        {props.abilities.groups && (
                          <div className="course-row-actions">
                            <button
                              className="inline-link"
                              onClick={() => open("groups", group)}
                            >
                              {t("console_courses.edit_group")}
                              <span aria-hidden="true">←</span>
                            </button>
                            {["planning", "active"].includes(group.status) && (
                              <button
                                className="inline-link"
                                onClick={() => open("teachers", group)}
                              >
                                {t("console_courses.assign_teacher")}
                              </button>
                            )}
                            {group.status === "planning" && (
                              <button
                                className="inline-link"
                                disabled={
                                  !group.can_activate || activating !== null
                                }
                                onClick={() => activate(group)}
                              >
                                {activating === group.id
                                  ? t("console_courses.saving")
                                  : t("console_courses.activate")}
                              </button>
                            )}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ))}
        {tab === "programs" &&
          (programs.length === 0 ? (
            empty
          ) : (
            <div className="catalog-programs">
              {programs.map((program) => (
                <section key={program.id} className="catalog-program">
                  <div className="panel-head">
                    <div>
                      <h2>{program.label}</h2>
                      <p>
                        <bdi>{program.code}</bdi> ·{" "}
                        {t("console_courses.options." + program.program_type)}
                      </p>
                      {program.default_rate_amount !== null &&
                        program.default_rate_amount !== undefined && (
                          <p>
                            {t("console_courses.fields.default_rate_amount")} ·{" "}
                            <bdi>
                              {String(program.default_rate_amount)}{" "}
                              {String(program.currency)}
                            </bdi>
                          </p>
                        )}
                    </div>
                    <div className="course-row-actions">
                      <button
                        className="inline-link"
                        onClick={() => open("programs", program)}
                      >
                        {t("console_courses.edit_program")}
                      </button>
                      <button
                        className="inline-link"
                        onClick={() =>
                          open("levels", undefined, { program_id: program.id })
                        }
                      >
                        <span aria-hidden="true">+</span>
                        {t("console_courses.create_level")}
                      </button>
                    </div>
                  </div>
                  <div className="catalog-levels">
                    {props.levels
                      .filter((level) => level.program_id === program.id)
                      .map((level) => (
                        <div key={level.id} className="activity-line">
                          <div>
                            <h3 className="cell-title">{level.label}</h3>
                            <span className="cell-sub">
                              {props.courses
                                .filter(
                                  (course) => course.level_id === level.id,
                                )
                                .map((course) => course.label)
                                .join(" · ") || t("console_courses.no_courses")}
                            </span>
                          </div>
                          <div className="course-row-actions">
                            <button
                              className="inline-link"
                              onClick={() => open("levels", level)}
                            >
                              {t("console_courses.edit_level")}
                            </button>
                            {props.abilities.courses && (
                              <button
                                className="inline-link"
                                onClick={() =>
                                  open("items", undefined, {
                                    level_id: level.id,
                                  })
                                }
                              >
                                {t("console_courses.create_course")}
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    {props.levels.every(
                      (level) => level.program_id !== program.id,
                    ) && (
                      <p className="cell-sub">
                        {t("console_courses.no_levels")}
                      </p>
                    )}
                  </div>
                </section>
              ))}
            </div>
          ))}
        <footer className="panel-foot">
          <span>
            {t("console_courses.result_count")
              .replace(":count", String(count))
              .replace(":total", String(total))}
          </span>
          <span>{t("console_courses.list_help." + tab)}</span>
        </footer>
      </section>
    </ConsoleLayout>
  );
}
