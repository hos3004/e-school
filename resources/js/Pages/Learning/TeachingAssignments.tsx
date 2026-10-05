import axios from "axios";
import { Link, useForm, usePage } from "@inertiajs/react";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
} from "react";
import LearningLayout from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import Icon from "./Icon";
import { date, Empty, Errors, ServiceLink } from "./shared";
type Target = { key: string; title: string };
type Submission = {
  id: string;
  student_profile_id: string;
  student_name: string;
  profile_url: string | null;
  content: string | null;
  attachments: string[];
  status: string;
  status_label: string;
  score: number | null;
  raw_score: number | null;
  feedback: string | null;
  submitted_at: string | null;
};
type Task = {
  id: string;
  title: Record<string, string>;
  instructions: Record<string, string> | null;
  due_at: string;
  max_score: number;
  submissions_count?: number;
  submissions?: Submission[];
  attachments: string[];
};
function errorText(error: unknown): Record<string, string> {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      errors?: Record<string, string[]>;
      message?: string;
      error?: { message?: string };
    };
    if (data?.errors)
      return Object.fromEntries(
        Object.entries(data.errors).map(([key, value]) => [
          key,
          value[0] ?? "",
        ]),
      );
    return { request: data?.error?.message ?? data?.message ?? "" };
  }
  return {};
}
export default function TeachingAssignments({
  targets,
  timezone,
  canCreate,
  canGrade,
}: {
  targets: Target[];
  timezone: string;
  canCreate: boolean;
  canGrade: boolean;
}) {
  const t = useI18n();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [next, setNext] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [creating, setCreating] = useState(
    () =>
      canCreate &&
      new URLSearchParams(window.location.search).get("create") === "1",
  );
  const [task, setTask] = useState<Task | null>(null);
  const [opening, setOpening] = useState<string | null>(null);
  const [feedback, setFeedback] = useState("");
  const load = useCallback(
    async (url = "/learn/teacher/assignments/list", append = false) => {
      setLoading(true);
      setFailed(false);
      try {
        const response = await axios.get<{
          data: Task[];
          links: { next: string | null };
        }>(url);
        setTasks((old) =>
          append ? [...old, ...response.data.data] : response.data.data,
        );
        setNext(response.data.links.next);
      } catch {
        setFailed(true);
      } finally {
        setLoading(false);
      }
    },
    [],
  );
  useEffect(() => {
    void load();
  }, [load]);
  const open = useCallback(async (id: string) => {
    setOpening(id);
    setFailed(false);
    try {
      const response = await axios.get<{ data: Task }>(
        `/learn/teacher/assignments/${id}`,
      );
      setTask(response.data.data);
    } catch {
      setFailed(true);
    } finally {
      setOpening(null);
    }
  }, []);
  useEffect(() => {
    const selected = new URLSearchParams(window.location.search).get(
      "assignment",
    );
    if (selected && /^[0-9a-hjkmnp-tv-z]{26}$/i.test(selected))
      void open(selected);
  }, [open]);
  return (
    <LearningLayout kind="teacher" title={t("learning.teaching.title")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.tasks_kicker")}
          </div>
          <h1>{t("learning.teaching.title")}</h1>
          <p>{t("learning.teaching.intro")}</p>
        </div>
        {canCreate && (
          <button className="lp-btn" onClick={() => setCreating(true)}>
            <Icon name="file" size={17} />
            {t("learning.teaching.new")}
          </button>
        )}
      </div>
      <div className="lp-inline-details">
        <Link className="lp-text-action" href="/learn/teacher#tasks">
          {t("learning.back_portal")}
        </Link>
        <Link className="lp-text-action" href="/learn/teacher/library">
          {t("learning.teaching.library")}
        </Link>
      </div>
      {feedback && (
        <p className="lp-feedback" role="status">
          {feedback}
        </p>
      )}
      {failed && (
        <div className="lp-feedback lp-error" role="alert">
          {t("learning.teaching.load_error")}
          <button className="lp-text-action" onClick={() => void load()}>
            {t("learning.workspace.retry")}
          </button>
        </div>
      )}
      <section className="lp-section lp-task-list" aria-busy={loading}>
        {loading && tasks.length === 0 ? (
          <Empty>{t("learning.teaching.loading")}</Empty>
        ) : tasks.length ? (
          <div className="lp-notices">
            {tasks.map((item) => (
              <button
                key={item.id}
                className="lp-resource-row"
                disabled={opening === item.id}
                onClick={() => void open(item.id)}
              >
                <Icon name="file" />
                <span>
                  <b>{item.title.ar}</b>
                  <small>
                    {t("learning.workspace.due")}{" "}
                    {date(item.due_at, "ar", timezone)} ·{" "}
                    {date(item.due_at, "ar", timezone, true)}
                  </small>
                  <span className="lp-resource-state">
                    {item.submissions_count ?? 0}{" "}
                    {t("learning.teaching.submissions")}
                  </span>
                </span>
                <Icon name="arrow" />
              </button>
            ))}
          </div>
        ) : (
          !failed && <Empty>{t("learning.teaching.empty")}</Empty>
        )}
        {next && (
          <button
            className="lp-text-action"
            disabled={loading}
            onClick={() => void load(next, true)}
          >
            {t("notifications.load_more")}
          </button>
        )}
      </section>
      {creating && (
        <CreateTask
          targets={targets}
          timezone={timezone}
          onClose={() => setCreating(false)}
          onSuccess={() => {
            setCreating(false);
            void load();
          }}
        />
      )}
      {task && (
        <ReviewTask
          task={task}
          timezone={timezone}
          canGrade={canGrade}
          onClose={() => setTask(null)}
          onSaved={() => {
            setFeedback(t("learning.teaching.graded"));
            void open(task.id);
            void load();
          }}
        />
      )}
    </LearningLayout>
  );
}
function CreateTask({
  targets,
  timezone,
  onClose,
  onSuccess,
}: {
  targets: Target[];
  timezone: string;
  onClose: () => void;
  onSuccess: () => void;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const accountId = usePage<{ auth: { user: { id: string } } }>().props.auth
    .user.id;
  const form = useForm(`learning-teacher-new-assignment-${accountId}`, {
    target: targets[0]?.key ?? "",
    title: "",
    instructions: "",
    due_local: "",
    max_score: "",
    allows_late: false,
    late_penalty_percent: "0",
  });
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    return () => element?.close();
  }, []);
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/learn/teacher/assignments", {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onSuccess();
      },
    });
  }
  return (
    <dialog
      ref={dialog}
      className="lp-sheet lp-live-drawer"
      dir="rtl"
      aria-labelledby="new-task-title"
      onClose={onClose}
    >
      <header className="lp-drawer-head">
        <div>
          <h2 id="new-task-title">{t("learning.teaching.new")}</h2>
          <p>{t("learning.teaching.new_hint")}</p>
        </div>
        <button
          className="lp-close"
          aria-label={t("learning.close")}
          onClick={onClose}
        >
          <Icon name="close" />
        </button>
      </header>
      <form className="lp-sheet-body lp-form" onSubmit={submit}>
        <Errors errors={form.errors} />
        <label className="lp-field">
          <span>{t("learning.teaching.target")}</span>
          <select
            required
            value={form.data.target}
            onChange={(e) => form.setData("target", e.target.value)}
          >
            {targets.map((target) => (
              <option key={target.key} value={target.key}>
                {target.title}
              </option>
            ))}
          </select>
        </label>
        {targets.length === 0 && (
          <p className="lp-info">{t("learning.teaching.no_targets")}</p>
        )}
        <label className="lp-field">
          <span>{t("learning.teaching.task_title")}</span>
          <input
            required
            maxLength={255}
            value={form.data.title}
            onChange={(e) => form.setData("title", e.target.value)}
          />
        </label>
        <label className="lp-field">
          <span>{t("learning.teaching.instructions")}</span>
          <textarea
            rows={5}
            maxLength={5000}
            value={form.data.instructions}
            onChange={(e) => form.setData("instructions", e.target.value)}
          />
        </label>
        <div className="lp-two-fields">
          <label className="lp-field">
            <span>{t("learning.teaching.deadline")}</span>
            <input
              required
              type="datetime-local"
              dir="ltr"
              value={form.data.due_local}
              onChange={(e) => form.setData("due_local", e.target.value)}
            />
            <small>{timezone}</small>
          </label>
          <label className="lp-field">
            <span>{t("learning.teaching.max_score")}</span>
            <input
              required
              type="number"
              min={1}
              max={1000}
              value={form.data.max_score}
              onChange={(e) => form.setData("max_score", e.target.value)}
            />
          </label>
        </div>
        <label className="lp-remember">
          <input
            type="checkbox"
            checked={form.data.allows_late}
            onChange={(e) => form.setData("allows_late", e.target.checked)}
          />
          {t("learning.teaching.allow_late")}
        </label>
        {form.data.allows_late && (
          <label className="lp-field">
            <span>{t("learning.teaching.late_penalty")}</span>
            <input
              type="number"
              required
              min={0}
              max={100}
              value={form.data.late_penalty_percent}
              onChange={(e) =>
                form.setData("late_penalty_percent", e.target.value)
              }
            />
          </label>
        )}
        <button
          className="lp-btn"
          type="submit"
          disabled={form.processing || targets.length === 0}
        >
          {t(form.processing ? "learning.saving" : "learning.teaching.create")}
        </button>
      </form>
    </dialog>
  );
}
function ReviewTask({
  task,
  timezone,
  canGrade,
  onClose,
  onSaved,
}: {
  task: Task;
  timezone: string;
  canGrade: boolean;
  onClose: () => void;
  onSaved: () => void;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    return () => element?.close();
  }, []);
  return (
    <dialog
      ref={dialog}
      className="lp-sheet lp-live-drawer"
      dir="rtl"
      aria-labelledby="review-task-title"
      onClose={onClose}
    >
      <header className="lp-drawer-head">
        <div>
          <h2 id="review-task-title">{task.title.ar}</h2>
          <p>
            {date(task.due_at, "ar", timezone)} ·{" "}
            {t("learning.teaching.max_score")} {task.max_score}
          </p>
        </div>
        <button
          className="lp-close"
          aria-label={t("learning.close")}
          onClick={onClose}
        >
          <Icon name="close" />
        </button>
      </header>
      <div className="lp-sheet-body">
        {task.instructions?.ar && (
          <div className="lp-reading lp-preserve-text">
            {task.instructions.ar}
          </div>
        )}
        <h3>{t("learning.teaching.student_work")}</h3>
        {task.submissions?.length ? (
          task.submissions.map((submission) => (
            <Grade
              key={submission.id + submission.status}
              submission={submission}
              maxScore={task.max_score}
              canGrade={canGrade}
              onSaved={onSaved}
            />
          ))
        ) : (
          <Empty>{t("learning.teaching.no_submissions")}</Empty>
        )}
      </div>
    </dialog>
  );
}
function Grade({
  submission,
  maxScore,
  canGrade,
  onSaved,
}: {
  submission: Submission;
  maxScore: number;
  canGrade: boolean;
  onSaved: () => void;
}) {
  const t = useI18n();
  const [score, setScore] = useState(
    submission.score === null
      ? ""
      : String(submission.raw_score ?? submission.score),
  );
  const [feedback, setFeedback] = useState(submission.feedback ?? "");
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      await axios.post(`/api/assignment-submissions/${submission.id}/grade`, {
        score: Number(score),
        feedback,
        reason: t("learning.teaching.grade_audit"),
      });
      onSaved();
    } catch (error) {
      setErrors(errorText(error));
    } finally {
      setBusy(false);
    }
  }
  return (
    <article className="lp-grade-card">
      <div className="lp-section-title">
        {submission.profile_url ? (
          <ServiceLink
            className="lp-student-name"
            href={submission.profile_url}
          >
            {submission.student_name}
          </ServiceLink>
        ) : (
          <h3>{submission.student_name}</h3>
        )}
        <span className="lp-tag">{submission.status_label}</span>
      </div>
      {submission.content && (
        <p className="lp-reading lp-preserve-text">{submission.content}</p>
      )}
      {submission.attachments
        .filter((href) => /^https?:\/\//i.test(href))
        .map((href, index) => (
          <ServiceLink key={href} className="lp-text-action" href={href}>
            {t("learning.teaching.attachment")} {index + 1}
          </ServiceLink>
        ))}
      {submission.status === "graded" ? (
        <div className="lp-feedback">
          <b>
            {t("learning.teaching.score")} {submission.score} / {maxScore}
          </b>
          <p>{submission.feedback}</p>
        </div>
      ) : canGrade && ["submitted", "late"].includes(submission.status) ? (
        <form className="lp-form" onSubmit={(event) => void submit(event)}>
          <Errors errors={errors} />
          <label className="lp-field">
            <span>
              {t("learning.teaching.score")} / {maxScore}
            </span>
            <input
              type="number"
              min={0}
              max={maxScore}
              required
              value={score}
              onChange={(e) => setScore(e.target.value)}
            />
          </label>
          <label className="lp-field">
            <span>{t("learning.workspace.feedback")}</span>
            <textarea
              rows={3}
              maxLength={5000}
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
            />
          </label>
          <button className="lp-btn" type="submit" disabled={busy}>
            {t(busy ? "learning.saving" : "learning.teaching.save_grade")}
          </button>
        </form>
      ) : (
        <p className="lp-help">{t("learning.teaching.waiting_submission")}</p>
      )}
    </article>
  );
}
