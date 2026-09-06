import { useForm, usePage } from "@inertiajs/react";
import { useEffect, useRef, type FormEvent } from "react";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps, Assignment } from "@/types";
import Icon from "./Icon";
import { date, Errors } from "./shared";
export default function AssignmentDrawer({
  assignment,
  timezone,
  onClose,
}: {
  assignment: Assignment;
  timezone: string;
  onClose: () => void;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const userId = usePage<AppPageProps>().props.auth.user?.id;
  const form = useForm(`learning-assignment-${userId}-${assignment.id}`, {
    content: assignment.submissionContent ?? "",
  });
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    return () => element?.close();
  }, []);
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(`/learn/student/assignments/${assignment.id}/submit`, {
      preserveScroll: true,
      onSuccess: onClose,
    });
  }
  return (
    <dialog
      ref={dialog}
      className="lp-sheet lp-live-drawer"
      dir="rtl"
      aria-labelledby="assignment-title"
      onClose={onClose}
    >
      <header className="lp-drawer-head">
        <div>
          <h2 id="assignment-title">{assignment.title}</h2>
          <p>{assignment.courseName}</p>
        </div>
        <button
          className="lp-close"
          onClick={onClose}
          aria-label={t("learning.close")}
        >
          <Icon name="close" />
        </button>
      </header>
      <div className="lp-sheet-body">
        <span className="lp-tag">
          {t(`statuses.${assignment.submissionStatus}`)}
        </span>
        <p className="lp-help">
          {t("learning.workspace.due")} {date(assignment.dueAt, "ar", timezone)}{" "}
          · {date(assignment.dueAt, "ar", timezone, true)}
        </p>
        {assignment.instructions && (
          <div className="lp-reading lp-preserve-text">
            {assignment.instructions}
          </div>
        )}
        {assignment.feedback && (
          <div className="lp-info">
            <Icon name="file" />
            <div>
              <b>{t("learning.workspace.feedback")}</b>
              <p className="lp-preserve-text">{assignment.feedback}</p>
              {assignment.score !== null && assignment.score !== undefined && (
                <p dir="ltr">
                  {assignment.score} / {assignment.maxScore}
                </p>
              )}
            </div>
          </div>
        )}
        <form className="lp-form" onSubmit={submit}>
          <Errors errors={form.errors} />
          <label className="lp-field">
            <span>{t("learning.workspace.answer")}</span>
            <textarea
              value={form.data.content}
              onChange={(e) => form.setData("content", e.target.value)}
              maxLength={20000}
              rows={6}
              required
              disabled={!assignment.canSubmit || form.processing}
            />
          </label>
          {assignment.canSubmit ? (
            <button className="lp-btn" disabled={form.processing} type="submit">
              {t(
                form.processing
                  ? "learning.saving"
                  : "learning.workspace.submit_assignment",
              )}
            </button>
          ) : (
            <p className="lp-help">
              {t("learning.workspace.assignment_closed")}
            </p>
          )}
        </form>
      </div>
    </dialog>
  );
}
