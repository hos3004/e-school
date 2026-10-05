import { Head, Link, router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import type {
  RegistrationCourse,
  RegistrationFormData,
  RegistrationPage,
  Option,
} from "./RegistrationTypes";

interface Props {
  catalogAbilities?: { courses: boolean; groups: boolean; programs: boolean };
  filters: Record<string, string | number | null>;
  catalog: RegistrationCourse[];
  forms: RegistrationFormData[];
  counts: { requests: number; accepted: number };
  applications: RegistrationPage | null;
  questionFilters: {
    id: string;
    label: string;
    type: string;
    options: string[];
  }[];
  countries: Option[];
  statuses: Option[];
  publicEnabled: boolean;
  canPlace: boolean;
}
export default function Registration(props: Props) {
  const t = useI18n();
  const stage = String(props.filters.stage ?? "forms");
  const course = String(props.filters.course ?? "");
  const [copied, setCopied] = useState("");
  const [filters, setFilters] = useState<Record<string, string>>(() =>
    Object.fromEntries(
      [
        "search",
        "form",
        "status",
        "country",
        "age_from",
        "age_to",
        "date_from",
        "date_to",
        "question",
        "answer",
        "answer_from",
        "answer_to",
      ].map((key) => [key, String(props.filters[key] ?? "")]),
    ),
  );
  const chosenQuestion = props.questionFilters.find(
    (question) => question.id === filters.question,
  );
  const href = (nextStage: string, extra: Record<string, string> = {}) =>
    "/manage/registration?" +
    new URLSearchParams({
      ...(course ? { course } : {}),
      stage: nextStage,
      ...extra,
    }).toString();
  const apply = (event: FormEvent) => {
    event.preventDefault();
    router.get(
      "/manage/registration",
      Object.fromEntries(
        Object.entries({ ...filters, course, stage }).filter(
          ([, value]) => value !== "",
        ),
      ),
      { preserveScroll: true, preserveState: true },
    );
  };
  const set = (key: string, value: string) =>
    setFilters((old) => ({ ...old, [key]: value }));
  const field = (key: string, type = "text") => (
    <div
      className={"field" + (key === "search" ? " console-filter-search" : "")}
      key={key}
    >
      <label htmlFor={"filter-" + key}>
        {t("console_registration.fields." + key)}
      </label>
      <input
        id={"filter-" + key}
        type={type}
        className="console-control"
        value={filters[key]}
        onChange={(event) => set(key, event.target.value)}
        dir={type === "number" || type === "date" ? "ltr" : undefined}
      />
    </div>
  );
  const select = (key: string, options: Option[]) => (
    <div
      className={
        "field" +
        (["form", "question", "answer"].includes(key)
          ? " console-filter-mobile-full"
          : "")
      }
      key={key}
    >
      <label htmlFor={"filter-" + key}>
        {t("console_registration.fields." + key)}
      </label>
      <select
        id={"filter-" + key}
        className="console-control"
        value={filters[key]}
        onChange={(event) => {
          set(key, event.target.value);
          if (key === "question")
            setFilters((old) => ({
              ...old,
              question: event.target.value,
              answer: "",
              answer_from: "",
              answer_to: "",
            }));
        }}
      >
        <option value="">{t("console_registration.all")}</option>
        {options.map((option) => (
          <option value={option.id} key={option.id}>
            {option.name}
          </option>
        ))}
      </select>
    </div>
  );
  return (
    <ConsoleLayout
      title={t("console_registration.title")}
      section="before"
      description={t("console_registration.description")}
      actions={
        <Link
          className="console-button primary"
          href={
            "/manage/registration/forms/create" +
            (course ? "?course=" + course : "")
          }
        >
          {t("console_registration.create_form")}
        </Link>
      }
    >
      <Head title={t("console_registration.title")} />
      <section className="panel console-catalog">
        <nav
          className="console-subtabs"
          aria-label={t("console_courses.sections")}
        >
          {props.catalogAbilities?.courses && (
            <Link href="/manage/courses">
              {t("console_courses.tabs.courses")}
            </Link>
          )}
          {props.catalogAbilities?.groups && (
            <Link href="/manage/groups">
              {t("console_courses.tabs.groups")}
            </Link>
          )}
          <Link href={href(stage)} aria-current="page">
            {t("console.nav.registration")}
          </Link>
          {props.catalogAbilities?.programs && (
            <Link href="/manage/courses/programs">
              {t("console_courses.tabs.programs")}
            </Link>
          )}
        </nav>
        <div className="registration-center">
          <div className="registration-context">
            <div>
              <h2>{t("console_registration.journey_title")}</h2>
              <p>{t("console_registration.journey_help")}</p>
            </div>
            <div className="field">
              <label htmlFor="registration-context">
                {t("console_registration.fields.course")}
              </label>
              <select
                id="registration-context"
                className="console-control"
                value={course}
                onChange={(event) =>
                  router.get(
                    "/manage/registration",
                    {
                      stage,
                      ...(event.target.value
                        ? { course: event.target.value }
                        : {}),
                    },
                    { preserveScroll: true },
                  )
                }
              >
                <option value="">
                  {t("console_registration.all_courses")}
                </option>
                {props.catalog.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name} · {item.program_name}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <nav
            className="registration-stages"
            aria-label={t("console_registration.stages")}
          >
            {[
              ["forms", props.forms.length],
              ["requests", props.counts.requests],
              ["accepted", props.counts.accepted],
            ].map(([id, count], index) => (
              <Link
                key={id}
                className={
                  "registration-stage " + (stage === id ? "current" : "")
                }
                aria-current={stage === id ? "page" : undefined}
                href={href(String(id))}
              >
                <span>{String(index + 1).padStart(2, "0")}</span>
                {t("console_registration.stages_" + id)}
                <b>{String(count)}</b>
              </Link>
            ))}
          </nav>
          {stage === "forms" ? (
            <>
              {props.forms.length === 0 && (
                <div className="console-empty">
                  <h2>{t("console_registration.empty_forms")}</h2>
                  <p>{t("console_registration.empty_forms_help")}</p>
                </div>
              )}
              {props.forms.map((form) => (
                <article key={form.id} className="registration-form-row">
                  <div>
                    <span
                      className={
                        "console-status " + (form.is_active ? "success" : "")
                      }
                    >
                      {t(
                        form.is_active
                          ? "console_registration.active"
                          : "console_registration.draft",
                      )}
                    </span>
                    <h3>{form.title}</h3>
                    <p>
                      {props.catalog.find(
                        (item) => item.id === form.preferred_course_id,
                      )?.name ?? t("console_registration.general_form")}
                    </p>
                    <small>
                      <bdi>{String(form.questions.length)}</bdi>{" "}
                      {t("console_registration.questions")} ·{" "}
                      <bdi>{String(form.applications_count ?? 0)}</bdi>{" "}
                      {t("console_registration.applications")}
                    </small>
                    <p className="cell-sub">{form.description}</p>
                    <div className="registration-link">
                      <label htmlFor={"form-link-" + form.id}>
                        {t("console_registration.public_link")}
                      </label>
                      <div className="input-action">
                        <input
                          id={"form-link-" + form.id}
                          className="console-control"
                          readOnly
                          dir="ltr"
                          value={form.public_url ?? ""}
                        />
                        <button
                          type="button"
                          className="console-button"
                          onClick={async () => {
                            try {
                              await navigator.clipboard.writeText(
                                form.public_url ?? "",
                              );
                              setCopied(form.id ?? "");
                            } catch {
                              setCopied("");
                            }
                          }}
                        >
                          {t(
                            copied === form.id
                              ? "console_registration.copied"
                              : "console_registration.copy_link",
                          )}
                        </button>
                      </div>
                      {!form.is_active && (
                        <small>{t("console_registration.inactive_link")}</small>
                      )}
                      {form.is_active && !props.publicEnabled && (
                        <small>
                          {t("console_registration.public_disabled")}
                        </small>
                      )}
                    </div>
                  </div>
                  <div className="course-row-actions">
                    <Link
                      className="console-button"
                      href={"/manage/registration/forms/" + form.id + "/edit"}
                    >
                      {t("console_registration.edit_form")}
                    </Link>
                    <Link
                      className="inline-link"
                      href={
                        "/manage/registration/forms/create?" +
                        new URLSearchParams({
                          template: form.id ?? "",
                          ...(course ? { course } : {}),
                        }).toString()
                      }
                    >
                      {t("console_registration.use_template")}
                    </Link>
                    <Link
                      className="inline-link"
                      href={href("requests", { form: form.id ?? "" })}
                    >
                      {t("console_registration.view_requests")}
                    </Link>
                    {form.is_active && props.publicEnabled && (
                      <a
                        className="inline-link"
                        href={form.public_url ?? ""}
                        target="_blank"
                        rel="noreferrer"
                      >
                        {t("console_registration.open_public")}
                      </a>
                    )}
                  </div>
                </article>
              ))}
            </>
          ) : (
            <>
              <form
                onSubmit={apply}
                className="registration-filters console-filter-bar console-filter-mobile-pairs"
              >
                {field("search")}
                {select(
                  "form",
                  props.forms.map((form) => ({
                    id: form.id ?? "",
                    name: form.title,
                  })),
                )}
                {select("status", props.statuses)}
                {select("country", props.countries)}
                {select(
                  "question",
                  props.questionFilters.map((question) => ({
                    id: question.id,
                    name: question.label,
                  })),
                )}
                <div className="console-filter-range">
                  {field("age_from", "number")}
                  {field("age_to", "number")}
                </div>
                <div className="console-filter-range">
                  {field("date_from", "date")}
                  {field("date_to", "date")}
                </div>
                {chosenQuestion &&
                  (chosenQuestion.type === "number" ? (
                    <div className="console-filter-range">
                      {field("answer_from", "number")}
                      {field("answer_to", "number")}
                    </div>
                  ) : (
                    select(
                      "answer",
                      chosenQuestion.options.map((option) => ({
                        id: option,
                        name: option,
                      })),
                    )
                  ))}
                <div className="actions console-filter-actions">
                  <button type="submit" className="console-button">
                    {t("console_registration.apply_filters")}
                  </button>
                  <Link href={href(stage)} className="inline-link">
                    {t("console_registration.reset_filters")}
                  </Link>
                </div>
              </form>
              {props.applications?.data.length === 0 ? (
                <div className="console-empty">
                  <h2>{t("console_registration.empty_requests")}</h2>
                  <p>{t("console_registration.empty_requests_help")}</p>
                </div>
              ) : (
                <div
                  className="console-table-wrap"
                  role="region"
                  tabIndex={0}
                  aria-label={t("console_registration.applications")}
                >
                  <table className="console-table registration-requests">
                    <caption className="sr-only">
                      {t("console_registration.applications")}
                    </caption>
                    <thead>
                      <tr>
                        {[
                          "application",
                          "source",
                          "answers",
                          "status",
                          "action",
                        ].map((key) => (
                          <th key={key} scope="col">
                            {t("console_registration." + key)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {props.applications?.data.map((application) => (
                        <tr key={application.id}>
                          <td>
                            <Link
                              className="cell-title"
                              href={
                                "/manage/registration/applications/" +
                                application.id
                              }
                            >
                              {application.full_name}
                            </Link>
                            <span className="cell-sub">
                              <bdi>
                                {application.student_code ??
                                  application.id.slice(-8)}
                              </bdi>{" "}
                              ·{" "}
                              {props.countries.find(
                                (country) =>
                                  country.id === application.country_id,
                              )?.name ?? t("console_registration.unspecified")}
                            </span>
                            <small>
                              <bdi>{application.submitted_at}</bdi>
                            </small>
                          </td>
                          <td>
                            {application.source}
                            <span className="cell-sub">
                              {props.catalog.find(
                                (item) =>
                                  item.id === application.preferred_course_id,
                              )?.name ?? t("console_registration.general_form")}
                            </span>
                          </td>
                          <td>
                            {application.answers.map((answer) => (
                              <span
                                className="cell-sub"
                                key={answer.question_id}
                              >
                                {answer.question}:{" "}
                                {Array.isArray(answer.answer)
                                  ? answer.answer.join("، ")
                                  : answer.answer}
                              </span>
                            ))}
                            {application.answers.length === 0 && (
                              <span className="cell-sub">
                                {t("console_registration.no_answers")}
                              </span>
                            )}
                            {application.duplicate_of_application_id && (
                              <span className="console-status warning">
                                {t("console_registration.duplicate_flag")}
                              </span>
                            )}
                          </td>
                          <td>
                            <span
                              className={
                                "console-status " +
                                (application.can_place
                                  ? "success"
                                  : application.status === "rejected"
                                    ? ""
                                    : "warning")
                              }
                            >
                              {
                                props.statuses.find(
                                  (status) => status.id === application.status,
                                )?.name
                              }
                            </span>
                          </td>
                          <td>
                            <div className="course-row-actions">
                              <Link
                                className="inline-link"
                                href={
                                  "/manage/registration/applications/" +
                                  application.id
                                }
                              >
                                {t("console_registration.review_request")}
                              </Link>
                              {application.can_place &&
                                application.placement_url && (
                                  <Link
                                    className="inline-link"
                                    href={application.placement_url}
                                  >
                                    {t("console_registration.placement_link")}
                                  </Link>
                                )}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              {props.applications && (
                <footer className="panel-foot">
                  <span>
                    {t("console_registration.result_count").replace(
                      ":count",
                      String(props.applications.total),
                    )}
                  </span>
                  <div className="actions">
                    {props.applications.prev_page_url && (
                      <Link
                        className="inline-link"
                        href={props.applications.prev_page_url}
                      >
                        {t("console_registration.previous")}
                      </Link>
                    )}
                    <span>
                      <bdi>
                        {String(props.applications.current_page)} /{" "}
                        {String(props.applications.last_page)}
                      </bdi>
                    </span>
                    {props.applications.next_page_url && (
                      <Link
                        className="inline-link"
                        href={props.applications.next_page_url}
                      >
                        {t("console_registration.next")}
                      </Link>
                    )}
                  </div>
                </footer>
              )}
            </>
          )}
        </div>
      </section>
    </ConsoleLayout>
  );
}
