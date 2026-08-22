import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

import type {
    AppPageProps,
    TranslationDictionary,
    TranslationValue,
} from '@/types';

function findTranslation(
    translations: TranslationDictionary,
    key: string,
): TranslationValue | undefined {
    const directValue = translations[key];

    if (directValue !== undefined) {
        return directValue;
    }

    return key.split('.').reduce<TranslationValue | undefined>(
        (value, segment) => {
            if (value === undefined || typeof value === 'string') {
                return undefined;
            }

            return value[segment];
        },
        translations,
    );
}

export function t(
    key: string,
    translations: TranslationDictionary,
): string {
    const value = findTranslation(translations, key);

    return typeof value === 'string' ? value : key;
}

export function useI18n(): (key: string) => string {
    const { translations } = usePage<AppPageProps>().props;

    return useCallback(
        (key: string) => t(key, translations),
        [translations],
    );
}
