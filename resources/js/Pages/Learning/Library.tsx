import { Link, router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BookOpen,
  Download,
  ExternalLink,
  FileText,
  Link2,
  Search,
} from "lucide-react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { LibraryMaterial } from "./LibraryPreview";
import "../../../css/learning-library.css";
interface Props {
  kind: LearningKind;
  timezone: string;
  indexUrl: string;
  materials: {
    data: LibraryMaterial[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
  } | null;
  selected: LibraryMaterial | null;
  courses: { id: string; name: string }[];
  filters: { search: string; type: string; course: string };
}
export default function Library({
  kind,
  timezone,
  indexUrl,
  materials,
  selected,
  courses,
  filters,
}: Props) {
  const t = useI18n();
  const tr = (key: string) => t("learning_library." + key);
  const [search, setSearch] = useState(filters.search);
  const [course, setCourse] = useState(filters.course);
  const [type, setType] = useState(filters.type);
  function submit(event: FormEvent) {
    event.preventDefault();
    router.get(
      indexUrl,
      { search, course, type },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }
  function date(value: string | null) {
    return value
      ? new Intl.DateTimeFormat("ar-u-nu-latn", {
          dateStyle: "medium",
          timeZone: timezone,
        }).format(new Date(value))
      : "—";
  }
  return (
    <LearningLayout kind={kind} title={selected?.title || tr("title")}>
      <div className="lp-page-heading lp-library-heading">
        <div>
          <div className="lp-kicker">{tr(selected ? "details" : "scope")}</div>
          <h1>{selected?.title || tr("title")}</h1>
          <p>
            {selected?.course ||
              tr(kind === "teacher" ? "teacher_description" : "description")}
          </p>
        </div>
        <Link
          className="lp-text-action"
          href={selected ? indexUrl : "/learn/" + kind}
        >
          <ArrowRight size={16} />
          {tr(selected ? "back_library" : "back")}
        </Link>
      </div>
      {selected ? (
        <article className="lp-library-detail">
          <div className="lp-library-file-icon">
            {selected.kind === "file" ? (
              <FileText size={38} />
            ) : (
              <Link2 size={38} />
            )}
          </div>
          <div className="lp-library-detail-body">
            <span className="lp-tag">{tr(selected.kind)}</span>
            <h2>{selected.title}</h2>
            {selected.description && (
              <p className="lp-library-description">{selected.description}</p>
            )}
            <dl className="lp-definition">
              <div>
                <dt>{tr("course")}</dt>
                <dd>{selected.course}</dd>
              </div>
              <div>
                <dt>{tr("published")}</dt>
                <dd>{date(selected.publishedAt)}</dd>
              </div>
              <div>
                <dt>{tr("revision")}</dt>
                <dd>
                  <bdi>{selected.revision}</bdi>
                </dd>
              </div>
              {selected.sizeBytes !== null && (
                <div>
                  <dt>{tr("size")}</dt>
                  <dd>
                    <bdi>
                      {new Intl.NumberFormat("ar-u-nu-latn").format(
                        selected.sizeBytes,
                      )}
                    </bdi>{" "}
                    {tr("bytes")}
                  </dd>
                </div>
              )}
            </dl>
            <a
              className="lp-btn"
              href={selected.downloadUrl}
              target={selected.kind === "link" ? "_blank" : undefined}
              rel={selected.kind === "link" ? "noopener noreferrer" : undefined}
            >
              {selected.kind === "file" ? (
                <Download size={17} />
              ) : (
                <ExternalLink size={17} />
              )}{" "}
              {tr(selected.kind === "file" ? "download" : "open_link")}
            </a>
            <p className="lp-help">
              {tr(selected.kind === "file" ? "download_hint" : "external_hint")}
            </p>
          </div>
        </article>
      ) : (
        <>
          <form
            onSubmit={submit}
            className="lp-library-filters console-filter-bar"
          >
            <label className="lp-field lp-library-search console-filter-search">
              <span>{tr("search")}</span>
              <div className="lp-library-search-input">
                <Search size={16} />
                <input
                  type="search"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder={tr("search_hint")}
                />
              </div>
            </label>
            <label className="lp-field">
              <span>{tr("course")}</span>
              <select
                value={course}
                onChange={(event) => setCourse(event.target.value)}
              >
                <option value="">{tr("all_courses")}</option>
                {courses.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="lp-field">
              <span>{tr("type")}</span>
              <select
                value={type}
                onChange={(event) => setType(event.target.value)}
              >
                <option value="">{tr("all_types")}</option>
                <option value="file">{tr("file")}</option>
                <option value="link">{tr("link")}</option>
              </select>
            </label>
            <div className="console-filter-actions">
              <button className="lp-btn" type="submit">
                {tr("apply")}
              </button>
              {(filters.search || filters.course || filters.type) && (
                <Link href={indexUrl} className="lp-text-action">
                  {tr("clear")}
                </Link>
              )}
            </div>
          </form>
          {materials?.data.length ? (
            <div className="lp-library-grid">
              {materials.data.map((item) => (
                <article key={item.id} className="lp-library-card">
                  <div className="lp-library-card-head">
                    <span className="lp-library-mini-icon">
                      {item.kind === "file" ? (
                        <FileText size={23} />
                      ) : (
                        <Link2 size={23} />
                      )}
                    </span>
                    <span className="lp-tag">{tr(item.kind)}</span>
                  </div>
                  <h2>
                    <Link href={item.url}>{item.title}</Link>
                  </h2>
                  <p className="lp-library-course">{item.course}</p>
                  {item.description && (
                    <p className="lp-library-card-description">
                      {item.description}
                    </p>
                  )}
                  <div className="lp-library-card-foot">
                    <span>{date(item.publishedAt)}</span>
                    <Link href={item.url} className="lp-text-action">
                      {tr("open")}
                      <ArrowLeft size={15} />
                    </Link>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <section className="lp-library-empty lp-library-empty-page">
              <BookOpen size={34} />
              <h2>{tr("empty")}</h2>
              <p>{tr("empty_hint")}</p>
            </section>
          )}
          {materials && (
            <div className="lp-library-pagination">
              <span>
                <bdi>
                  {materials.from ?? 0}–{materials.to ?? 0} / {materials.total}
                </bdi>{" "}
                {tr("records")}
              </span>
              <div>
                {materials.prev_page_url && (
                  <Link
                    href={materials.prev_page_url}
                    className="lp-btn lp-secondary"
                    preserveScroll
                  >
                    {tr("previous")}
                  </Link>
                )}
                {materials.next_page_url && (
                  <Link
                    href={materials.next_page_url}
                    className="lp-btn lp-secondary"
                    preserveScroll
                  >
                    {tr("next")}
                  </Link>
                )}
              </div>
            </div>
          )}
        </>
      )}
    </LearningLayout>
  );
}
