import BrandLogo from "@/Components/BrandLogo";
import { Head, Link, usePage } from "@inertiajs/react";
import type { PropsWithChildren } from "react";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
import Icon from "@/Pages/Learning/Icon";
import "../../css/learning.css";
import "../../css/console-filters.css";
export type LearningKind = "student" | "teacher";
export default function LearningLayout({
  kind,
  title,
  children,
}: PropsWithChildren<{ kind: LearningKind; title: string }>) {
  const t = useI18n();
  const { auth, flash } = usePage<AppPageProps>().props;
  const home = `/learn/${kind}`;
  const current = usePage().url.split("?")[0];
  return (
    <div className="learning-app learning-portal" dir="rtl" lang="ar">
      <Head title={title} />
      <a className="learning-skip" href="#learning-main">
        {t("learning.skip")}
      </a>
      <header className="lp-header">
        <div className="lp-header-inner">
          <Link
            href={home}
            className="lp-brand"
            aria-label={t("learning.brand")}
          >
            <span className="lp-logo">
              <BrandLogo label={t("learning.brand")} />
            </span>
          </Link>
          <span className="lp-portal-name">{t(`learning.${kind}_portal`)}</span>
          <div className="lp-header-actions">
            <Link href={`${home}/profile`} className="lp-profile-button">
              <span className="lp-avatar">
                {auth.user?.name
                  ?.trim()
                  .split(/\s+/)
                  .slice(0, 2)
                  .map((part) => part.slice(0, 1))
                  .join(" ")}
              </span>
              <span>
                {auth.user?.name}
                <small>{t("learning.my_profile")}</small>
              </span>
              <Icon name="arrow" size={16} />
            </Link>
            <Link href="/learn/entry" className="lp-back">
              {t("learning.entry.kicker")}
            </Link>
            <Link href="/logout" method="post" as="button" className="lp-back">
              {t("learning.logout")}
            </Link>
          </div>
        </div>
      </header>
      <nav className="lp-section-nav" aria-label={t("learning.navigation")}>
        <div>
          <Link
            href={home}
            aria-current={current === home ? "page" : undefined}
          >
            {t("learning.workspace.home")}
          </Link>
          <Link href={`${home}#schedule`}>{t("learning.schedule")}</Link>
          <Link href={`${home}#studies`}>
            {t(
              kind === "teacher"
                ? "learning.workspace.teacher_studies"
                : "learning.my_studies",
            )}
          </Link>
          <Link href={`${home}#tasks`}>
            {t(
              kind === "teacher"
                ? "learning.workspace.teacher_tasks"
                : "learning.workspace.student_tasks",
            )}
          </Link>
          <Link href={`${home}#progress`}>
            {t(
              kind === "teacher"
                ? "learning.workspace.professional"
                : "learning.workspace.progress",
            )}
          </Link>
          <Link href={`${home}#notices`}>{t("learning.notifications")}</Link>
        </div>
      </nav>
      <main className="learning-main lp-main" id="learning-main">
        {flash?.success && (
          <div className="learning-flash" role="status">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="learning-error" role="alert">
            {flash.error}
          </div>
        )}
        {children}
      </main>
      <footer className="learning-footer">
        <span>{t("learning.brand")}</span>
        <span>{t("learning.footer")}</span>
      </footer>
    </div>
  );
}
