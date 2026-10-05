import { useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useI18n } from "@/lib/i18n";

export type PlacementMembership = {
  id: string;
  group: string;
  status: string;
};

export type PlacementData = {
  optionsUrl: string;
  addUrl: string;
  transferUrl: string;
  memberships: PlacementMembership[];
};

type CourseOption = { value: string; label: string };
type GroupOption = {
  value: string;
  label: string;
  seats: number | null;
  draft: boolean;
  teachers: string;
};
type Mode = "add" | "transfer";

export default function PlacementActions({
  placement,
}: {
  placement: PlacementData;
}) {
  const t = useI18n();
  const [mode, setMode] = useState<Mode | null>(null);
  const [courses, setCourses] = useState<CourseOption[]>([]);
  const [groups, setGroups] = useState<GroupOption[]>([]);
  const [loading, setLoading] = useState(false);
  const form = useForm({
    course_id: "",
    group_id: "",
    membership_id: placement.memberships[0]?.id ?? "",
    reason: "",
  });

  useEffect(() => {
    if (mode === null || courses.length > 0) {
      return;
    }
    const controller = new AbortController();
    setLoading(true);
    fetch(placement.optionsUrl, {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    })
      .then((response) => (response.ok ? response.json() : null))
      .then((data) => setCourses(data?.courses ?? []))
      .catch(() => undefined)
      .finally(() => setLoading(false));

    return () => controller.abort();
  }, [mode, courses.length, placement.optionsUrl]);

  const selectCourse = (courseId: string) => {
    form.setData((current) => ({
      ...current,
      course_id: courseId,
      group_id: "",
    }));
    setGroups([]);
    if (courseId === "") {
      return;
    }
    setLoading(true);
    fetch(
      placement.optionsUrl + "?" + new URLSearchParams({ course_id: courseId }),
      { headers: { Accept: "application/json" } },
    )
      .then((response) => (response.ok ? response.json() : null))
      .then((data) => setGroups(data?.groups ?? []))
      .catch(() => undefined)
      .finally(() => setLoading(false));
  };

  const close = () => {
    form.clearErrors();
    form.reset("course_id", "group_id", "reason");
    setGroups([]);
    setMode(null);
  };

  const submit = () => {
    form.post(mode === "transfer" ? placement.transferUrl : placement.addUrl, {
      preserveScroll: true,
      onSuccess: close,
    });
  };

  const canTransfer = placement.memberships.length > 0;
  const ready =
    form.data.course_id !== "" &&
    form.data.group_id !== "" &&
    form.data.reason.trim().length >= 3 &&
    (mode !== "transfer" || form.data.membership_id !== "");

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">{t("console_people.placement.title")}</h2>
      <p className="text-sm my-2">{t("console_people.placement.hint")}</p>

      <div className="flex flex-wrap gap-2 my-4">
        <button
          type="button"
          aria-expanded={mode === "add"}
          className="rounded-lg border border-[var(--line)] px-4 py-2"
          onClick={() => {
            close();
            setMode("add");
          }}
        >
          {t("console_people.placement.add")}
        </button>
        {canTransfer && (
          <button
            type="button"
            aria-expanded={mode === "transfer"}
            className="rounded-lg border border-[var(--line)] px-4 py-2"
            onClick={() => {
              close();
              setMode("transfer");
            }}
          >
            {t("console_people.placement.transfer")}
          </button>
        )}
      </div>

      {mode !== null && (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          <p className="text-sm my-2">
            {t("console_people.placement.confirm_" + mode)}
          </p>

          {mode === "transfer" && (
            <label className="block my-3">
              <span className="block mb-2">
                {t("console_people.placement.from_group")}
              </span>
              <select
                className="w-full rounded-lg border border-[var(--line)] p-3"
                value={form.data.membership_id}
                onChange={(event) =>
                  form.setData("membership_id", event.target.value)
                }
                required
              >
                {placement.memberships.map((membership) => (
                  <option key={membership.id} value={membership.id}>
                    {membership.group} ({membership.status})
                  </option>
                ))}
              </select>
            </label>
          )}

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_people.placement.course")}
            </span>
            <select
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.course_id}
              onChange={(event) => selectCourse(event.target.value)}
              required
            >
              <option value="">
                {loading
                  ? t("console_people.placement.loading")
                  : t("console_people.placement.choose")}
              </option>
              {courses.map((course) => (
                <option key={course.value} value={course.value}>
                  {course.label}
                </option>
              ))}
            </select>
          </label>

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_people.placement.group")}
            </span>
            <select
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.group_id}
              onChange={(event) => form.setData("group_id", event.target.value)}
              required
              disabled={form.data.course_id === ""}
            >
              <option value="">
                {loading
                  ? t("console_people.placement.loading")
                  : t("console_people.placement.choose")}
              </option>
              {groups.map((group) => (
                <option key={group.value} value={group.value}>
                  {group.label}
                  {group.teachers === "" ? "" : " — " + group.teachers}
                  {group.draft
                    ? " — " + t("console_people.placement.draft")
                    : group.seats === null
                      ? ""
                      : " — " +
                        t("console_people.placement.seats") +
                        ": " +
                        group.seats}
                </option>
              ))}
            </select>
          </label>

          <label className="block my-3">
            <span className="block mb-2">
              {t("console_people.placement.reason")}
            </span>
            <textarea
              className="w-full rounded-lg border border-[var(--line)] p-3"
              value={form.data.reason}
              onChange={(event) => form.setData("reason", event.target.value)}
              required
              minLength={3}
              maxLength={1000}
              rows={3}
            />
          </label>

          {Object.values(form.errors).map((error, index) => (
            <p key={index} role="alert" className="text-red-700">
              {error}
            </p>
          ))}

          <div className="flex gap-2">
            <button
              className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50"
              disabled={form.processing || !ready}
            >
              {t("console_people.placement.submit_" + mode)}
            </button>
            <button
              type="button"
              className="rounded-lg border border-[var(--line)] px-4 py-2"
              onClick={close}
            >
              {t("console_people.placement.cancel")}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
