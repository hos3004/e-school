import { useForm } from "@inertiajs/react";
import { type FormEvent, useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

type Kind = "program" | "course" | "group";
type SectionKey = "programs" | "courses" | "groups";

type Summary = Record<string, string | number | null>;

export type ArchivedRow = {
  id: string;
  kind: Kind;
  code: string;
  name: string;
  closedAt: string | null;
  closedBy: string;
  reason: string | null;
  summary: Summary;
};

export type CandidateRow = {
  id: string;
  kind: Kind;
  code: string;
  name: string;
};

export type Preview = {
  kind: Kind;
  summary: Summary;
  blockers: Record<string, number>;
  closable: boolean;
};

export interface Props {
  sections: Record<SectionKey, ArchivedRow[]>;
  candidates: Record<SectionKey, CandidateRow[]>;
  timezone: string;
  abilities: Record<SectionKey, boolean>;
}

const SECTIONS: SectionKey[] = ["programs", "courses", "groups"];

/**
 * ترتيب حقول الحصيلة معروض لا أبجدي: الأعداد التي يسأل عنها المستخدم أولًا،
 * ثم التواريخ. أي مفتاح جديد يظهر بعدها بترتيب الخادم.
 */
const SUMMARY_ORDER = [
  "levels_total",
  "courses_total",
  "courses_open",
  "courses_closed",
  "members_total",
  "teachers_total",
  "programs_total",
  "planned_sessions",
  "sessions_total",
  "sessions_completed",
  "sessions_cancelled",
  "sessions_stale",
  "sessions_other",
  "enrollments_total",
  "enrollments_completed",
  "enrollments_withdrawn",
  "students_distinct",
  "teachers_distinct",
  "capacity",
  "status",
  "starts_on",
  "ends_on",
  "first_session_at",
  "last_session_at",
];

/** الحقول التي لا تُعرض كأرقام: وصف اللقطة نفسها لا محتواها. */
const HIDDEN_SUMMARY_KEYS = new Set(["kind", "captured_at", "level_id", "program_id"]);

function orderedSummary(summary: Summary): [string, string | number | null][] {
  const keys = Object.keys(summary).filter((key) => !HIDDEN_SUMMARY_KEYS.has(key));
  keys.sort((a, b) => {
    const left = SUMMARY_ORDER.indexOf(a);
    const right = SUMMARY_ORDER.indexOf(b);
    return (left === -1 ? 999 : left) - (right === -1 ? 999 : right);
  });
  return keys.map((key) => [key, summary[key] ?? null]);
}

export default function Archive({ sections, candidates, timezone, abilities }: Props) {
  const t = useI18n();
  const [preview, setPreview] = useState<{ row: CandidateRow; data: Preview | null } | null>(null);
  const [reopening, setReopening] = useState<ArchivedRow | null>(null);

  const label = (key: string) => t("console_archive." + key);

  const value = (raw: string | number | null): string => {
    if (raw === null || raw === "") return "—";
    if (typeof raw === "string" && /^\d{4}-\d{2}-\d{2}/.test(raw)) {
      return formatDate(raw, timezone);
    }
    return String(raw);
  };

  async function openPreview(row: CandidateRow) {
    setPreview({ row, data: null });
    const response = await fetch(`/manage/archive/${row.kind}/${row.id}/preview`, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    });
    if (!response.ok) {
      setPreview(null);
      return;
    }
    setPreview({ row, data: (await response.json()) as Preview });
  }

  return (
    <ConsoleLayout
      title={label("title")}
      description={label("description")}
      section="records"
    >
      {SECTIONS.map((key) => (
        <section key={key} className="console-panel mb-6">
          <div className="console-panel-body">
            <h2 className="mb-1">{label("sections." + key)}</h2>

            <h3 className="mt-4 mb-2">{label("sections.archived")}</h3>
            {sections[key].length === 0 ? (
              <p className="text-sm opacity-70">{label("empty.archived")}</p>
            ) : (
              <ul className="space-y-4">
                {sections[key].map((row) => (
                  <li key={row.id} className="rounded-lg border p-4">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <strong>
                        {row.name} <span className="opacity-60">({row.code})</span>
                      </strong>
                      {abilities[key] && (
                        <button
                          type="button"
                          className="underline"
                          onClick={() => setReopening(row)}
                        >
                          {label("actions.reopen")}
                        </button>
                      )}
                    </div>

                    <p className="mt-1 text-sm opacity-80">
                      {label("labels.closed_at")}:{" "}
                      {row.closedAt ? formatDate(row.closedAt, timezone) : "—"} ·{" "}
                      {label("labels.closed_by")}: {row.closedBy}
                    </p>
                    {row.reason && (
                      <p className="mt-1 text-sm">
                        {label("labels.reason")}: {row.reason}
                      </p>
                    )}

                    <dl className="console-summary-grid mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {orderedSummary(row.summary).map(([field, raw]) => (
                        <div key={field}>
                          <dt className="text-xs opacity-70">
                            {label("summary." + field)}
                          </dt>
                          <dd className="font-medium">{value(raw)}</dd>
                        </div>
                      ))}
                    </dl>
                  </li>
                ))}
              </ul>
            )}

            {abilities[key] && (
              <>
                <h3 className="mt-6 mb-2">{label("sections.candidates")}</h3>
                {candidates[key].length === 0 ? (
                  <p className="text-sm opacity-70">{label("empty.candidates")}</p>
                ) : (
                  <ul className="space-y-2">
                    {candidates[key].map((row) => (
                      <li
                        key={row.id}
                        className="flex flex-wrap items-baseline justify-between gap-2 rounded border p-3"
                      >
                        <span>
                          {row.name} <span className="opacity-60">({row.code})</span>
                        </span>
                        <button
                          type="button"
                          className="underline"
                          onClick={() => void openPreview(row)}
                        >
                          {label("actions.preview")}
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </>
            )}
          </div>
        </section>
      ))}

      {preview && (
        <ClosureDialog
          row={preview.row}
          data={preview.data}
          timezone={timezone}
          onClose={() => setPreview(null)}
        />
      )}

      {reopening && (
        <ReopenDialog row={reopening} onClose={() => setReopening(null)} />
      )}
    </ConsoleLayout>
  );
}

function ClosureDialog({
  row,
  data,
  timezone,
  onClose,
}: {
  row: CandidateRow;
  data: Preview | null;
  timezone: string;
  onClose: () => void;
}) {
  const t = useI18n();
  const label = (key: string) => t("console_archive." + key);
  const form = useForm({ reason: "" });

  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(`/manage/archive/${row.kind}/${row.id}/close`, {
      preserveScroll: true,
      onSuccess: onClose,
    });
  }

  return (
    <div className="console-dialog" role="dialog" aria-modal="true">
      <div className="console-dialog-body rounded-lg border bg-white p-5">
        <h3>{label("dialog.close_title").replace(":name", row.name)}</h3>

        {data === null ? (
          <p className="mt-3">{label("actions.loading")}</p>
        ) : data.closable ? (
          <>
            <p className="mt-2 text-sm opacity-80">{label("dialog.closable")}</p>
            <dl className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
              {orderedSummary(data.summary).map(([field, raw]) => (
                <div key={field}>
                  <dt className="text-xs opacity-70">{label("summary." + field)}</dt>
                  <dd className="font-medium">
                    {raw === null || raw === ""
                      ? "—"
                      : typeof raw === "string" && /^\d{4}-\d{2}-\d{2}/.test(raw)
                        ? formatDate(raw, timezone)
                        : String(raw)}
                  </dd>
                </div>
              ))}
            </dl>

            <form onSubmit={submit} className="mt-4">
              <div className="console-field">
                <label htmlFor="archive-reason">{label("dialog.reason")}</label>
                <textarea
                  id="archive-reason"
                  required
                  minLength={3}
                  maxLength={1000}
                  rows={3}
                  placeholder={label("dialog.reason_placeholder")}
                  value={form.data.reason}
                  onChange={(event) => form.setData("reason", event.target.value)}
                />
                <p className="text-xs opacity-70">{label("dialog.reason_help")}</p>
                {form.errors.reason && (
                  <p className="text-sm text-red-700">{form.errors.reason}</p>
                )}
              </div>
              <div className="mt-3 flex gap-3">
                <button type="submit" disabled={form.processing}>
                  {label("actions.close")}
                </button>
                <button type="button" className="underline" onClick={onClose}>
                  {label("actions.cancel")}
                </button>
              </div>
            </form>
          </>
        ) : (
          <>
            <h4 className="mt-3">{label("blockers.title")}</h4>
            <p className="text-sm opacity-80">{label("blockers.help")}</p>
            <ul className="mt-2 list-disc ps-5">
              {Object.entries(data.blockers).map(([key, count]) => (
                <li key={key}>
                  {label("blockers." + key).replace(":count", String(count))}
                </li>
              ))}
            </ul>
            <button type="button" className="mt-4 underline" onClick={onClose}>
              {label("actions.cancel")}
            </button>
          </>
        )}
      </div>
    </div>
  );
}

function ReopenDialog({ row, onClose }: { row: ArchivedRow; onClose: () => void }) {
  const t = useI18n();
  const label = (key: string) => t("console_archive." + key);
  const form = useForm({ reason: "" });

  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(`/manage/archive/${row.kind}/${row.id}/reopen`, {
      preserveScroll: true,
      onSuccess: onClose,
    });
  }

  return (
    <div className="console-dialog" role="dialog" aria-modal="true">
      <form
        onSubmit={submit}
        className="console-dialog-body rounded-lg border bg-white p-5"
      >
        <h3>{label("dialog.reopen_title").replace(":name", row.name)}</h3>
        <div className="console-field mt-3">
          <label htmlFor="reopen-reason">{label("dialog.reason")}</label>
          <textarea
            id="reopen-reason"
            required
            minLength={3}
            maxLength={1000}
            rows={3}
            placeholder={label("dialog.reason_placeholder")}
            value={form.data.reason}
            onChange={(event) => form.setData("reason", event.target.value)}
          />
          {form.errors.reason && (
            <p className="text-sm text-red-700">{form.errors.reason}</p>
          )}
        </div>
        <div className="mt-3 flex gap-3">
          <button type="submit" disabled={form.processing}>
            {label("actions.reopen")}
          </button>
          <button type="button" className="underline" onClick={onClose}>
            {label("actions.cancel")}
          </button>
        </div>
      </form>
    </div>
  );
}
