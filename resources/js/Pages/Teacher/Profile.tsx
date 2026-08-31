import { Head, router, useForm } from "@inertiajs/react";

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
import StatusPill from "@/Components/StatusPill";
import type { StatusColorMap } from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import {
  formatDate,
  formatDateTime,
  useLocale,
  useSupportedLocales,
} from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps } from "@/types";

import { MetricTile, teacherFieldClasses } from "./Components/TeacherUi";

interface TeacherSummary {
  id: string;
  name: string;
  code: string;
  email: string;
  phone?: string | null;
  specializations: readonly string[];
  bio: Record<string, string>;
}

interface AccountSettings {
  name: string;
  username: string | null;
  email: string;
  phone: string | null;
  locale: string;
  timezone: string;
  status: string;
  lastLoginAt: string | null;
}

interface Qualification {
  id: string;
  code: string;
  name: string;
  programName?: string | null;
  qualifiedAt?: string | null;
  notes?: string | null;
}

interface AvailabilitySlot {
  id: string;
  weekday: number;
  startTime: string;
  endTime: string;
  timezone: string;
  approvalStatus: string;
}

interface Props extends LoadablePageProps {
  teacher?: TeacherSummary | null;
  account?: AccountSettings | null;
  timezones?: readonly string[];
  qualifications?: readonly Qualification[];
  availability?: readonly AvailabilitySlot[];
  updateUrl?: string;
  passwordUrl?: string;
  availabilityUrl?: string;
}

const approvalColors: StatusColorMap<string> = {
  pending: "warning",
  approved: "success",
};

export default function Profile({
  teacher = null,
  account = null,
  timezones = [],
  qualifications = [],
  availability = [],
  updateUrl = "",
  passwordUrl = "",
  availabilityUrl = "",
  loading = false,
  error = null,
}: Props) {
  const t = useI18n();
  const locale = useLocale();
  const localeOptions = useSupportedLocales();

  const profileForm = useForm({
    name: account?.name ?? "",
    phone: account?.phone ?? "",
    locale: account?.locale ?? "ar",
    timezone: account?.timezone ?? "UTC",
  });

  const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  });

  const approvedCount = availability.filter(
    (slot) => slot.approvalStatus === "approved",
  ).length;

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.profile.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          title={t("teacher.profile.title")}
          subtitle={t("teacher.profile.subtitle")}
        />

        {!loading && !error && teacher ? (
          <section
            aria-label={t("teacher.profile.title")}
            className="grid gap-3 sm:grid-cols-3"
          >
            <MetricTile
              emphasis="brand"
              icon="profile"
              label={t("teacher.profile.subjects")}
              value={teacher.specializations.length}
            />
            <MetricTile
              icon="document"
              label={t("teacher.profile.qualifications_title")}
              value={qualifications.length}
            />
            <MetricTile
              icon="clock"
              label={t("teacher.profile.approved_slots")}
              value={`${approvedCount} / ${availability.length}`}
            />
          </section>
        ) : null}

        {loading ? (
          <LoadingState label={t("teacher.profile.loading")} rows={4} />
        ) : error ? (
          <ErrorState message={error} onRetry={() => router.reload()} />
        ) : !teacher ? (
          <EmptyState
            title={t("teacher.profile.empty_title")}
            description={t("teacher.profile.empty_description")}
          />
        ) : (
          <div className="space-y-6">
            <Card className="overflow-hidden border-[var(--brand)]/30">
              <CardHeader className="mb-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-mono text-sm text-[var(--brand)]">
                      {teacher.code}
                    </p>
                    <CardTitle className="mt-1">{teacher.name}</CardTitle>
                  </div>
                  {account ? (
                    <StatusPill
                      colorMap={{
                        active: "success",
                        suspended: "danger",
                        pending: "warning",
                      }}
                      status={account.status}
                    />
                  ) : null}
                </div>
              </CardHeader>

              <dl className="grid gap-4 sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.profile.fields.email")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {teacher.email}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.profile.fields.phone")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {teacher.phone || t("common.not_available")}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.profile.approved_slots")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {approvedCount} / {availability.length}
                  </dd>
                </div>
                {account?.lastLoginAt ? (
                  <div>
                    <dt className="text-xs text-[var(--ink-muted)]">
                      {t("account.last_login")}
                    </dt>
                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                      {formatDateTime(account.lastLoginAt, locale)}
                    </dd>
                  </div>
                ) : null}
              </dl>

              {teacher.specializations.length > 0 ? (
                <div className="mt-6">
                  <h3 className="text-sm font-bold text-[var(--ink)]">
                    {t("teacher.profile.subjects")}
                  </h3>
                  <ul className="mt-2 flex flex-wrap gap-2">
                    {teacher.specializations.map((specialization) => (
                      <li
                        className="rounded-full bg-[var(--surface-muted)] px-3 py-1 text-sm text-[var(--ink)]"
                        key={specialization}
                      >
                        {specialization}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </Card>

            <Card className="border-[var(--line)]">
              <CardHeader className="mb-4">
                <CardTitle>
                  {t("teacher.profile.qualifications_title")}
                </CardTitle>
                <CardDescription>
                  {t("teacher.profile.qualifications_description")}
                </CardDescription>
              </CardHeader>

              {qualifications.length === 0 ? (
                <p className="text-sm text-[var(--ink-muted)]">
                  {t("teacher.profile.qualifications_empty")}
                </p>
              ) : (
                <ul className="divide-y divide-[var(--surface-muted)]">
                  {qualifications.map((qualification) => (
                    <li
                      className="flex flex-wrap items-center justify-between gap-3 py-3"
                      key={qualification.id}
                    >
                      <div className="min-w-0">
                        <p className="font-semibold text-[var(--ink)]">
                          {qualification.name}
                        </p>
                        <p className="mt-1 text-sm text-[var(--ink-muted)]">
                          <span className="font-mono">
                            {qualification.code}
                          </span>
                          {qualification.programName
                            ? ` · ${qualification.programName}`
                            : ""}
                        </p>
                      </div>
                      {qualification.qualifiedAt ? (
                        <span className="text-xs text-[var(--ink-muted)]">
                          {formatDate(qualification.qualifiedAt, locale)}
                        </span>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            <Card className="border-[var(--line)]">
              <CardHeader className="mb-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <CardTitle>{t("teacher.availability.title")}</CardTitle>
                    <CardDescription>
                      {t("teacher.profile.availability_hint")}
                    </CardDescription>
                  </div>
                  {availabilityUrl ? (
                    <Button
                      as="link"
                      href={availabilityUrl}
                      size="sm"
                      variant="secondary"
                    >
                      {t("teacher.profile.manage_availability")}
                    </Button>
                  ) : null}
                </div>
              </CardHeader>

              {availability.length === 0 ? (
                <p className="text-sm text-[var(--ink-muted)]">
                  {t("teacher.availability.empty_description")}
                </p>
              ) : (
                <ul className="flex flex-wrap gap-2">
                  {availability.map((slot) => (
                    <li
                      className="flex items-center gap-2 rounded-lg border border-[var(--surface-muted)] px-3 py-2 text-sm"
                      key={slot.id}
                    >
                      <span className="text-[var(--ink)]">
                        {t(`weekdays.${String(slot.weekday)}`)}
                      </span>
                      <span
                        className="font-mono text-[var(--ink-muted)]"
                        dir="ltr"
                      >
                        {slot.startTime}–{slot.endTime}
                      </span>
                      <StatusPill
                        colorMap={approvalColors}
                        status={slot.approvalStatus}
                      />
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            <Card className="border-[var(--line)]">
              <CardHeader className="mb-5">
                <CardTitle>{t("account.edit_title")}</CardTitle>
                <CardDescription>
                  {t("account.edit_description")}
                </CardDescription>
              </CardHeader>

              <form
                className="grid gap-4 sm:grid-cols-2"
                onSubmit={(event) => {
                  event.preventDefault();
                  profileForm.patch(updateUrl, {
                    preserveScroll: true,
                  });
                }}
              >
                <label className="text-sm">
                  {t("account.fields.name")}
                  <input
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      profileForm.setData("name", event.target.value)
                    }
                    required
                    type="text"
                    value={profileForm.data.name}
                  />
                  {profileForm.errors.name ? (
                    <span className="mt-1 block text-xs text-[var(--danger)]">
                      {profileForm.errors.name}
                    </span>
                  ) : null}
                </label>

                <label className="text-sm">
                  {t("account.fields.phone")}
                  <input
                    className={teacherFieldClasses}
                    dir="ltr"
                    onChange={(event) =>
                      profileForm.setData("phone", event.target.value)
                    }
                    type="tel"
                    value={profileForm.data.phone ?? ""}
                  />
                </label>

                <label className="text-sm">
                  {t("common.language")}
                  <select
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      profileForm.setData("locale", event.target.value)
                    }
                    value={profileForm.data.locale}
                  >
                    {localeOptions.map((option) => (
                      <option key={option} value={option}>
                        {t(`locales.${option}`)}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="text-sm">
                  {t("account.fields.timezone")}
                  <select
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      profileForm.setData("timezone", event.target.value)
                    }
                    value={profileForm.data.timezone}
                  >
                    {timezones.map((zone) => (
                      <option key={zone} value={zone}>
                        {zone}
                      </option>
                    ))}
                  </select>
                </label>

                <div className="sm:col-span-2">
                  <Button
                    className="w-full sm:w-auto"
                    disabled={profileForm.processing}
                    type="submit"
                  >
                    {profileForm.processing
                      ? t("actions.saving")
                      : t("account.save")}
                  </Button>
                </div>
              </form>

              <p className="mt-4 text-xs leading-6 text-[var(--ink-muted)]">
                {t("account.identity_locked_note")}
              </p>
            </Card>

            <Card className="border-[var(--line)]">
              <CardHeader className="mb-5">
                <CardTitle>{t("account.password_title")}</CardTitle>
                <CardDescription>
                  {t("account.password_description")}
                </CardDescription>
              </CardHeader>

              <form
                className="grid gap-4 sm:grid-cols-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  passwordForm.put(passwordUrl, {
                    preserveScroll: true,
                    onSuccess: () => passwordForm.reset(),
                  });
                }}
              >
                <label className="text-sm">
                  {t("account.fields.current_password")}
                  <input
                    autoComplete="current-password"
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      passwordForm.setData(
                        "current_password",
                        event.target.value,
                      )
                    }
                    required
                    type="password"
                    value={passwordForm.data.current_password}
                  />
                  {passwordForm.errors.current_password ? (
                    <span className="mt-1 block text-xs text-[var(--danger)]">
                      {passwordForm.errors.current_password}
                    </span>
                  ) : null}
                </label>

                <label className="text-sm">
                  {t("account.fields.new_password")}
                  <input
                    autoComplete="new-password"
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      passwordForm.setData("password", event.target.value)
                    }
                    required
                    type="password"
                    value={passwordForm.data.password}
                  />
                  {passwordForm.errors.password ? (
                    <span className="mt-1 block text-xs text-[var(--danger)]">
                      {passwordForm.errors.password}
                    </span>
                  ) : null}
                </label>

                <label className="text-sm">
                  {t("account.fields.confirm_password")}
                  <input
                    autoComplete="new-password"
                    className={teacherFieldClasses}
                    onChange={(event) =>
                      passwordForm.setData(
                        "password_confirmation",
                        event.target.value,
                      )
                    }
                    required
                    type="password"
                    value={passwordForm.data.password_confirmation}
                  />
                </label>

                <div className="sm:col-span-3">
                  <Button
                    className="w-full sm:w-auto"
                    disabled={passwordForm.processing}
                    type="submit"
                    variant="secondary"
                  >
                    {passwordForm.processing
                      ? t("actions.saving")
                      : t("account.change_password")}
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
