import axios from "axios";
import { Link } from "@inertiajs/react";
import { useCallback, useEffect, useState } from "react";
import { useI18n } from "@/lib/i18n";
import Icon from "./Icon";
import { date, Empty } from "./shared";
type Item = {
  id: string;
  subject: string;
  body: string;
  read_at: string | null;
  created_at: string | null;
  target_url: string | null;
};
function target(href: string | null, kind: string): string | null {
  if (!href) return null;
  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin) return null;
    let path = url.pathname;
    if (kind === "admin") return path + url.search + url.hash;
    if (
      /^\/(student|teacher)\/(schedule|profile|notifications|reports|earnings|availability|postponements)$/.test(
        path,
      ) ||
      /^\/(student|teacher)\/sessions\/[0-9a-hjkmnp-tv-z]{26}$/i.test(path) ||
      /^\/teacher\/students\/[0-9a-hjkmnp-tv-z]{26}$/i.test(path)
    )
      path = "/learn" + path;
    if (path === "/student/assignments") path = "/learn/student#tasks";
    if (path === "/teacher/assignments") path = "/learn/teacher/assignments";
    if (
      /^\/(student|teacher)\/(group|groups|students|programs)(\/.*)?$/.test(
        path,
      )
    )
      path = `/learn/${path.split("/")[1]}#studies`;
    if (path === "/student" || path === "/teacher") path = "/learn" + path;
    return path + url.search + url.hash;
  } catch {
    return null;
  }
}
export default function Notifications({
  timezone,
  kind,
  full = false,
}: {
  timezone: string;
  kind: string;
  full?: boolean;
}) {
  const t = useI18n();
  const [items, setItems] = useState<Item[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [next, setNext] = useState<string | null>(null);
  const [filter, setFilter] = useState("all");
  const load = useCallback(
    async (url = "/api/notifications", append = false) => {
      setLoading(true);
      setError(false);
      try {
        const response = await axios.get<{
          data: Item[];
          links: { next: string | null };
        }>(url);
        const incoming = full
          ? response.data.data
          : response.data.data.slice(0, 8);
        setItems((current) => (append ? [...current, ...incoming] : incoming));
        setNext(response.data.links.next);
      } catch {
        setError(true);
      } finally {
        setLoading(false);
      }
    },
    [full],
  );
  useEffect(() => {
    void load();
  }, [load]);
  async function read(id: string) {
    setBusy(id);
    setError(false);
    try {
      await axios.post(
        id === "all"
          ? "/api/notifications/mark-all-as-read"
          : `/api/notifications/${id}/mark-as-read`,
        {},
        { headers: { Accept: "application/json" } },
      );
      setItems((current) =>
        current.map((item) =>
          id === "all" || item.id === id
            ? { ...item, read_at: new Date().toISOString() }
            : item,
        ),
      );
    } catch {
      setError(true);
    } finally {
      setBusy(null);
    }
  }
  const visible = items.filter((item) => filter === "all" || !item.read_at);
  return (
    <>
      {full && (
        <div className="lp-notice-toolbar console-filter-pills-bar">
          <div
            className="lp-segmented"
            role="group"
            aria-label={t("learning.notifications")}
          >
            {["all", "unread"].map((value) => (
              <button
                key={value}
                aria-pressed={filter === value}
                onClick={() => setFilter(value)}
              >
                {t(`notifications.${value}`)}
              </button>
            ))}
          </div>
          <button
            className="lp-text-action"
            disabled={busy !== null || !items.some((item) => !item.read_at)}
            onClick={() => void read("all")}
          >
            {t(
              busy === "all"
                ? "notifications.saving"
                : "notifications.mark_all",
            )}
          </button>
        </div>
      )}
      <div className="lp-notices" aria-busy={loading}>
        {loading && items.length === 0 ? (
          <p className="learning-empty" role="status">
            {t("notifications.loading")}
          </p>
        ) : visible.length ? (
          visible.map((item) => (
            <article
              className={`lp-notice-row ${item.read_at ? "is-read" : ""}`}
              key={item.id}
            >
              <span className="lp-notice-dot" />
              <span>
                <b>{item.subject}</b>
                <small>{item.body}</small>
                {item.created_at && (
                  <small>{date(item.created_at, "ar", timezone)}</small>
                )}
                <span className="lp-notice-actions">
                  {!item.read_at && (
                    <button
                      type="button"
                      className="lp-text-action"
                      disabled={busy !== null}
                      onClick={() => void read(item.id)}
                    >
                      {t(
                        busy === item.id
                          ? "notifications.saving"
                          : "notifications.mark_read",
                      )}
                    </button>
                  )}
                  {target(item.target_url, kind) && (
                    <Link
                      className="lp-text-action"
                      href={target(item.target_url, kind) ?? ""}
                    >
                      {t("notifications.open")}
                      <Icon name="arrow" size={15} />
                    </Link>
                  )}
                </span>
              </span>
              <span className="lp-notice-read">
                {t(
                  item.read_at
                    ? "learning.workspace.read"
                    : "learning.workspace.new",
                )}
              </span>
            </article>
          ))
        ) : (
          !error && <Empty>{t("notifications.empty")}</Empty>
        )}
      </div>
      {error && (
        <div className="lp-feedback lp-error" role="alert">
          {t("notifications.error")}
          <button className="lp-text-action" onClick={() => void load()}>
            {t("learning.workspace.retry")}
          </button>
        </div>
      )}
      {full ? (
        next && (
          <button
            className="lp-text-action"
            disabled={loading}
            onClick={() => void load(next, true)}
          >
            {t(loading ? "notifications.loading" : "notifications.load_more")}
          </button>
        )
      ) : (
        <Link href={`/learn/${kind}/notifications`} className="lp-text-action">
          {t("learning.workspace.all_notices")}
        </Link>
      )}
    </>
  );
}
