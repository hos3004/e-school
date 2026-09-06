import { Head, Link } from "@inertiajs/react";
import { useEffect, type PropsWithChildren } from "react";
import { useI18n } from "@/lib/i18n";
import Icon from "@/Pages/Learning/Icon";
import "../../css/learning.css";

export default function LearningEntryLayout({
  title,
  children,
}: PropsWithChildren<{ title: string }>) {
  const t = useI18n();
  useEffect(() => {
    document.documentElement.lang = "ar";
    document.documentElement.dir = "rtl";
  }, []);
  return (
    <div className="learning-portal lp-entry" dir="rtl" lang="ar">
      <Head title={title} />
      <header className="lp-entry-header">
        <Link href="/" className="lp-brand">
          <span className="lp-logo">
            <img src="/images/academy-brand.png" alt={t("learning.brand")} />
          </span>
        </Link>
        <Link href="/" className="lp-back">
          {t("learning.entry.back")}
          <Icon name="arrow" size={17} />
        </Link>
      </header>
      <main className="lp-entry-main">
        <section className="lp-entry-story">
          <div className="lp-kicker">
            <span />
            {t("learning.entry.kicker")}
          </div>
          <h1>
            {t("learning.entry.headline_first")}
            <br />
            {t("learning.entry.headline_second")}
          </h1>
          <p>{t("learning.entry.intro")}</p>
          <div className="lp-entry-points">
            {["calendar", "book", "shield"].map((name) => (
              <div key={name}>
                <Icon name={name} />
                <span>
                  <b>{t(`learning.entry.${name}_title`)}</b>
                  <small>{t(`learning.entry.${name}_hint`)}</small>
                </span>
              </div>
            ))}
          </div>
          <div className="lp-entry-foot">
            <span>{t("learning.brand")}</span>
            {t("learning.entry.footer")}
          </div>
        </section>
        <section className="lp-entry-panel" aria-labelledby="entry-title">
          <div className="lp-entry-icon">
            <Icon name="teacher" size={27} />
          </div>
          {children}
          <div className="lp-entry-notice">
            <Icon name="lock" size={17} />
            <p>{t("learning.entry.privacy")}</p>
          </div>
        </section>
      </main>
    </div>
  );
}
