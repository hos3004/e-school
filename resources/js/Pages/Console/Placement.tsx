import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import type {
  RegistrationApplication,
  RegistrationCourse,
  RegistrationPage,
} from "./RegistrationTypes";

interface Schedule {
  id: string;
  teacher_name: string | null;
  duration_minutes: number;
  interval_weeks: number;
  timezone: string;
  starts_on: string;
  ends_on: string | null;
  weekly_slots: { weekday: number; start_time: string }[];
}
interface Group {
  id: string;
  name: string;
  code: string;
  status: string;
  timezone: string;
  starts_on: string | null;
  ends_on: string | null;
  capacity: number | null;
  occupied_seats: number | null;
  remaining_seats: number | null;
  can_select: boolean;
  teachers: string[];
  schedules: Schedule[];
}
interface Row extends RegistrationApplication {
  memberships: { group_id: string; group_name: string; status: string }[];
}
interface Props {
  filters: Record<string, string | null>;
  catalog: RegistrationCourse[];
  applications: Omit<RegistrationPage, "data"> & { data: Row[] };
  groups: Group[];
  timezone: string;
  canReview: boolean;
}
interface Check {
  eligible_count: number;
  remaining_seats: number | null;
  capacity_warning: string | null;
  group_is_draft: boolean;
  candidates: {
    id: string;
    name: string;
    eligible: boolean;
    already_member: boolean;
    reason: string | null;
  }[];
}
interface Saved {
  message: string;
  group_id: string;
  group_is_draft: boolean;
  placed_count: number;
  skipped_existing_count: number;
  applications: Row[];
  groups: Group[];
}
export default function Placement({
  filters,
  catalog,
  applications,
  groups: initialGroups,
  timezone,
  canReview,
}: Props) {
  const t = useI18n();
  const p = (key: string) => t("console_registration.placement." + key);
  const [rows, setRows] = useState(applications.data);
  const [groups, setGroups] = useState(initialGroups);
  const initialAssignments = Object.fromEntries(
    applications.data.map((row) => [
      row.id,
      row.memberships.find((m) =>
        initialGroups.some((g) => g.id === m.group_id),
      )?.group_id ?? "",
    ]),
  );
  const [assignments, setAssignments] =
    useState<Record<string, string>>(initialAssignments);
  const [selected, setSelected] = useState<string[]>([]);
  const [saved, setSaved] = useState<string[]>([]);
  const [busy, setBusy] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState("");
  const [target, setTarget] = useState("");
  const [newName, setNewName] = useState("");
  const [check, setCheck] = useState<Check | null>(null);
  const [draftFilters, setDraftFilters] = useState({ ...filters });
  const courseId = filters.course ?? "";
  const dirty = rows.some(
    (row) =>
      !!assignments[row.id] &&
      !row.memberships.some((m) => m.group_id === assignments[row.id]),
  );
  const currentCourse = catalog.find((c) => c.id === courseId);
  const visit = (url: string, data?: Record<string, string | null>) => {
    if (!dirty || window.confirm(p("discard")))
      router.get(url, data ?? {}, {
        preserveState: false,
        preserveScroll: false,
      });
  };
  const errorText = (error: unknown) =>
    axios.isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(
      error,
    )
      ? Object.values(error.response?.data.errors ?? {})
          .flat()
          .join(" · ") ||
        (error.response?.status === 403
          ? p("forbidden")
          : error.response?.status === 404
            ? p("not_found")
            : p("save_failed"))
      : p("save_failed");
  const payload = (ids: string[], group: string) => ({
    application_ids: ids,
    course_id: courseId,
    group_id: group === "new" ? null : group,
    new_group_name: group === "new" ? newName : null,
  });
  async function save(ids: string[], group: string, key: string) {
    if (!courseId || !group || !ids.length || busy) return;
    setBusy(key);
    setErrors((prev) => ({ ...prev, [key]: "" }));
    setNotice("");
    try {
      const { data } = await axios.post<Saved>(
        "/manage/placement",
        payload(ids, group),
      );
      setRows((old) =>
        old.map(
          (row) =>
            data.applications.find((updated) => updated.id === row.id) ?? row,
        ),
      );
      setGroups((old) => [
        ...old.filter((g) => !data.groups.some((next) => next.id === g.id)),
        ...data.groups,
      ]);
      setAssignments((old) => ({
        ...old,
        ...Object.fromEntries(ids.map((id) => [id, data.group_id])),
      }));
      setSaved((old) => Array.from(new Set([...old, ...ids])));
      setSelected((old) => old.filter((id) => !ids.includes(id)));
      setNotice(
        data.message + (data.group_is_draft ? " · " + p("draft_saved") : ""),
      );
      setCheck(null);
      if (group === "new") {
        setTarget(data.group_id);
        setNewName("");
      }
    } catch (error) {
      setErrors((old) => ({ ...old, [key]: errorText(error) }));
    } finally {
      setBusy(null);
    }
  }
  async function preview() {
    if (!courseId || !target || !selected.length || busy) return;
    setBusy("preview");
    setErrors((old) => ({ ...old, batch: "" }));
    setCheck(null);
    try {
      const { data } = await axios.post<Check>(
        "/manage/placement/preflight",
        payload(selected, target),
      );
      setCheck(data);
    } catch (error) {
      setErrors((old) => ({ ...old, batch: errorText(error) }));
    } finally {
      setBusy(null);
    }
  }
  function toggle(id: string) {
    setSelected((old) =>
      old.includes(id) ? old.filter((item) => item !== id) : [...old, id],
    );
    setCheck(null);
  }
  function submitFilters(event: FormEvent) {
    event.preventDefault();
    visit("/manage/placement", {
      ...draftFilters,
      application: null,
      page: null,
    });
  }
  return (
    <ConsoleLayout
      title={p("title")}
      description={p("description")}
      section="before"
      actions={
        <>
          {canReview && (
            <Link
              className="console-button"
              href={
                "/manage/registration?stage=accepted" +
                (courseId ? "&course=" + courseId : "")
              }
            >
              {p("admissions")}
            </Link>
          )}
          <Link className="console-button" href="/manage/groups">
            {p("groups")}
          </Link>
        </>
      }
    >
      <Head title={p("title")} />
      {notice && (
        <div className="feedback" role="status">
          {notice}
        </div>
      )}
      <form
        className="console-toolbar console-filter-bar console-filter-mobile-pairs"
        onSubmit={submitFilters}
      >
        <label className="field console-filter-mobile-full">
          <span>{t("console_registration.fields.course")}</span>
          <select
            className="console-control"
            value={draftFilters.course ?? ""}
            onChange={(e) =>
              setDraftFilters({ ...draftFilters, course: e.target.value })
            }
          >
            <option value="">{p("choose_course")}</option>
            {catalog.map((course) => (
              <option key={course.id} value={course.id}>
                {course.name} · {course.program_name}
              </option>
            ))}
          </select>
        </label>
        <label className="field console-filter-search">
          <span>{t("console_registration.search")}</span>
          <input
            className="console-control"
            value={draftFilters.search ?? ""}
            onChange={(e) =>
              setDraftFilters({ ...draftFilters, search: e.target.value })
            }
          />
        </label>
        <label className="field">
          <span>{p("status")}</span>
          <select
            className="console-control"
            value={draftFilters.status ?? "waiting"}
            onChange={(e) =>
              setDraftFilters({ ...draftFilters, status: e.target.value })
            }
          >
            {["waiting", "assigned", "all"].map((status) => (
              <option key={status} value={status}>
                {p(status)}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>{p("scope")}</span>
          <select
            className="console-control"
            value={draftFilters.scope ?? "course"}
            onChange={(e) =>
              setDraftFilters({ ...draftFilters, scope: e.target.value })
            }
          >
            <option value="course">{p("course_scope")}</option>
            <option value="all">{p("all_scope")}</option>
          </select>
        </label>
        <button
          className="console-button primary"
          type="submit"
          disabled={!!busy}
        >
          {t("console_registration.apply_filters")}
        </button>
      </form>
      {filters.application && (
        <div className="catalog-tip">
          <span>{p("focused")}</span>
          <button
            type="button"
            className="inline-link"
            onClick={() =>
              visit("/manage/placement", { course: courseId, status: "all" })
            }
          >
            {p("show_all")}
          </button>
        </div>
      )}
      {!courseId && (
        <div className="catalog-tip">{p("choose_course_help")}</div>
      )}
      <section className="panel">
        <div className="toolbar">
          <div className="filters">
            {currentCourse && (
              <span className="pill current">{currentCourse.name}</span>
            )}
            <span className="pill">
              {p("students")} · {String(applications.total)}
            </span>
          </div>
          <span className="toolbar-info">
            {p("school_timezone")} <bdi>{timezone}</bdi>
          </span>
        </div>
        <div className="batchbar">
          <label className="console-checkbox">
            <input
              type="checkbox"
              checked={rows.length > 0 && selected.length === rows.length}
              onChange={(e) => {
                setSelected(e.target.checked ? rows.map((r) => r.id) : []);
                setCheck(null);
              }}
              disabled={!!busy}
            />
            <span>
              {selected.length
                ? String(selected.length) + " " + p("selected")
                : p("select_rows")}
            </span>
          </label>
          <div className="actions">
            <label className="field">
              <span>{p("batch_group")}</span>
              <select
                className="console-control"
                value={target}
                disabled={!courseId || !!busy}
                onChange={(e) => {
                  setTarget(e.target.value);
                  setCheck(null);
                }}
              >
                <option value="">{p("choose_group")}</option>
                {groups
                  .filter((g) => g.can_select)
                  .map((group) => (
                    <option key={group.id} value={group.id}>
                      {group.name}
                    </option>
                  ))}
                <option value="new">{p("create_draft")}</option>
              </select>
            </label>
            {target === "new" && (
              <label className="field">
                <span>{p("new_group_name")}</span>
                <input
                  className="console-control"
                  value={newName}
                  maxLength={255}
                  onChange={(e) => {
                    setNewName(e.target.value);
                    setCheck(null);
                  }}
                  disabled={!!busy}
                />
              </label>
            )}
            <button
              type="button"
              className="console-button"
              onClick={() => void preview()}
              disabled={
                !selected.length ||
                !target ||
                !courseId ||
                !!busy ||
                (target === "new" && !newName.trim())
              }
            >
              {busy === "preview" ? p("checking") : p("check")}
            </button>
            <button
              type="button"
              className="console-button primary"
              onClick={() => void save(selected, target, "batch")}
              disabled={
                !selected.length ||
                !target ||
                !courseId ||
                !!busy ||
                (target === "new" && !newName.trim())
              }
            >
              {busy === "batch" ? p("saving") : p("save_selected")}
            </button>
          </div>
        </div>
        {errors.batch && (
          <div className="feedback" role="alert">
            {errors.batch}
          </div>
        )}
        {check && (
          <div className="catalog-tip">
            <div>
              <strong>
                {p("eligible")} {String(check.eligible_count)}
              </strong>
              <p>{check.capacity_warning ?? p("final_check")}</p>
              {check.group_is_draft && <p>{p("draft_help")}</p>}
              {check.candidates
                .filter((c) => !c.eligible)
                .map((c) => (
                  <p key={c.id}>
                    {c.name} · {c.reason}
                  </p>
                ))}
            </div>
          </div>
        )}
        <div className="scroll-note">{p("scroll")}</div>
        <div
          className="console-table-scroll"
          tabIndex={0}
          role="region"
          aria-label={p("table")}
        >
          <table className="console-table table placement-table">
            <thead>
              <tr>
                {[
                  "student",
                  "group_teacher",
                  "days_time",
                  "duration_interval",
                  "dates",
                  "status_action",
                ].map((key) => (
                  <th key={key}>{p(key)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const group = groups.find((g) => g.id === assignments[row.id]);
                const membership = row.memberships.find(
                  (m) => m.group_id === group?.id,
                );
                const savedHere = !!membership && row.status === "assigned";
                const rowError = errors[row.id];
                const pending = membership?.status === "pending";
                return (
                  <tr key={row.id}>
                    <td>
                      <div className="student-cell">
                        <input
                          type="checkbox"
                          aria-label={p("select") + " " + row.full_name}
                          checked={selected.includes(row.id)}
                          disabled={!!busy}
                          onChange={() => toggle(row.id)}
                        />
                        <div>
                          <Link
                            className="cell-title"
                            href={"/manage/students/" + row.student_profile_id}
                          >
                            {row.full_name}
                          </Link>
                          <span className="cell-sub">{row.student_code}</span>
                          <span className="cell-sub">{row.source}</span>
                          {row.memberships.map((m) => (
                            <span key={m.group_id} className="cell-sub">
                              {p("current_group")} {m.group_name}
                            </span>
                          ))}
                        </div>
                      </div>
                    </td>
                    <td>
                      <div className="schedule-cell">
                        <label className="field">
                          <span className="sr-only">
                            {p("group") + " " + row.full_name}
                          </span>
                          <select
                            className="console-control"
                            value={assignments[row.id] ?? ""}
                            disabled={!courseId || !!busy}
                            onChange={(e) => {
                              setAssignments((old) => ({
                                ...old,
                                [row.id]: e.target.value,
                              }));
                              setSaved((old) =>
                                old.filter((id) => id !== row.id),
                              );
                            }}
                          >
                            <option value="">{p("choose_group")}</option>
                            {groups
                              .filter(
                                (g) =>
                                  g.can_select ||
                                  g.id === assignments[row.id] ||
                                  row.memberships.some(
                                    (m) => m.group_id === g.id,
                                  ),
                              )
                              .map((g) => (
                                <option
                                  key={g.id}
                                  value={g.id}
                                  disabled={
                                    !g.can_select &&
                                    !row.memberships.some(
                                      (m) => m.group_id === g.id,
                                    )
                                  }
                                >
                                  {g.name}
                                </option>
                              ))}
                          </select>
                        </label>
                        <small>
                          {group?.teachers.length
                            ? group.teachers.join("، ")
                            : p("teacher_pending")}
                        </small>
                        {group && (
                          <small>
                            {group.capacity === null
                              ? p("capacity_pending")
                              : p("remaining") +
                                " " +
                                String(group.remaining_seats ?? 0) +
                                " / " +
                                String(group.capacity)}
                          </small>
                        )}
                        {group && (
                          <Link
                            className="inline-link"
                            href={"/manage/groups?group=" + group.id}
                          >
                            {p("open_group")}
                          </Link>
                        )}
                      </div>
                    </td>
                    <td>
                      {group?.schedules.length ? (
                        group.schedules.map((schedule) => (
                          <div className="schedule-cell" key={schedule.id}>
                            {schedule.weekly_slots.map((slot, index) => (
                              <span key={index} className="cell-title">
                                {p("weekdays." + slot.weekday)} ·{" "}
                                <bdi>{slot.start_time}</bdi>
                              </span>
                            ))}
                            <span className="cell-sub">
                              <bdi>{schedule.timezone}</bdi>
                            </span>
                            {schedule.teacher_name && (
                              <span className="cell-sub">
                                {schedule.teacher_name}
                              </span>
                            )}
                          </div>
                        ))
                      ) : (
                        <span className="cell-sub">
                          {p("schedule_pending")}
                        </span>
                      )}
                    </td>
                    <td>
                      {group?.schedules.length ? (
                        group.schedules.map((schedule) => (
                          <div className="schedule-cell" key={schedule.id}>
                            <span className="cell-title">
                              {String(schedule.duration_minutes)} {p("minutes")}
                            </span>
                            <span className="cell-sub">
                              {schedule.interval_weeks === 1
                                ? p("weekly")
                                : p("every") +
                                  " " +
                                  String(schedule.interval_weeks) +
                                  " " +
                                  p("weeks")}
                            </span>
                          </div>
                        ))
                      ) : (
                        <span className="cell-sub">{p("not_set")}</span>
                      )}
                    </td>
                    <td>
                      {group?.schedules.length ? (
                        group.schedules.map((schedule) => (
                          <div className="schedule-cell" key={schedule.id}>
                            <span className="cell-title">
                              <bdi>{schedule.starts_on}</bdi>
                            </span>
                            <span className="cell-sub">
                              {schedule.ends_on ? (
                                <bdi>{schedule.ends_on}</bdi>
                              ) : (
                                p("open_end")
                              )}
                            </span>
                          </div>
                        ))
                      ) : (
                        <>
                          <span className="cell-title">
                            <bdi>{group?.starts_on ?? p("not_set")}</bdi>
                          </span>
                          <span className="cell-sub">
                            <bdi>{group?.ends_on ?? p("open_end")}</bdi>
                          </span>
                        </>
                      )}
                    </td>
                    <td>
                      <div className="schedule-cell">
                        <span
                          className={
                            "status " + (savedHere ? "green" : "slate")
                          }
                        >
                          {savedHere
                            ? pending
                              ? p("pending_activation")
                              : p("assigned")
                            : assignments[row.id]
                              ? p("unsaved")
                              : p("waiting")}
                        </span>
                        {saved.includes(row.id) && (
                          <small role="status">{p("saved")}</small>
                        )}
                        {savedHere ? (
                          <span className="cell-sub">{p("already_saved")}</span>
                        ) : (
                          <button
                            type="button"
                            className="inline-link"
                            onClick={() =>
                              void save(
                                [row.id],
                                assignments[row.id] ?? "",
                                row.id,
                              )
                            }
                            disabled={
                              !!busy ||
                              !courseId ||
                              !group ||
                              (!group.can_select && !membership)
                            }
                          >
                            {busy === row.id ? p("saving") : p("save_row")}
                          </button>
                        )}
                        {row.memberships.length > 0 &&
                          !savedHere &&
                          assignments[row.id] && (
                            <small>{p("additional_group")}</small>
                          )}
                        {rowError && (
                          <span role="alert" className="console-error">
                            {rowError}
                          </span>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6}>
                    <div className="empty-state">{p("empty")}</div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="panel-foot">
          <span>
            {p("page")} {String(applications.current_page)} /{" "}
            {String(applications.last_page)}
          </span>
          <div className="actions">
            {applications.prev_page_url && (
              <button
                className="inline-link"
                onClick={() => visit(applications.prev_page_url!)}
              >
                {p("previous")}
              </button>
            )}
            {applications.next_page_url && (
              <button
                className="inline-link"
                onClick={() => visit(applications.next_page_url!)}
              >
                {p("next")}
              </button>
            )}
          </div>
          <span>{p("keep_visible")}</span>
        </div>
      </section>
      <div className="catalog-tip">{p("final_check")}</div>
    </ConsoleLayout>
  );
}
