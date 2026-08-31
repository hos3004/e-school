import { Head, Link } from "@inertiajs/react";
import { useCallback, useEffect, useMemo, useState } from "react";

import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import AppLayout, { type AppRole } from "@/Layouts/AppLayout";
import { formatDateTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";

interface NotificationItem {
  id: string;
  subject: string;
  body: string;
  category: string;
  category_label: string;
  read_at: string | null;
  created_at: string | null;
  target_url: string | null;
}

interface NotificationListResponse {
  data?: NotificationItem[];
  links?: { next?: string | null };
}

interface Props {
  role?: AppRole;
}

type Filter = "all" | "unread";

const csrfToken = () =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
  "";

export default function Notifications({ role }: Props) {
  const t = useI18n();
  const locale = useLocale();
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [filter, setFilter] = useState<Filter>("all");
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isMarkingAll, setIsMarkingAll] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [nextPage, setNextPage] = useState<string | null>(null);

  const requestPage = useCallback(async (url: string, append: boolean) => {
    if (append) {
      setIsLoadingMore(true);
    } else {
      setIsLoading(true);
    }
    setHasError(false);

    try {
      const response = await fetch(url, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) throw new Error("notification-list-failed");

      const payload = (await response.json()) as NotificationListResponse;
      const incoming = Array.isArray(payload.data) ? payload.data : [];
      setItems((current) => (append ? [...current, ...incoming] : incoming));
      setNextPage(payload.links?.next ?? null);
    } catch {
      setHasError(true);
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, []);

  useEffect(() => {
    void requestPage("/api/notifications", false);
  }, [requestPage]);

  const visibleItems = useMemo(
    () =>
      items.filter(
        (notification) => filter === "all" || notification.read_at === null,
      ),
    [filter, items],
  );
  const unreadCount = items.filter(
    (notification) => notification.read_at === null,
  ).length;

  const markAsRead = async (id: string) => {
    const item = items.find((notification) => notification.id === id);
    if (item?.read_at) return;

    const response = await fetch(`/api/notifications/${id}/mark-as-read`, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    if (!response.ok) return;

    const readAt = new Date().toISOString();
    setItems((current) =>
      current.map((notification) =>
        notification.id === id
          ? { ...notification, read_at: readAt }
          : notification,
      ),
    );
  };

  const markAllAsRead = async () => {
    if (unreadCount === 0) return;
    setIsMarkingAll(true);

    try {
      const response = await fetch("/api/notifications/mark-all-as-read", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
      });
      if (!response.ok) return;

      const readAt = new Date().toISOString();
      setItems((current) =>
        current.map((notification) => ({
          ...notification,
          read_at: notification.read_at ?? readAt,
        })),
      );
    } finally {
      setIsMarkingAll(false);
    }
  };

  return (
    <AppLayout role={role}>
      <Head title={t("notifications.title")} />
      <PageHeader
        className="mb-6"
        title={t("notifications.title")}
        subtitle={t("notifications.subtitle")}
      />

      <section className="mb-5 flex flex-wrap items-center gap-2 rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-raised)] p-3 shadow-[var(--shadow-card)]">
        {(["all", "unread"] as const).map((value) => (
          <button
            aria-pressed={filter === value}
            className={
              filter === value
                ? "min-h-10 rounded-[var(--radius-md)] bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-[var(--ink-inverse)]"
                : "min-h-10 rounded-[var(--radius-md)] px-4 py-2 text-sm font-semibold text-[var(--ink-muted)] hover:bg-[var(--surface-muted)]"
            }
            key={value}
            onClick={() => setFilter(value)}
            type="button"
          >
            {t(`notifications.${value}`)}
            {value === "unread" && unreadCount > 0 ? ` (${unreadCount})` : ""}
          </button>
        ))}
        <button
          className="ms-auto min-h-10 rounded-[var(--radius-md)] border border-[var(--line-strong)] px-4 py-2 text-sm font-semibold text-[var(--brand-strong)] hover:bg-[var(--brand-soft)] disabled:cursor-not-allowed disabled:opacity-50"
          disabled={isMarkingAll || unreadCount === 0}
          onClick={() => void markAllAsRead()}
          type="button"
        >
          {isMarkingAll
            ? t("notifications.saving")
            : t("notifications.mark_all")}
        </button>
      </section>

      {isLoading ? (
        <LoadingState label={t("notifications.loading")} rows={5} />
      ) : hasError && items.length === 0 ? (
        <ErrorState
          message={t("notifications.error")}
          onRetry={() => void requestPage("/api/notifications", false)}
        />
      ) : visibleItems.length === 0 ? (
        <EmptyState
          title={t("notifications.empty")}
          description={t("notifications.empty_description")}
        />
      ) : (
        <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-raised)] shadow-[var(--shadow-card)]">
          <div className="divide-y divide-[var(--line)]">
            {visibleItems.map((notification) => (
              <article
                className={
                  notification.read_at ? "p-5" : "bg-[var(--brand-soft)]/45 p-5"
                }
                key={notification.id}
              >
                <div className="flex items-start gap-3">
                  {!notification.read_at && (
                    <span
                      aria-hidden="true"
                      className="mt-2 size-2.5 shrink-0 rounded-full bg-[var(--brand)]"
                    />
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h2 className="font-semibold text-[var(--ink)]">
                          {notification.subject}
                        </h2>
                        {notification.body && (
                          <p className="mt-1 text-sm leading-6 text-[var(--ink-muted)]">
                            {notification.body}
                          </p>
                        )}
                      </div>
                      <span className="rounded-full bg-[var(--surface)] px-2.5 py-1 text-xs text-[var(--ink-muted)]">
                        {notification.category_label}
                      </span>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-4 text-xs">
                      {!notification.read_at && (
                        <button
                          className="font-semibold text-[var(--brand)]"
                          onClick={() => void markAsRead(notification.id)}
                          type="button"
                        >
                          {t("notifications.mark_read")}
                        </button>
                      )}
                      {notification.target_url && (
                        <Link
                          className="font-semibold text-[var(--brand)]"
                          href={notification.target_url}
                          onClick={() => void markAsRead(notification.id)}
                        >
                          {t("notifications.open")}
                        </Link>
                      )}
                      {notification.created_at && (
                        <time
                          className="ms-auto text-[var(--ink-muted)]"
                          dateTime={notification.created_at}
                        >
                          {formatDateTime(notification.created_at, locale)}
                        </time>
                      )}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {nextPage && filter === "all" && (
            <button
              className="w-full border-t border-[var(--line)] px-4 py-3 text-sm font-semibold text-[var(--brand)] disabled:opacity-50"
              disabled={isLoadingMore}
              onClick={() => void requestPage(nextPage, true)}
              type="button"
            >
              {isLoadingMore
                ? t("notifications.loading")
                : t("notifications.load_more")}
            </button>
          )}
        </div>
      )}
    </AppLayout>
  );
}
