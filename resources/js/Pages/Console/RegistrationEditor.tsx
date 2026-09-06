import { Head, Link, useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import type {
  RegistrationCourse,
  RegistrationFormData,
  RegistrationQuestion,
} from "./RegistrationTypes";

export default function RegistrationEditor({
  form: record,
  catalog,
  questionTypes,
  publicEnabled,
}: {
  form: RegistrationFormData;
  catalog: RegistrationCourse[];
  questionTypes: {
    id: string;
    name: string;
    has_options: boolean;
    filterable: boolean;
  }[];
  publicEnabled: boolean;
}) {
  const t = useI18n();
  const [preview, setPreview] = useState(false);
  const [copied, setCopied] = useState(false);
  const form = useForm({
    title: record.title,
    description: record.description,
    slug: record.slug,
    is_active: record.is_active,
    preferred_program_id: record.preferred_program_id,
    preferred_course_id: record.preferred_course_id,
    questions: record.questions,
  });
  const chosen = catalog.find(
    (course) => course.id === form.data.preferred_course_id,
  );
  const error = (key: string) => form.errors[key as keyof typeof form.errors];
  const feedback = (key: string) =>
    error(key) ? (
      <small role="alert" id={"error-" + key}>
        {error(key)}
      </small>
    ) : null;
  const updateQuestion = (
    index: number,
    change: Partial<RegistrationQuestion>,
  ) =>
    form.setData(
      "questions",
      form.data.questions.map((question, i) =>
        i === index ? { ...question, ...change } : question,
      ),
    );
  const move = (index: number, offset: number) => {
    const questions = [...form.data.questions];
    const current = questions[index];
    const target = questions[index + offset];
    if (!current || !target) return;
    questions[index] = target;
    questions[index + offset] = current;
    form.setData("questions", questions);
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      questions: data.questions.map((question) => ({
        ...question,
        options: question.options
          .map((option) => option.trim())
          .filter(Boolean),
      })),
    }));
    if (record.id)
      form.patch("/manage/registration/forms/" + record.id, {
        preserveScroll: true,
      });
    else form.post("/manage/registration/forms", { preserveScroll: true });
  };
  const back =
    "/manage/registration" +
    (form.data.preferred_course_id
      ? "?course=" + form.data.preferred_course_id
      : "");
  const confirmClose = () =>
    !form.isDirty || window.confirm(t("console_registration.discard"));
  const label = (key: string) => t("console_registration.fields." + key);
  const title = t(
    record.id
      ? "console_registration.edit_form"
      : "console_registration.create_form",
  );
  return (
    <ConsoleLayout
      title={title}
      section="before"
      description={t("console_registration.editor_help")}
      actions={
        <div className="actions">
          <button
            type="button"
            className="console-button"
            onClick={() => setPreview(!preview)}
          >
            {t(
              preview
                ? "console_registration.back_editor"
                : "console_registration.preview",
            )}
          </button>
          <Link className="console-button" href={back} onBefore={confirmClose}>
            {t("console_registration.back_center")}
          </Link>
        </div>
      }
    >
      <Head title={title} />
      {preview ? (
        <section className="panel">
          <div className="panel-head">
            <div>
              <h2>{form.data.title || title}</h2>
              <p>{t("console_registration.preview_help")}</p>
            </div>
          </div>
          <div className="form-section">
            <p>{form.data.description}</p>
            <p className="cell-sub">
              {chosen ? chosen.name : t("console_registration.general_form")}
            </p>
          </div>
          <div className="form-section field-grid">
            {[
              "full_name",
              "phone",
              "email",
              "date_of_birth",
              "gender",
              "country",
              "region",
              "notes",
            ].map((key) => (
              <div className="field" key={key}>
                <label htmlFor={"preview-" + key}>{label(key)}</label>
                <input
                  id={"preview-" + key}
                  className="console-control"
                  disabled
                />
              </div>
            ))}
          </div>
          <div className="form-section field-grid">
            {form.data.questions
              .filter((question) => question.is_active)
              .map((question, index) => (
                <div className="field span2" key={index}>
                  <label htmlFor={"preview-question-" + index}>
                    {question.question}
                    {question.is_required
                      ? " · " + t("console_registration.required")
                      : ""}
                  </label>
                  {question.type === "textarea" ? (
                    <textarea
                      id={"preview-question-" + index}
                      className="console-control"
                      disabled
                    />
                  ) : ["select", "radio", "checkbox"].includes(
                      question.type,
                    ) ? (
                    <select
                      id={"preview-question-" + index}
                      className="console-control"
                      disabled
                      multiple={question.type === "checkbox"}
                    >
                      {question.options.map((option, i) => (
                        <option key={i}>{option}</option>
                      ))}
                    </select>
                  ) : (
                    <input
                      id={"preview-question-" + index}
                      className="console-control"
                      type={question.type === "number" ? "number" : "text"}
                      disabled
                    />
                  )}
                </div>
              ))}
          </div>
          <div className="savebar">
            <p>{t("console_registration.preview_help")}</p>
            <button className="console-button primary" disabled>
              {t("console_registration.submit_preview")}
            </button>
          </div>
        </section>
      ) : (
        <div className="setup-layout">
          <form className="panel" onSubmit={submit}>
            {Object.keys(form.errors).length > 0 && (
              <div role="alert" className="console-flash error">
                <p>{t("console_registration.correct_errors")}</p>
                <ul>
                  {Object.entries(form.errors).map(([key, message]) => (
                    <li key={key}>{message}</li>
                  ))}
                </ul>
              </div>
            )}
            <section className="form-section">
              <h2>
                <span className="step-number">01</span>
                {t("console_registration.identity")}
              </h2>
              <div className="field-grid">
                <div className="field span2">
                  <label htmlFor="registration-title">{label("title")}</label>
                  <input
                    className="console-control"
                    id="registration-title"
                    required
                    maxLength={255}
                    value={form.data.title}
                    onChange={(event) =>
                      form.setData("title", event.target.value)
                    }
                    aria-invalid={!!error("title")}
                    aria-describedby={
                      error("title") ? "error-title" : undefined
                    }
                  />
                  {feedback("title")}
                </div>
                <div className="field">
                  <label htmlFor="registration-course">{label("course")}</label>
                  <select
                    id="registration-course"
                    className="console-control"
                    value={form.data.preferred_course_id ?? ""}
                    onChange={(event) => {
                      const course = catalog.find(
                        (item) => item.id === event.target.value,
                      );
                      form.setData((data) => ({
                        ...data,
                        preferred_course_id: course?.id ?? null,
                        preferred_program_id: course?.program_id ?? null,
                      }));
                    }}
                  >
                    <option value="">
                      {t("console_registration.general_form")}
                    </option>
                    {catalog.map((course) => (
                      <option value={course.id} key={course.id}>
                        {course.name} · {course.program_name}
                      </option>
                    ))}
                  </select>
                  {feedback("preferred_course_id")}
                </div>
                <div className="field">
                  <label htmlFor="registration-slug">{label("slug")}</label>
                  <input
                    id="registration-slug"
                    className="console-control"
                    dir="ltr"
                    required
                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                    maxLength={120}
                    value={form.data.slug}
                    onChange={(event) =>
                      form.setData("slug", event.target.value)
                    }
                    aria-invalid={!!error("slug")}
                  />
                  {feedback("slug")}
                  <small>{t("console_registration.slug_help")}</small>
                </div>
                <div className="field span2">
                  <label htmlFor="registration-description">
                    {label("description")}{" "}
                    <span className="required-label">
                      ({t("console_registration.optional")})
                    </span>
                  </label>
                  <textarea
                    id="registration-description"
                    className="console-control"
                    maxLength={3000}
                    value={form.data.description}
                    onChange={(event) =>
                      form.setData("description", event.target.value)
                    }
                  />
                  {feedback("description")}
                </div>
              </div>
            </section>
            <section className="form-section">
              <h2>
                <span className="step-number">02</span>
                {t("console_registration.basic_data")}
              </h2>
              <p className="cell-sub">
                {t("console_registration.basic_data_help")}
              </p>
            </section>
            <section className="form-section">
              <h2>
                <span className="step-number">03</span>
                {t("console_registration.questions")}
              </h2>
              {form.data.questions.map((question, index) => {
                const type = questionTypes.find(
                  (item) => item.id === question.type,
                );
                return (
                  <section
                    className="question-editor"
                    key={question.id ?? "new-" + index}
                  >
                    <div className="question-editor-head">
                      <h3>
                        {t("console_registration.question_number")}{" "}
                        <bdi>{String(index + 1).padStart(2, "0")}</bdi>
                      </h3>
                      <div className="actions">
                        <button
                          type="button"
                          className="console-button small"
                          disabled={index === 0}
                          aria-label={
                            t("console_registration.move_up") +
                            " " +
                            String(index + 1)
                          }
                          onClick={() => move(index, -1)}
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          className="console-button small"
                          disabled={index === form.data.questions.length - 1}
                          aria-label={
                            t("console_registration.move_down") +
                            " " +
                            String(index + 1)
                          }
                          onClick={() => move(index, 1)}
                        >
                          ↓
                        </button>
                      </div>
                    </div>
                    <div className="field-grid">
                      <div className="field span2">
                        <label htmlFor={"question-" + index}>
                          {label("question")}
                        </label>
                        <input
                          id={"question-" + index}
                          className="console-control"
                          required
                          maxLength={1000}
                          value={question.question}
                          onChange={(event) =>
                            updateQuestion(index, {
                              question: event.target.value,
                            })
                          }
                        />
                        {feedback("questions." + index + ".question")}
                      </div>
                      <div className="field">
                        <label htmlFor={"question-type-" + index}>
                          {label("answer_type")}
                        </label>
                        <select
                          id={"question-type-" + index}
                          className="console-control"
                          value={question.type}
                          onChange={(event) => {
                            const next = questionTypes.find(
                              (item) => item.id === event.target.value,
                            );
                            updateQuestion(index, {
                              type: event.target.value,
                              is_filterable: next?.filterable
                                ? question.is_filterable
                                : false,
                              options: next?.has_options
                                ? question.options
                                : [],
                            });
                          }}
                        >
                          {questionTypes.map((item) => (
                            <option value={item.id} key={item.id}>
                              {item.name}
                            </option>
                          ))}
                        </select>
                      </div>
                      {type?.has_options && (
                        <div className="field span2">
                          <label htmlFor={"question-options-" + index}>
                            {label("options")}
                          </label>
                          <textarea
                            id={"question-options-" + index}
                            className="console-control"
                            value={question.options.join("\n")}
                            onChange={(event) =>
                              updateQuestion(index, {
                                options: event.target.value.split("\n"),
                              })
                            }
                          />
                          <small>
                            {t("console_registration.options_help")}
                          </small>
                          {feedback("questions." + index + ".options")}
                        </div>
                      )}
                    </div>
                    <div className="question-toggles">
                      <label>
                        <input
                          type="checkbox"
                          checked={question.is_required}
                          onChange={(event) =>
                            updateQuestion(index, {
                              is_required: event.target.checked,
                            })
                          }
                        />
                        {label("is_required")}
                      </label>
                      <label>
                        <input
                          type="checkbox"
                          checked={question.is_active}
                          onChange={(event) =>
                            updateQuestion(index, {
                              is_active: event.target.checked,
                            })
                          }
                        />
                        {label("question_active")}
                      </label>
                      <label>
                        <input
                          type="checkbox"
                          checked={question.is_filterable}
                          disabled={!type?.filterable}
                          onChange={(event) =>
                            updateQuestion(index, {
                              is_filterable: event.target.checked,
                            })
                          }
                        />
                        {label("is_filterable")}
                      </label>
                    </div>
                    {feedback("questions." + index + ".is_filterable")}
                    <div className="actions">
                      <button
                        type="button"
                        className="inline-link"
                        onClick={() =>
                          form.setData("questions", [
                            ...form.data.questions,
                            { ...question, id: null },
                          ])
                        }
                      >
                        {t("console_registration.copy_question")}
                      </button>
                      <button
                        type="button"
                        className="inline-link"
                        onClick={() =>
                          form.setData(
                            "questions",
                            form.data.questions.filter((_, i) => i !== index),
                          )
                        }
                      >
                        {t("console_registration.remove_question")}
                      </button>
                    </div>
                  </section>
                );
              })}
              {form.data.questions.length === 0 && (
                <p className="cell-sub">
                  {t("console_registration.no_questions")}
                </p>
              )}
              <button
                type="button"
                className="console-button"
                onClick={() =>
                  form.setData("questions", [
                    ...form.data.questions,
                    {
                      id: null,
                      question: "",
                      type: "text",
                      options: [],
                      is_required: false,
                      is_active: true,
                      is_filterable: false,
                    },
                  ])
                }
              >
                {t("console_registration.add_question")}
              </button>
            </section>
            <section className="form-section">
              <h2>
                <span className="step-number">04</span>
                {t("console_registration.publishing")}
              </h2>
              <label className="question-check">
                <input
                  type="checkbox"
                  checked={form.data.is_active}
                  onChange={(event) =>
                    form.setData("is_active", event.target.checked)
                  }
                />
                {label("is_active")}
              </label>
              <p className="cell-sub">
                {t("console_registration.deactivate_help")}
              </p>
              {!publicEnabled && (
                <p className="cell-sub">
                  {t("console_registration.public_disabled")}
                </p>
              )}
            </section>
            <footer className="savebar">
              <p>{t("console_registration.save_help")}</p>
              <button
                type="submit"
                className="console-button primary"
                disabled={form.processing}
              >
                {t(
                  form.processing
                    ? "console_registration.saving"
                    : "console_registration.save",
                )}
              </button>
            </footer>
          </form>
          <aside className="panel summary">
            <div className="panel-head">
              <h2>{t("console_registration.summary")}</h2>
            </div>
            <div className="summary-content">
              <dl>
                {[
                  [
                    label("title"),
                    form.data.title || t("console_registration.unnamed"),
                  ],
                  [
                    label("course"),
                    chosen?.name || t("console_registration.general_form"),
                  ],
                  [
                    t("console_registration.questions"),
                    String(form.data.questions.length),
                  ],
                  [
                    t("console_registration.status"),
                    t(
                      form.data.is_active
                        ? "console_registration.active"
                        : "console_registration.draft",
                    ),
                  ],
                ].map(([key, value]) => (
                  <div className="definition" key={key}>
                    <dt>{key}</dt>
                    <dd>{value}</dd>
                  </div>
                ))}
              </dl>
              <div className="summary-note">
                {t("console_registration.snapshot_help")}
              </div>
              {record.public_url && (
                <div className="registration-link">
                  <label htmlFor="saved-public-url">
                    {t("console_registration.public_link")}
                  </label>
                  <input
                    className="console-control"
                    id="saved-public-url"
                    dir="ltr"
                    value={record.public_url}
                    readOnly
                  />
                  <button
                    type="button"
                    className="inline-link"
                    onClick={async () => {
                      try {
                        await navigator.clipboard.writeText(record.public_url!);
                        setCopied(true);
                      } catch {
                        setCopied(false);
                      }
                    }}
                  >
                    {t(
                      copied
                        ? "console_registration.copied"
                        : "console_registration.copy_link",
                    )}
                  </button>
                  {record.is_active && publicEnabled && (
                    <a
                      className="inline-link"
                      href={record.public_url}
                      target="_blank"
                      rel="noreferrer"
                    >
                      {t("console_registration.open_public")}
                    </a>
                  )}
                </div>
              )}
            </div>
          </aside>
        </div>
      )}
    </ConsoleLayout>
  );
}
