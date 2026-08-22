import { usePage } from '@inertiajs/react';

import type { AppPageProps, Locale } from '@/types';

function validDate(value: string): Date | null {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function useLocale(): Locale {
    const { auth, locale } = usePage<AppPageProps>().props;

    return locale ?? auth.user?.locale ?? 'ar';
}

export function formatDate(
    value: string,
    locale: Locale,
    timeZone?: string,
): string {
    const date = validDate(value);

    if (date === null) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone,
    }).format(date);
}

export function formatTime(
    value: string,
    locale: Locale,
    timeZone?: string,
): string {
    const date = validDate(value);

    if (date === null) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone,
    }).format(date);
}

export function formatDateTime(
    value: string,
    locale: Locale,
    timeZone?: string,
): string {
    const date = validDate(value);

    if (date === null) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(date);
}

export function formatPercent(
    value: number,
    locale: Locale,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'percent',
        maximumFractionDigits: 1,
    }).format(value > 1 ? value / 100 : value);
}
