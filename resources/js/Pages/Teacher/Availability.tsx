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
import { formatDate, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps } from "@/types";

import { MetricTile, teacherFieldClasses } from "./Components/TeacherUi";

interface AvailabilitySlot {
  id: string;
  weekday: number;
  startTime: string;
  endTime: string;
  timezone: string;
  effectiveFrom: string;
  effectiveTo?: string | null;
  approvalStatus: string;
}

interface Props extends LoadablePageProps {
  availability?: readonly AvailabilitySlot[];
  hasProfile?: boolean;
  timezones?: readonly string[];
  defaultTimezone?: string;
  storeUrl?: string;
  canManage?: boolean;
}

const approvalColors: StatusColorMap<string> = {
  pending: "warning",
  approved: "success",
};

const weekdays = [0, 1, 2, 3, 4, 5, 6] as const;

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

export default function Availability({
  availability = [],
  hasProfile = false,
  timezones = [],
  defaultTimezone = "UTC",
  storeUrl = "",
  canManage = false,
  loading = false,
  error = null,
}: Props) {
  const t = useI18n();
  const locale = useLocale();

  const form = useForm({
    weekday: "0",
    start_time: "",
    end_time: "",
    timezone: defaultTimezone,
    effective_from: todayIso(),
    effective_to: "",
  });

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.availability.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          title={t("teacher.availability.title")}
          subtitle={t("teacher.availability.subtitle")}
        />

        {!loading && !error && hasProfile ? (
          <section
            aria-label={t("teacher.availability.list_title")}
            className="max-w-sm"
          >
            <MetricTile
              emphasis="brand"
              icon="clock"
              label={t("teacher.availability.list_title")}
              value={availability.length}
            />
          </section>
        ) : null}

        {loading ? (
          <LoadingState label={t("teacher.availability.loading")} rows={3} />
        ) : error ? (
          <ErrorState message={error} onRetry={() => router.reload()} />
        ) : !hasProfile ? (
          <EmptyState
            title={t("teacher.availability.no_profile_title")}
            description={t("teacher.availability.no_profile_description")}
          />
        ) : (
          <div className="space-y-6">
            <Card className="border-[var(--brand)]/30">
              <CardHeader className="mb-5">
                <CardTitle>{t("teacher.availability.add_title")}</CardTitle>
                <CardDescription>
                  {t("teacher.availability.add_description")}
                </CardDescription>
              </CardHeader>

              {canManage ? (
                <form
                  className="grid gap-4 sm:grid-cols-3"
                  onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeUrl, {
                      preserveScroll: true,
                      onSuccess: () =>
                        form.setData({
                          ...form.data,
                          start_time: "",
                          end_time: "",
                        }),
                    });
                  }}
                >
                  <label className="text-sm">
                    {t("teacher.availability.weekday")}
                    <select
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("weekday", event.target.value)
                      }
                      value={form.data.weekday}
                    >
                      {weekdays.map((day) => (
                        <option key={day} value={day}>
                          {t(`weekdays.${String(day)}`)}
                        </option>
                      ))}
                    </select>
                    {form.errors.weekday ? (
                      <span className="mt-1 block text-xs text-[var(--danger)]">
                        {form.errors.weekday}
                      </span>
                    ) : null}
                  </label>

                  <label className="text-sm">
                    {t("teacher.availability.start")}
                    <input
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("start_time", event.target.value)
                      }
                      required
                      type="time"
                      value={form.data.start_time}
                    />
                    {form.errors.start_time ? (
                      <span className="mt-1 block text-xs text-[var(--danger)]">
                        {form.errors.start_time}
                      </span>
                    ) : null}
                  </label>

                  <label className="text-sm">
                    {t("teacher.availability.end")}
                    <input
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("end_time", event.target.value)
                      }
                      required
                      type="time"
                      value={form.data.end_time}
                    />
                    {form.errors.end_time ? (
                      <span className="mt-1 block text-xs text-[var(--danger)]">
                        {form.errors.end_time}
                      </span>
                    ) : null}
                  </label>

                  <label className="text-sm">
                    {t("teacher.availability.timezone")}
                    <select
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("timezone", event.target.value)
                      }
                      value={form.data.timezone}
                    >
                      {timezones.map((zone) => (
                        <option key={zone} value={zone}>
                          {zone}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="text-sm">
                    {t("teacher.availability.effective_from")}
                    <input
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("effective_from", event.target.value)
                      }
                      required
                      type="date"
                      value={form.data.effective_from}
                    />
                    {form.errors.effective_from ? (
                      <span className="mt-1 block text-xs text-[var(--danger)]">
                        {form.errors.effective_from}
                      </span>
                    ) : null}
                  </label>

                  <label className="text-sm">
                    {t("teacher.availability.effective_to")}
                    <input
                      className={teacherFieldClasses}
                      onChange={(event) =>
                        form.setData("effective_to", event.target.value)
                      }
                      type="date"
                      value={form.data.effective_to}
                    />
                    {form.errors.effective_to ? (
                      <span className="mt-1 block text-xs text-[var(--danger)]">
                        {form.errors.effective_to}
                      </span>
                    ) : null}
                  </label>

                  <div className="sm:col-span-3">
                    <Button
                      className="w-full sm:w-auto"
                      disabled={form.processing}
                      type="submit"
                    >
                      {form.processing
                        ? t("actions.saving")
                        : t("teacher.availability.add")}
                    </Button>
                  </div>
                </form>
              ) : (
                <p className="text-sm text-[var(--ink-muted)]">
                  {t("teacher.availability.no_permission")}
                </p>
              )}

              <p className="mt-4 text-xs leading-6 text-[var(--ink-muted)]">
                {t("teacher.availability.approval_note")}
              </p>
            </Card>

            <Card>
              <CardHeader className="mb-4">
                <CardTitle>{t("teacher.availability.list_title")}</CardTitle>
              </CardHeader>

              {availability.length === 0 ? (
                <EmptyState
                  title={t("teacher.availability.empty_title")}
                  description={t("teacher.availability.empty_description")}
                />
              ) : (
                <ul className="grid gap-3 sm:grid-cols-2">
                  {availability.map((slot) => (
                    <li
                      className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface-subtle)] p-4"
                      key={slot.id}
                    >
                      <div className="min-w-0">
                        <p className="font-semibold text-[var(--ink)]">
                          {t(`weekdays.${String(slot.weekday)}`)}{" "}
                          <span
                            className="font-mono font-normal text-[var(--ink-muted)]"
                            dir="ltr"
                          >
                            {slot.startTime}–{slot.endTime}
                          </span>
                        </p>
                        <p className="mt-1 text-sm text-[var(--ink-muted)]">
                          {slot.timezone} ·{" "}
                          {formatDate(slot.effectiveFrom, locale)}
                          {slot.effectiveTo
                            ? ` ${t("common.until")} ${formatDate(slot.effectiveTo, locale)}`
                            : ""}
                        </p>
                      </div>

                      <div className="flex items-center gap-3">
                        <StatusPill
                          colorMap={approvalColors}
                          status={slot.approvalStatus}
                        />
                        {canManage && slot.approvalStatus !== "approved" ? (
                          <Button
                            aria-label={`${t("actions.remove")} — ${t(`weekdays.${String(slot.weekday)}`)}`}
                            onClick={() =>
                              router.delete(
                                `/teacher/availability/${slot.id}`,
                                {
                                  preserveScroll: true,
                                },
                              )
                            }
                            size="sm"
                            variant="danger"
                          >
                            {t("actions.remove")}
                          </Button>
                        ) : null}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
