import { Head, router } from "@inertiajs/react";

import Button from "@/Components/Button";
import Card, {
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/Card";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatPercent, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps } from "@/types";

interface StudentGroup {
  id: string;
  code: string;
  name: string;
  joinedAt?: string | null;
}

interface TeacherStudentProfile {
  id: string;
  name: string;
  code: string;
  status: string;
  gender?: string | null;
  dateOfBirth?: string | null;
  country?: string | null;
  city?: string | null;
  attendanceRate?: number | null;
  openAssignmentsCount: number;
  groups: readonly StudentGroup[];
}

interface Props extends LoadablePageProps {
  student?: TeacherStudentProfile | null;
}

export default function TeacherStudentShow({
  student = null,
  loading = false,
  error = null,
}: Props) {
  const t = useI18n();
  const locale = useLocale();

  const profileFields: ReadonlyArray<readonly [string, string]> = student
    ? [
        ["code", student.code],
        ["status", t(`statuses.${student.status}`)],
        [
          "date_of_birth",
          student.dateOfBirth
            ? formatDate(student.dateOfBirth, locale)
            : t("common.not_available"),
        ],
        [
          "gender",
          student.gender
            ? t(`teacher.students.profile.genders.${student.gender}`)
            : t("common.not_available"),
        ],
        ["country", student.country || t("common.not_available")],
        ["city", student.city || t("common.not_available")],
      ]
    : [];

  return (
    <AppLayout role="teacher">
      <Head title={student?.name ?? t("teacher.students.profile.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          action={
            <Button as="link" href="/teacher/students" variant="secondary">
              {t("teacher.students.profile.back")}
            </Button>
          }
          subtitle={t("teacher.students.profile.subtitle")}
          title={student?.name ?? t("teacher.students.profile.title")}
        />

        {loading ? (
          <LoadingState label={t("teacher.students.loading")} rows={4} />
        ) : error ? (
          <ErrorState message={error} onRetry={() => router.reload()} />
        ) : !student ? (
          <EmptyState
            description={t("teacher.students.profile.empty_description")}
            title={t("teacher.students.profile.empty_title")}
          />
        ) : (
          <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">
                  {t("teacher.students.profile.summary_title")}
                </CardTitle>
                <CardDescription>
                  {t("teacher.students.profile.summary_description")}
                </CardDescription>
              </CardHeader>
              <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                {profileFields.map(([key, value]) => (
                  <div
                    className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-4"
                    key={key}
                  >
                    <dt className="text-xs text-[var(--ink-muted)]">
                      {t(`teacher.students.profile.fields.${key}`)}
                    </dt>
                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                      {value}
                    </dd>
                  </div>
                ))}
                <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-4">
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.students.columns.attendance_rate")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {student.attendanceRate === null ||
                    student.attendanceRate === undefined
                      ? t("common.not_available")
                      : formatPercent(student.attendanceRate, locale)}
                  </dd>
                </div>
                <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-4">
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.students.columns.open_assignments")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {student.openAssignmentsCount}
                  </dd>
                </div>
              </dl>
            </Card>

            <Card as="section">
              <CardHeader>
                <CardTitle as="h2">
                  {t("teacher.students.profile.groups_title")}
                </CardTitle>
                <CardDescription>
                  {t("teacher.students.profile.groups_description")}
                </CardDescription>
              </CardHeader>
              <ul className="mt-5 space-y-3">
                {student.groups.map((group) => (
                  <li
                    className="rounded-[var(--radius-md)] border border-[var(--line)] p-4"
                    key={group.id}
                  >
                    <p className="font-semibold text-[var(--ink)]">
                      {group.name}
                    </p>
                    <p className="mt-1 font-mono text-sm text-[var(--ink-muted)]">
                      {group.code}
                    </p>
                    <Button
                      as="link"
                      className="mt-3 w-full"
                      href={`/teacher/groups/${group.id}`}
                      size="sm"
                      variant="secondary"
                    >
                      {t("teacher.groups.view_details")}
                    </Button>
                  </li>
                ))}
              </ul>
            </Card>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
