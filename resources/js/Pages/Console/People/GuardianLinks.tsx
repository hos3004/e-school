import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type GuardianStudentLink = {
  id: string;
  student_profile_id: string;
  name: string;
  code: string | null;
  relationship: string;
  is_primary: boolean;
  can_act_for: boolean;
  verified_at: string | null;
  programs: string[];
  archived: boolean;
  show_url: string;
  unlink_url: string;
};

export type GuardianRole = {
  kind: string;
  label: string;
  code: string | null;
  show_url: string;
};

export type GuardianLinksData = {
  canLink: boolean;
  linkUrl: string | null;
  studentSearchUrl: string | null;
  relationshipOptions: { value: string; label: string }[];
};

type StudentOption = { value: string; label: string };

/**
 * الأبناء المرتبطون بحساب ولي الأمر، وأي صفة أخرى يحملها الحساب نفسه.
 *
 * حساب واحد قد يُربط بأكثر من طالب — بنفس البرنامج أو ببرامج مختلفة — وقد
 * يكون هو نفسه طالبًا أو معلمًا في آن؛ هذه الشاشة تجمع الاثنين معًا.
 */
export default function GuardianLinks({
  students,
  roles,
  links,
}: {
  students: GuardianStudentLink[];
  roles: GuardianRole[];
  links: GuardianLinksData;
}) {
  const t = useI18n();
  const [linking, setLinking] = useState(false);
  const [unlinkTarget, setUnlinkTarget] = useState<GuardianStudentLink | null>(
    null,
  );
  const [search, setSearch] = useState("");
  const [options, setOptions] = useState<StudentOption[]>([]);
  const linkForm = useForm({
    student_profile_id: "",
    relationship: "",
    is_primary: false as boolean,
    can_act_for: false as boolean,
    reason: "",
  });
  const unlinkForm = useForm({ reason: "" });

  async function findStudents() {
    if (!links.studentSearchUrl) return;
    const response = await fetch(
      links.studentSearchUrl + "?" + new URLSearchParams({ search }),
      { headers: { Accept: "application/json" } },
    );
    if (!response.ok) return;
    const result = (await response.json()) as { students?: StudentOption[] };
    setOptions(result.students ?? []);
  }

  function submitLink(event: React.FormEvent) {
    event.preventDefault();
    if (!links.linkUrl) return;
    linkForm.post(links.linkUrl, {
      preserveScroll: true,
      onSuccess: () => {
        linkForm.reset();
        setOptions([]);
        setSearch("");
        setLinking(false);
      },
    });
  }

  function submitUnlink(event: React.FormEvent) {
    event.preventDefault();
    if (!unlinkTarget) return;
    unlinkForm.delete(unlinkTarget.unlink_url, {
      preserveScroll: true,
      onSuccess: () => {
        unlinkForm.reset();
        setUnlinkTarget(null);
      },
    });
  }

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-bold text-lg">
            {t("console_people.guardians.title")}
          </h2>
          <p className="text-sm text-slate-500">
            {t("console_people.guardians.hint")}
          </p>
        </div>
        {links.canLink && links.linkUrl && (
          <button
            type="button"
            className="console-button"
            onClick={() => setLinking((value) => !value)}
          >
            {t("console_people.guardians.link")}
          </button>
        )}
      </div>

      {students.length === 0 ? (
        <p className="mt-4 text-sm text-slate-500">
          {t("console_people.guardians.none")}
        </p>
      ) : (
        <ul className="mt-4 space-y-3">
          {students.map((student) => (
            <li
              key={student.id}
              className="flex items-center justify-between gap-3 rounded-lg border border-[var(--line)] p-3"
            >
              <div>
                <a href={student.show_url} className="font-medium underline">
                  {student.name}
                </a>
                {student.code && (
                  <span className="ms-2 text-xs text-slate-400">
                    {student.code}
                  </span>
                )}
                <p className="text-sm text-slate-500">
                  {student.relationship}
                  {student.is_primary
                    ? " · " + t("console_people.guardians.primary")
                    : ""}
                  {student.programs.length > 0
                    ? " · " + student.programs.join(" · ")
                    : ""}
                </p>
              </div>
              {links.canLink && (
                <button
                  type="button"
                  className="console-button"
                  onClick={() => setUnlinkTarget(student)}
                >
                  {t("console_people.guardians.unlink")}
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {roles.length > 0 && (
        <div className="mt-6 border-t border-[var(--line)] pt-4">
          <h3 className="font-semibold">
            {t("console_people.guardians.other_roles")}
          </h3>
          <p className="text-sm text-slate-500">
            {t("console_people.guardians.other_roles_hint")}
          </p>
          <ul className="mt-2 space-y-1">
            {roles.map((role) => (
              <li key={role.kind}>
                <a href={role.show_url} className="underline">
                  {role.label}
                  {role.code ? " · " + role.code : ""}
                </a>
              </li>
            ))}
          </ul>
        </div>
      )}

      {linking && links.linkUrl && (
        <form
          onSubmit={submitLink}
          className="mt-4 space-y-3 rounded-lg bg-slate-50 p-4"
        >
          <p className="text-sm leading-7 text-slate-600">
            {t("console_people.guardians.link_hint")}
          </p>
          <div className="flex gap-2">
            <input
              className="console-control"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <button
              type="button"
              className="console-button"
              onClick={() => void findStudents()}
            >
              {t("console_people.search")}
            </button>
          </div>
          <select
            className="console-control"
            value={linkForm.data.student_profile_id}
            onChange={(event) =>
              linkForm.setData("student_profile_id", event.target.value)
            }
          >
            <option value="">{t("console_people.choose")}</option>
            {options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <select
            className="console-control"
            value={linkForm.data.relationship}
            onChange={(event) =>
              linkForm.setData("relationship", event.target.value)
            }
          >
            <option value="">{t("console_people.choose")}</option>
            {links.relationshipOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={linkForm.data.is_primary}
              onChange={(event) =>
                linkForm.setData("is_primary", event.target.checked)
              }
            />
            {t("console_people.fields.is_primary")}
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={linkForm.data.can_act_for}
              onChange={(event) =>
                linkForm.setData("can_act_for", event.target.checked)
              }
            />
            {t("console_people.fields.can_act_for")}
          </label>
          <label className="block text-sm">
            {t("console_people.guardians.reason")}
            <input
              className="console-control"
              value={linkForm.data.reason}
              onChange={(event) =>
                linkForm.setData("reason", event.target.value)
              }
            />
          </label>
          {linkForm.errors.student_profile_id && (
            <p role="alert" className="text-sm text-red-700">
              {linkForm.errors.student_profile_id}
            </p>
          )}
          <div className="flex gap-2">
            <button
              type="submit"
              className="console-button primary"
              disabled={linkForm.processing}
            >
              {t("console_people.guardians.link")}
            </button>
            <button
              type="button"
              className="console-button"
              onClick={() => setLinking(false)}
            >
              {t("console_people.guardians.cancel")}
            </button>
          </div>
        </form>
      )}

      {unlinkTarget && (
        <form
          onSubmit={submitUnlink}
          className="mt-4 space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4"
        >
          <p className="text-sm text-amber-900">
            {t("console_people.guardians.confirm_unlink")}
          </p>
          <label className="block text-sm">
            {t("console_people.guardians.reason")}
            <input
              className="console-control"
              value={unlinkForm.data.reason}
              onChange={(event) =>
                unlinkForm.setData("reason", event.target.value)
              }
            />
          </label>
          <div className="flex gap-2">
            <button
              type="submit"
              className="console-button primary"
              disabled={unlinkForm.processing}
            >
              {t("console_people.guardians.submit_unlink")}
            </button>
            <button
              type="button"
              className="console-button"
              onClick={() => setUnlinkTarget(null)}
            >
              {t("console_people.guardians.cancel")}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
