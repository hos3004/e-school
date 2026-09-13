import { useState } from "react";
import { useI18n } from "@/lib/i18n";

export type TeacherPortfolioStudent = {
  id: string;
  name: string;
  code: string | null;
  url: string | null;
  slots: string[];
  duration_minutes: number | null;
  awaiting: boolean;
  group: string | null;
};

export type TeacherPortfolioGroup = {
  id: string;
  name: string;
  code: string | null;
  status: string | null;
  role: string | null;
  url: string | null;
  slots: string[];
  duration_minutes: number | null;
  students_count: number;
};

export type TeacherPortfolioCourse = {
  id: string;
  name: string;
  code: string | null;
  session_mode: string | null;
  level: string | null;
  default_duration_minutes: number | null;
  sessions_per_week: number | null;
  total_sessions: number | null;
  students: TeacherPortfolioStudent[];
  groups: TeacherPortfolioGroup[];
  students_count: number;
};

export type TeacherPortfolioProgram = {
  id: string | null;
  name: string;
  code: string | null;
  courses: TeacherPortfolioCourse[];
  courses_count: number;
  students_count: number;
};

export type TeacherPortfolioData = { programs: TeacherPortfolioProgram[] };

const box = "rounded-lg border border-[var(--line)] p-3";
const ghost = "rounded-lg border border-[var(--line)] px-4 py-2";
const pick =
  "w-full rounded-lg border border-[var(--line)] p-3 text-start hover:bg-[var(--brand-soft)]";
const picked = pick + " border-[var(--brand)] bg-[var(--brand-soft)]";

export default function TeacherPrograms({
  teaching,
}: {
  teaching: TeacherPortfolioData;
}) {
  const t = useI18n();
  const [programId, setProgramId] = useState<string | null>(null);
  const [courseId, setCourseId] = useState<string | null>(null);
  const key = (program: TeacherPortfolioProgram) => program.id ?? "";

  const meta = (label: string, value: string | number | null) =>
    value === null || value === "" ? null : (
      <p key={label}>
        <span className="text-sm">{label}: </span>
        <strong>{value}</strong>
      </p>
    );

  const slots = (values: string[]) =>
    values.length === 0
      ? t("console_people.portfolio.no_slots")
      : values.join(" · ");

  return (
    <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
      <h2 className="font-bold text-lg">
        {t("console_people.portfolio.title")}
      </h2>
      <p className="text-sm my-2">{t("console_people.portfolio.hint")}</p>

      {teaching.programs.length === 0 && (
        <p className="my-3">{t("console_people.portfolio.none")}</p>
      )}

      <ul className="my-3 flex flex-col gap-3">
        {teaching.programs.map((item) => (
          <li key={key(item)}>
            <button
              type="button"
              className={key(item) === programId ? picked : pick}
              aria-expanded={key(item) === programId}
              onClick={() => {
                setCourseId(null);
                setProgramId(key(item) === programId ? null : key(item));
              }}
            >
              <strong>{item.name}</strong>
              {item.code && <span className="text-sm"> · {item.code}</span>}
              <span className="block text-sm">
                {t("console_people.portfolio.courses_count")}:{" "}
                {item.courses_count} ·{" "}
                {t("console_people.portfolio.students_count")}:{" "}
                {item.students_count}
              </span>
            </button>

            {key(item) === programId && (
              <ul className="mt-3 ms-4 flex flex-col gap-3">
                {item.courses.map((entry) => (
                  <li key={entry.id}>
                    <button
                      type="button"
                      className={entry.id === courseId ? picked : pick}
                      aria-expanded={entry.id === courseId}
                      onClick={() =>
                        setCourseId(entry.id === courseId ? null : entry.id)
                      }
                    >
                      <strong>{entry.name}</strong>
                      {entry.code && (
                        <span className="text-sm"> · {entry.code}</span>
                      )}
                      <span className="block text-sm">
                        {entry.session_mode && entry.session_mode + " · "}
                        {t("console_people.portfolio.students_count")}:{" "}
                        {entry.students_count}
                      </span>
                    </button>

                    {entry.id === courseId && (
                      <div className={box + " mt-3"}>
                        <h3 className="font-bold">
                          {t("console_people.portfolio.course_details")}
                        </h3>
                        <div className="my-2 flex flex-col gap-1">
                          {meta(
                            t("console_people.portfolio.program"),
                            item.name,
                          )}
                          {meta(
                            t("console_people.portfolio.code"),
                            entry.code,
                          )}
                          {meta(
                            t("console_people.portfolio.level"),
                            entry.level,
                          )}
                          {meta(
                            t("console_people.portfolio.session_mode"),
                            entry.session_mode,
                          )}
                          {meta(
                            t("console_people.portfolio.duration"),
                            entry.default_duration_minutes,
                          )}
                          {meta(
                            t("console_people.portfolio.sessions_per_week"),
                            entry.sessions_per_week,
                          )}
                          {meta(
                            t("console_people.portfolio.total_sessions"),
                            entry.total_sessions,
                          )}
                        </div>

                        {entry.groups.length > 0 && (
                          <>
                            <h4 className="font-bold mt-4">
                              {t("console_people.portfolio.groups")}
                            </h4>
                            <ul className="my-2 flex flex-col gap-2">
                              {entry.groups.map((group) => (
                                <li key={group.id} className={box}>
                                  <span>
                                    {group.url ? (
                                      <a
                                        className="font-bold underline"
                                        href={group.url}
                                      >
                                        {group.name}
                                      </a>
                                    ) : (
                                      <strong>{group.name}</strong>
                                    )}
                                    <span className="text-sm">
                                      {[group.code, group.status, group.role]
                                        .filter(Boolean)
                                        .map((part) => " · " + part)
                                        .join("")}
                                    </span>
                                  </span>
                                  <span className="block text-sm">
                                    {t("console_people.portfolio.slots")}:{" "}
                                    {slots(group.slots)} ·{" "}
                                    {t(
                                      "console_people.portfolio.students_count",
                                    )}
                                    : {group.students_count}
                                  </span>
                                </li>
                              ))}
                            </ul>
                          </>
                        )}

                        <h4 className="font-bold mt-4">
                          {t("console_people.portfolio.students")}
                        </h4>
                        {entry.students.length === 0 && (
                          <p className="text-sm my-2">
                            {entry.students_count > 0
                              ? t("console_people.portfolio.students_hidden")
                              : t("console_people.portfolio.no_students")}
                          </p>
                        )}
                        <ul className="my-2 flex flex-col gap-2">
                          {entry.students.map((student) => (
                            <li key={student.id} className={box}>
                              <span>
                                {student.url ? (
                                  <a
                                    className="font-bold underline"
                                    href={student.url}
                                  >
                                    {student.name}
                                  </a>
                                ) : (
                                  <strong>{student.name}</strong>
                                )}
                                {student.code && (
                                  <span className="text-sm">
                                    {" "}
                                    · {student.code}
                                  </span>
                                )}
                                {student.group && (
                                  <span className="text-sm">
                                    {" "}
                                    · {student.group}
                                  </span>
                                )}
                              </span>
                              <span className="block text-sm">
                                {student.awaiting
                                  ? t("console_people.portfolio.awaiting")
                                  : t("console_people.portfolio.slots") +
                                    ": " +
                                    slots(student.slots)}
                                {student.duration_minutes !== null &&
                                  " · " +
                                    student.duration_minutes +
                                    " " +
                                    t("console_people.portfolio.minutes")}
                              </span>
                            </li>
                          ))}
                        </ul>

                        <button
                          type="button"
                          className={ghost + " mt-2"}
                          onClick={() => setCourseId(null)}
                        >
                          {t("console_people.portfolio.close")}
                        </button>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
