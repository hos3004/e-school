export function formatNumber(value: number, locale = "ar"): string {
  return new Intl.NumberFormat(locale, {
    numberingSystem: "latn",
    maximumFractionDigits: 1,
  }).format(value);
}
export function formatTime(
  value: string,
  timezone: string,
  locale = "ar",
): string {
  return new Intl.DateTimeFormat(locale, {
    timeZone: timezone,
    numberingSystem: "latn",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).format(new Date(value));
}
export function formatDate(
  value: string,
  timezone: string,
  locale = "ar",
): string {
  const calendarDate = /^\d{4}-\d{2}-\d{2}$/.test(value);
  return new Intl.DateTimeFormat(locale, {
    timeZone: calendarDate ? "UTC" : timezone,
    numberingSystem: "latn",
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(new Date(value));
}
