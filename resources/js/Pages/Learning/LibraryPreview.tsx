import { Link } from "@inertiajs/react";
import { ArrowLeft, BookOpen, FileText, Link2 } from "lucide-react";
import { useI18n } from "@/lib/i18n";
import "../../../css/learning-library.css";
export interface LibraryMaterial {
  id: string;
  title: string;
  description: string;
  kind: "file" | "link";
  courseId: string;
  course: string;
  sizeBytes: number | null;
  revision: number;
  publishedAt: string | null;
  url: string;
  downloadUrl: string;
}
export interface LibraryPreviewData {
  items: LibraryMaterial[];
  total: number;
  url: string | null;
}
export default function LibraryPreview({ data }: { data: LibraryPreviewData }) {
  const t = useI18n();
  if (!data.url) return null;
  return (
    <div className="lp-library-preview">
      <h3 className="lp-subheading">{t("learning_library.review")}</h3>
      {data.items.length ? (
        data.items.map((item) => (
          <Link href={item.url} key={item.id} className="lp-resource-row">
            {item.kind === "file" ? <FileText /> : <Link2 />}
            <span>
              <b>{item.title}</b>
              <small>{item.course}</small>
              <span className="lp-resource-state">
                {t("learning_library." + item.kind)}
              </span>
            </span>
            <ArrowLeft />
          </Link>
        ))
      ) : (
        <div className="lp-library-empty">
          <BookOpen size={22} />
          <p>{t("learning_library.empty")}</p>
        </div>
      )}
      <Link href={data.url} className="lp-text-action">
        {t("learning_library.all")}
        <span dir="ltr">({data.total})</span>
        <ArrowLeft size={15} />
      </Link>
    </div>
  );
}
