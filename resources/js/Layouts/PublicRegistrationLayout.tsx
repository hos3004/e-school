import BrandLogo from "@/Components/BrandLogo";
import { Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import { ArrowLeft } from "lucide-react";
import { useI18n } from "@/lib/i18n";
import "../../css/public-registration.css";

export default function PublicRegistrationLayout({
  children,
  sidebar,
}: {
  children: ReactNode;
  sidebar?: ReactNode;
}) {
  const t = useI18n();
  return (
    <div className="public-registration" dir="rtl" lang="ar">
      <a href="#registration-main" className="pr-skip">
        {t("public_registration.skip")}
      </a>
      <header className="pr-header">
        <div className="pr-header-inner">
          <Link
            href="/"
            className="pr-brand"
            aria-label={t("public_registration.brand")}
          >
            <BrandLogo label={t("public_registration.brand")} />
          </Link>
          <span className="pr-header-title">
            {t("public_registration.portal")}
          </span>
          <Link href="/login" className="pr-login">
            {t("auth.back_to_login")}
            <ArrowLeft size={16} />
          </Link>
        </div>
      </header>
      <main
        id="registration-main"
        className={sidebar ? "pr-main pr-with-summary" : "pr-main pr-centered"}
      >
        <div className="pr-content">{children}</div>
        {sidebar && <aside className="pr-sidebar">{sidebar}</aside>}
      </main>
      <footer className="pr-footer">
        <span>{t("public_registration.brand")}</span>
        <span>{t("public_registration.footer")}</span>
      </footer>
    </div>
  );
}
