import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";
import { Empty, Errors } from "./shared";

export interface WeeklySlot {
  weekday: number;
  start_time: string;
}

export interface PendingApproval {
  studentLabel: string;
  status: string;
  statusLabel: string;
}

export interface PendingRequest {
  id: string;
  status: string;
  statusLabel: string;
  proposedSummary: string;
  reason: string;
  expiresAt: string;
  acceptedCount: number;
  totalCount: number;
  approvals: PendingApproval[];
}

export interface TeacherSchedule {
  id: string;
  courseLabel: string;
  targetLabel: string;
  individual: boolean;
  timezone: string;
  durationMinutes: number;
  intervalWeeks: number;
  slots: WeeklySlot[];
  currentSummary: string;
  pendingRequest: PendingRequest | null;
}

export interface StudentChangeRequest {
  id: string;
  courseLabel: string;
  teacherLabel: string;
  timezone: string;
  currentSummary: string;
  proposedSummary: string;
  reason: string;
  expiresAt: string;
}

const WEEKDAYS = [0, 1, 2, 3, 4, 5, 6];

function dayName(t: (key: string) => string, weekday: number): string {
  return t(`learning.schedule_change.days.${weekday}`);
}

export function TeacherScheduleChange({
  schedules,
}: {
  schedules: TeacherSchedule[];
}) {
  const t = useI18n();

  return (
    <section className="lp-section">
      <div className="lp-section-head">
        <div>
          <h2>{t("learning.schedule_change.title")}</h2>
          <p>{t("learning.schedule_change.teacher_intro")}</p>
        </div>
      </div>
      {schedules.length ? (
        <div className="lp-request-list">
          {schedules.map((schedule) => (
            <ScheduleCard key={schedule.id} schedule={schedule} />
          ))}
        </div>
      ) : (
        <Empty>{t("learning.schedule_change.empty_teacher")}</Empty>
      )}
    </section>
  );
}

function ScheduleCard({ schedule }: { schedule: TeacherSchedule }) {
  const t = useI18n();
  const [open, setOpen] = useState(false);
  const form = useForm<{ slots: WeeklySlot[]; reason: string }>({
    slots: schedule.slots.map((slot) => ({ ...slot })),
    reason: "",
  });
  const withdraw = useForm({});
  const pending = schedule.pendingRequest;

  const setSlot = (index: number, patch: Partial<WeeklySlot>) =>
    form.setData(
      "slots",
      form.data.slots.map((slot, current) =>
        current === index ? { ...slot, ...patch } : slot,
      ),
    );

  return (
    <article className="lp-report-card lp-request-card">
      <div className="lp-section-head">
        <div>
          <h3>{schedule.courseLabel}</h3>
          <p>
            {schedule.targetLabel} · {schedule.timezone}
          </p>
        </div>
      </div>
      <p>
        <b>{t("learning.schedule_change.current")}</b>: {schedule.currentSummary}
      </p>
      {pending ? (
        <div className="lp-request-response">
          <span className="lp-tag">{pending.statusLabel}</span>
          <p>
            <b>{t("learning.schedule_change.proposed")}</b>:{" "}
            {pending.proposedSummary}
          </p>
          <p>
            {t("learning.schedule_change.awaiting")}: {pending.acceptedCount} /{" "}
            {pending.totalCount}
          </p>
          <ul className="lp-approval-list">
            {pending.approvals.map((approval) => (
              <li key={`${pending.id}-${approval.studentLabel}`}>
                {approval.studentLabel} — {approval.statusLabel}
              </li>
            ))}
          </ul>
          <Errors errors={withdraw.errors} />
          <button
            type="button"
            className="lp-btn lp-btn-secondary"
            disabled={withdraw.processing}
            onClick={() =>
              withdraw.post(
                `/learn/teacher/schedule-changes/${pending.id}/withdraw`,
                { preserveScroll: true },
              )
            }
          >
            {t("learning.schedule_change.withdraw")}
          </button>
        </div>
      ) : (
        <>
          <button
            type="button"
            className="lp-btn lp-btn-secondary"
            onClick={() => setOpen(!open)}
          >
            {t("learning.schedule_change.propose")}
          </button>
          {open && (
            <form
              className="lp-form lp-auth-form lp-request-response"
              onSubmit={(event) => {
                event.preventDefault();
                form.post(
                  `/learn/teacher/schedules/${schedule.id}/change-requests`,
                  {
                    preserveScroll: true,
                    onSuccess: () => {
                      form.reset();
                      setOpen(false);
                    },
                  },
                );
              }}
            >
              <Errors errors={form.errors} />
              <p className="lp-muted">{t("learning.schedule_change.rule")}</p>
              {form.data.slots.map((slot, index) => (
                <div className="lp-slot-row" key={`slot-${index}`}>
                  <label className="lp-field">
                    <span>{t("learning.schedule_change.day")}</span>
                    <select
                      value={slot.weekday}
                      onChange={(event) =>
                        setSlot(index, { weekday: Number(event.target.value) })
                      }
                    >
                      {WEEKDAYS.map((weekday) => (
                        <option key={weekday} value={weekday}>
                          {dayName(t, weekday)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="lp-field">
                    <span>{t("learning.schedule_change.time")}</span>
                    <input
                      type="time"
                      value={slot.start_time}
                      onChange={(event) =>
                        setSlot(index, { start_time: event.target.value })
                      }
                    />
                  </label>
                  {form.data.slots.length > 1 && (
                    <button
                      type="button"
                      className="lp-text-action"
                      onClick={() =>
                        form.setData(
                          "slots",
                          form.data.slots.filter(
                            (_, current) => current !== index,
                          ),
                        )
                      }
                    >
                      {t("learning.schedule_change.remove_day")}
                    </button>
                  )}
                </div>
              ))}
              {form.data.slots.length < 7 && (
                <button
                  type="button"
                  className="lp-text-action"
                  onClick={() =>
                    form.setData("slots", [
                      ...form.data.slots,
                      {
                        weekday: 0,
                        start_time:
                          form.data.slots[0]?.start_time ?? "17:00",
                      },
                    ])
                  }
                >
                  {t("learning.schedule_change.add_day")}
                </button>
              )}
              <label className="lp-field">
                <span>{t("learning.schedule_change.reason")}</span>
                <textarea
                  value={form.data.reason}
                  onChange={(event) => form.setData("reason", event.target.value)}
                  required
                />
              </label>
              <button className="lp-btn" disabled={form.processing}>
                {t("learning.schedule_change.submit")}
              </button>
            </form>
          )}
        </>
      )}
    </article>
  );
}

export function StudentScheduleChangeRequests({
  requests,
}: {
  requests: StudentChangeRequest[];
}) {
  const t = useI18n();

  if (!requests.length) {
    return null;
  }

  return (
    <section className="lp-section">
      <div className="lp-section-head">
        <div>
          <h2>{t("learning.schedule_change.title")}</h2>
          <p>{t("learning.schedule_change.student_intro")}</p>
        </div>
      </div>
      <div className="lp-request-list">
        {requests.map((request) => (
          <StudentRequestCard key={request.id} request={request} />
        ))}
      </div>
    </section>
  );
}

function StudentRequestCard({ request }: { request: StudentChangeRequest }) {
  const t = useI18n();
  const form = useForm<{ decision: string; note: string }>({
    decision: "accept",
    note: "",
  });

  const respond = (decision: "accept" | "reject") => {
    form.transform((data) => ({ ...data, decision }));
    form.post(`/learn/student/schedule-changes/${request.id}/respond`, {
      preserveScroll: true,
    });
  };

  return (
    <article className="lp-report-card lp-request-card">
      <div className="lp-section-head">
        <div>
          <h3>{request.courseLabel}</h3>
          <p>
            {request.teacherLabel} · {request.timezone}
          </p>
        </div>
      </div>
      <p>
        <b>{t("learning.schedule_change.current")}</b>: {request.currentSummary}
      </p>
      <p>
        <b>{t("learning.schedule_change.proposed")}</b>:{" "}
        {request.proposedSummary}
      </p>
      <p>{request.reason}</p>
      <p className="lp-muted">{t("learning.schedule_change.rule")}</p>
      <Errors errors={form.errors} />
      <label className="lp-field">
        <span>{t("learning.schedule_change.note")}</span>
        <textarea
          value={form.data.note}
          onChange={(event) => form.setData("note", event.target.value)}
        />
      </label>
      <div className="lp-inline-actions">
        <button
          type="button"
          className="lp-btn"
          disabled={form.processing}
          onClick={() => respond("accept")}
        >
          {t("learning.schedule_change.accept")}
        </button>
        <button
          type="button"
          className="lp-btn lp-btn-secondary"
          disabled={form.processing}
          onClick={() => respond("reject")}
        >
          {t("learning.schedule_change.reject")}
        </button>
      </div>
    </article>
  );
}
