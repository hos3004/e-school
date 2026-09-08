import BrandLogo from "@/Components/BrandLogo";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useState, type ReactNode } from "react";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
import "../../css/console.css";
import "../../css/console-filters.css";
import NotificationBell from "@/Components/NotificationBell";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";

type NavigationItem = {
  key: string;
  label: string;
  href: string;
  group: string;
  fullPage?: boolean;
};
type ConsoleProps = AppPageProps & {
  direction?: "rtl" | "ltr";
  console?: {
    navigation: NavigationItem[];
    school: { name: string; timezone: string };
    timezone: string;
    local: boolean;
  };
};
type Props = {
  title: string;
  description?: string;
  section: "before" | "during" | "after" | "records" | "settings";
  actions?: ReactNode;
  children: ReactNode;
  hidePageHeading?: boolean;
};

export default function ConsoleLayout({
  title,
  description,
  section,
  actions,
  children,
  hidePageHeading = false,
}: Props) {
  const t = useI18n();
  const { props, url } = usePage<ConsoleProps>();
  const [busy, setBusy] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const data = props.console;
  useEffect(() => {
    const start = router.on("start", () => setBusy(true));
    const finish = router.on("finish", () => setBusy(false));
    return () => {
      start();
      finish();
    };
  }, []);
  const links = data?.navigation ?? [];
  const phases = [
    {
      key: "before",
      n: "01",
      href:
        links.find((link) =>
          ["courses", "registration", "quran"].includes(link.key),
        )?.href ?? "/manage/courses",
    },
    { key: "during", n: "02", href: "/manage" },
    {
      key: "after",
      n: "03",
      href:
        links.find((link) => link.key === "reports")?.href ??
        links.find((link) => link.key === "teacher_dues")?.href ??
        "/manage/reports",
    },
  ];
  const isQuran = url.split("?")[0] === "/manage/quran";
  if (isQuran)
    phases.forEach((phase) => {
      phase.href = "/manage/quran?phase=" + phase.key;
    });
  const current = (href: string) => {
    const path = url.split("?")[0] ?? "/manage";
    if (href === "/manage") return path === href;
    return path.startsWith(href);
  };
  return (
    <div className="console-shell" dir="rtl" lang="ar">
      <Head title={title} />
      <a className="console-skip" href="#console-main">
        {t("console.skip")}
      </a>
      <aside
        className={"console-rail" + (menuOpen ? " is-open" : "")}
        aria-label={t("console.navigation")}
      >
        <Link
          href="/manage"
          className="console-brand"
          aria-label={t("console.brand")}
        >
          <span className="console-brand-crop">
            <BrandLogo label={t("console.brand")} />
          </span>
          <small className="console-brand-caption">
            {t("console.environment")}
          </small>
        </Link>
        {["work", "records", "tools"].map((group) => (
          <nav key={group} aria-label={t("console.nav_groups." + group)}>
            <p className="console-nav-caption">
              {t("console.nav_groups." + group)}
            </p>
            {links
              .filter((link) => link.group === group)
              .map((link) => {
                const NavigationLink = link.fullPage ? "a" : Link;
                return (
                  <NavigationLink
                    key={link.key}
                    href={link.href}
                    onClick={() => setMenuOpen(false)}
                    className={
                      "console-nav-link" +
                      (current(link.href) ? " is-current" : "")
                    }
                    aria-current={current(link.href) ? "page" : undefined}
                  >
                    <ConsoleIcon name={link.key} />
                    {link.label}
                  </NavigationLink>
                );
              })}
          </nav>
        ))}
        <div className="console-user">
          <span className="console-avatar" aria-hidden="true">
            {props.auth.user?.name.slice(0, 1)}
          </span>
          <div>
            <strong>{props.auth.user?.name}</strong>
            <small>{data?.school.name ?? t("console.brand")}</small>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            className="console-logout"
          >
            {t("console.logout")}
          </Link>
        </div>
      </aside>
      <div className="console-workspace">
        <header className="console-topbar">
          <Link
            href="/manage"
            className="console-mobile-brand"
            aria-label={t("console.brand")}
          >
            <span className="console-brand-crop">
              <BrandLogo label={t("console.brand")} />
            </span>
          </Link>
          <button
            type="button"
            className="console-menu-button"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen(!menuOpen)}
          >
            {t("console.navigation")}
          </button>
          <nav className="console-phases" aria-label={t("console.phases")}>
            {phases.map((phase) => {
              const available =
                isQuran ||
                phase.key === "during" ||
                links.some((link) => link.href === phase.href);
              return available ? (
                <Link
                  key={phase.key}
                  href={phase.href}
                  className={section === phase.key ? "is-current" : ""}
                  aria-current={section === phase.key ? "page" : undefined}
                >
                  <span className="console-phase-number" dir="ltr">
                    {phase.n}
                  </span>
                  <span>
                    <small>{t("console.phase_short." + phase.key)}</small>
                    <strong>{t("console.phase." + phase.key)}</strong>
                  </span>
                </Link>
              ) : null;
            })}
          </nav>
          <div className="console-header-tools">
            <form
              method="get"
              action="/manage/directory"
              className="console-top-search"
              role="search"
            >
              <label className="sr-only" htmlFor="console-task-search">
                {t("console_directory.search")}
              </label>
              <input
                id="console-task-search"
                type="search"
                name="search"
                maxLength={120}
                placeholder={t("console_directory.search")}
              />
              <button type="submit" aria-label={t("console.search")}>
                <ConsoleIcon name="directory" />
              </button>
            </form>
            <NotificationBell notificationsUrl="/manage/notifications" />
          </div>
        </header>
        <div className="console-context">
          <span>
            {t(
              data?.local ? "console.environment_local" : "console.environment",
            )}{" "}
          </span>
          <span>
            {t("console.timezone")} <bdi>{data?.timezone ?? "UTC"}</bdi>
          </span>
        </div>
        {busy && (
          <div className="console-progress" role="status">
            <span className="sr-only">{t("console.loading")}</span>
          </div>
        )}
        <main
          id="console-main"
          className={
            "console-main" + (hidePageHeading ? " console-profile-main" : "")
          }
          tabIndex={-1}
          aria-busy={busy}
        >
          {!hidePageHeading && (
            <div className="console-pagehead">
              <div>
                <p className="console-eyebrow">
                  {t("console.phase." + section)}
                </p>
                <h1>{title}</h1>
                {description && <p>{description}</p>}
              </div>
              {actions && <div className="console-actions">{actions}</div>}
            </div>
          )}
          {props.flash?.success && (
            <div className="console-feedback is-success" role="status">
              {props.flash.success}
            </div>
          )}
          {props.flash?.error && (
            <div className="console-feedback is-error" role="alert">
              {props.flash.error}
            </div>
          )}
          {children}
        </main>
      </div>
    </div>
  );
}
