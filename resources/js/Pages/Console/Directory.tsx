import { Link } from "@inertiajs/react";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";

type Item = { key: string; label: string; href: string; legacy: boolean };
export default function Directory({
  sections,
  search: initialSearch,
}: {
  sections: { key: string; title: string; items: Item[] }[];
  search: string;
}) {
  const t = useI18n();
  const [search, setSearch] = useState(initialSearch);
  const matched = sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) =>
        (section.title + " " + item.label).includes(search.trim()),
      ),
    }))
    .filter((section) => section.items.length);
  return (
    <ConsoleLayout
      section="records"
      title={t("console_directory.title")}
      description={t("console_directory.description")}
    >
      <div className="console-panel">
        <div className="console-filters console-filter-bar console-filter-bar--short">
          <div className="console-field" style={{ flex: 1 }}>
            <label htmlFor="directory-search">
              {t("console_directory.search")}
            </label>
            <input
              type="search"
              id="directory-search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t("console_directory.search_placeholder")}
            />
          </div>
          <span className="console-status">
            {formatNumber(
              matched.reduce(
                (count, section) => count + section.items.length,
                0,
              ),
            )}{" "}
            {t("console_directory.results")}
          </span>
          {search && (
            <button className="console-button" onClick={() => setSearch("")}>
              {t("console_directory.clear")}
            </button>
          )}
        </div>
      </div>
      {!matched.length && (
        <div className="console-empty">{t("console.no_results")}</div>
      )}
      <div className="console-directory-grid">
        {matched.map((section) => (
          <section
            key={section.key}
            className={
              "console-panel console-directory-section " +
              (section.key === "specialized"
                ? "console-directory-specialized"
                : "")
            }
          >
            <div className="console-panel-header">
              <h2>{section.title}</h2>
              <ConsoleIcon
                name={
                  section.key === "quran"
                    ? "quran"
                    : section.key === "settings"
                      ? "settings"
                      : "directory"
                }
              />
            </div>
            {section.key === "specialized" && (
              <p className="console-settings-note">
                {t("console_directory.legacy_note")}
              </p>
            )}
            <div
              className={
                section.key === "specialized"
                  ? "console-directory-legacy-grid"
                  : ""
              }
            >
              {section.items.map((item) =>
                item.legacy ? (
                  <a
                    key={item.key}
                    href={item.href}
                    className="console-directory-item"
                  >
                    <span>
                      {item.label}
                      <small>{t("console_directory.legacy")}</small>
                    </span>
                    <b aria-hidden="true">←</b>
                  </a>
                ) : (
                  <Link
                    key={item.key}
                    href={item.href}
                    className="console-directory-item"
                  >
                    <span>{item.label}</span>
                    <b aria-hidden="true">←</b>
                  </Link>
                ),
              )}
            </div>
          </section>
        ))}
      </div>
    </ConsoleLayout>
  );
}
