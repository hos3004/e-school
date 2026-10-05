import { Head, Link, router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { ArrowLeft, Plus } from "lucide-react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import {
  fieldClass,
  primaryClass,
  secondaryClass,
  type PersonKind,
} from "@/Components/Console/PeopleFields";
import { useI18n } from "@/lib/i18n";
import "../../../../css/console-people.css";
type Person = {
  id: string;
  code: string;
  name: string;
  username: string | null;
  phone: string | null;
  status: string;
  timezone: string | null;
  study: string;
  show_url: string;
  edit_url: string | null;
};
type Props = {
  kind: PersonKind;
  people: {
    data: Person[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: { search: string; archived: string };
  indexUrl: string;
  createUrl: string | null;
};
export default function PeopleIndex({
  kind,
  people,
  filters,
  indexUrl,
  createUrl,
}: Props) {
  const t = useI18n();
  const [search, setSearch] = useState(filters.search);
  const [archived, setArchived] = useState(filters.archived);
  function submit(event: FormEvent) {
    event.preventDefault();
    router.get(
      indexUrl,
      { search, archived },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }
  const context = new URLSearchParams({
    search: filters.search,
    archived: filters.archived,
    page: String(people.current_page),
  }).toString();
  return (
    <ConsoleLayout
      title={t("console_people." + kind)}
      section="records"
      description={t("console_people.directory_description")}
      actions={
        createUrl && (
          <Link href={createUrl} className={primaryClass}>
            <Plus size={16} />
            {t("console_people.add_" + kind)}
          </Link>
        )
      }
    >
      <Head title={t("console_people." + kind)} />
      <section className="panel console-people-directory">
        <div className="panel-head">
          <div>
            <h2>{t("console_people.directory")}</h2>
            <p>{t("console_people.directory_description")}</p>
          </div>
          <span className="cell-sub">
            <bdi>{people.total}</bdi> {t("console_people.records")}
          </span>
        </div>
        <form
          onSubmit={submit}
          className="people-directory-filters console-filter-bar console-filter-bar--short"
        >
          <div className="people-directory-search field">
            <label htmlFor="people-search">{t("console_people.search")}</label>
            <input
              id="people-search"
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className={fieldClass}
              placeholder={t("console_people.search_hint")}
            />
          </div>
          <div className="field">
            <label htmlFor="people-status">{t("console_people.show")}</label>
            <select
              id="people-status"
              value={archived}
              onChange={(event) => setArchived(event.target.value)}
              className={fieldClass}
            >
              <option value="0">{t("console_people.current_records")}</option>
              <option value="1">{t("console_people.archived")}</option>
            </select>
          </div>
          <div className="console-filter-actions">
            <button className={secondaryClass} type="submit">
              {t("console_people.apply")}
            </button>
            {(filters.search || filters.archived === "1") && (
              <Link href={indexUrl} className="inline-link">
                {t("console_people.clear")}
              </Link>
            )}
          </div>
        </form>
        {!people.data.length ? (
          <div className="people-directory-empty">
            <h3>{t("console_people.empty")}</h3>
            <p>{t("console_people.empty_hint")}</p>
          </div>
        ) : (
          <div className="console-table-wrap">
            <table className="console-table">
              <thead>
                <tr>
                  {["person", "study_link", "status", "actions"].map((key) => (
                    <th key={key}>{t("console_people." + key)}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {people.data.map((person) => (
                  <tr key={person.id}>
                    <td>
                      <div className="people-directory-name">
                        <span
                          className="people-directory-avatar"
                          aria-hidden="true"
                        >
                          {person.name
                            .trim()
                            .split(/\s+/)
                            .slice(0, 2)
                            .map((part) => part[0])
                            .join(" ")}
                        </span>
                        <div>
                          <Link
                            href={person.show_url + "?" + context}
                            className="cell-title"
                          >
                            {person.name}
                          </Link>
                          <div className="people-directory-meta">
                            <bdi>{person.code}</bdi>
                            <bdi>{person.username}</bdi>
                            {person.phone && <bdi>{person.phone}</bdi>}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td>{person.study}</td>
                    <td>
                      <span className="console-status">{person.status}</span>
                      <div className="people-directory-meta">
                        <bdi>{person.timezone || "—"}</bdi>
                      </div>
                    </td>
                    <td>
                      <div className="people-row-actions">
                        <Link
                          href={person.show_url + "?" + context}
                          className="inline-link"
                        >
                          {t("console_people.open_profile")}
                          <ArrowLeft size={14} />
                        </Link>
                        {person.edit_url && (
                          <Link
                            href={person.edit_url + "?" + context}
                            className="inline-link"
                          >
                            {t("console_people.edit")}
                          </Link>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="people-directory-footer">
          <span>
            <bdi>
              {people.from ?? 0}–{people.to ?? 0} / {people.total}
            </bdi>
          </span>
          <div className="people-row-actions">
            {people.prev_page_url && (
              <Link
                preserveScroll
                href={people.prev_page_url}
                className={secondaryClass}
              >
                {t("console_people.previous")}
              </Link>
            )}
            {people.next_page_url && (
              <Link
                preserveScroll
                href={people.next_page_url}
                className={secondaryClass}
              >
                {t("console_people.next")}
              </Link>
            )}
          </div>
        </div>
      </section>
    </ConsoleLayout>
  );
}
