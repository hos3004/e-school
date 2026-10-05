import { Link, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";
import LearningLayout from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps, Session } from "@/types";
import { date, Empty, Errors } from "./shared";
import { RequestFields } from "./SessionRequests";
interface Item {
  id: string;
  session: Session;
  requestedBy: { name: string };
  reason: string;
  requestedStartAt: string;
  status: string;
  approveUrl?: string | null;
  rejectUrl?: string | null;
  proposeAlternativeUrl?: string | null;
}
export default function Postponements({
  requests,
  timezone,
}: {
  requests: Item[];
  timezone: string;
}) {
  const t = useI18n();
  return (
    <LearningLayout kind="teacher" title={t("learning.requests.title")}>
      <div className="lp-page-heading">
        <div>
          <h1>{t("learning.requests.title")}</h1>
          <p>{t("learning.requests.intro")}</p>
        </div>
      </div>
      {requests.length ? (
        <div className="lp-request-list">
          {requests.map((item) => (
            <Request key={item.id} item={item} timezone={timezone} />
          ))}
        </div>
      ) : (
        <Empty>{t("learning.requests.empty")}</Empty>
      )}
    </LearningLayout>
  );
}
function Request({ item, timezone }: { item: Item; timezone: string }) {
  const t = useI18n();
  const user = usePage<AppPageProps>().props.auth.user?.id ?? "";
  const [mode, setMode] = useState<"reject" | "propose" | null>(null);
  const form = useForm(`learning-response-${user}-${item.id}`, {
    category: "schedule",
    note: "",
    proposed_local: "",
  });
  const approve = useForm({});
  return (
    <article className="lp-report-card lp-request-card">
      <div className="lp-section-head">
        <div>
          <span className="lp-tag">
            {t(`learning.requests.statuses.${item.status}`)}
          </span>
          <h2>{item.requestedBy.name}</h2>
          <p>
            {item.session.title} · {date(item.session.startsAt, "ar", timezone)}{" "}
            {date(item.session.startsAt, "ar", timezone, true)}
          </p>
        </div>
        <Link
          href={`/learn/teacher/sessions/${item.session.id}`}
          className="lp-text-action"
        >
          {t("learning.requests.view_lesson")}
        </Link>
      </div>
      <p>{item.reason}</p>
      <p>
        <b>{t("learning.requests.proposed")}</b>:{" "}
        {date(item.requestedStartAt, "ar", timezone)} ·{" "}
        {date(item.requestedStartAt, "ar", timezone, true)}
      </p>
      <Errors errors={approve.errors} />
      <div className="lp-inline-actions">
        {item.approveUrl && (
          <button
            className="lp-btn"
            disabled={approve.processing || form.processing}
            onClick={() =>
              approve.post(item.approveUrl!, { preserveScroll: true })
            }
          >
            {t("learning.requests.approve")}
          </button>
        )}
        {item.proposeAlternativeUrl && (
          <button
            className="lp-btn lp-btn-secondary"
            onClick={() => setMode(mode === "propose" ? null : "propose")}
          >
            {t("learning.requests.alternative")}
          </button>
        )}
        {item.rejectUrl && (
          <button
            className="lp-btn lp-btn-secondary"
            onClick={() => setMode(mode === "reject" ? null : "reject")}
          >
            {t("learning.requests.reject")}
          </button>
        )}
      </div>
      {!item.approveUrl && !item.rejectUrl && !item.proposeAlternativeUrl && (
        <p className="lp-muted">{t("learning.requests.awaiting_admin")}</p>
      )}
      {mode && (
        <form
          className="lp-form lp-auth-form lp-request-response"
          onSubmit={(e) => {
            e.preventDefault();
            const url =
              mode === "reject" ? item.rejectUrl : item.proposeAlternativeUrl;
            if (url)
              form.post(url, {
                preserveScroll: true,
                onSuccess: () => {
                  form.reset();
                  setMode(null);
                },
              });
          }}
        >
          <Errors errors={form.errors} />
          <RequestFields
            timezone={timezone}
            data={form.data}
            setData={form.setData}
            withDate={mode === "propose"}
          />
          <button
            className="lp-btn"
            disabled={form.processing || approve.processing}
          >
            {t(
              mode === "reject"
                ? "learning.requests.reject"
                : "learning.requests.alternative",
            )}
          </button>
        </form>
      )}
    </article>
  );
}
