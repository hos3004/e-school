import { Link } from '@inertiajs/react';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

export default function NotFound() {
    const t = useI18n();
    return <MarketingLayout><Seo title={t('marketing.not_found.title')} description={t('marketing.not_found.description')} /><section className="marketing-section"><div className="marketing-container grid min-h-[520px] place-items-center text-center"><div><p className="text-8xl font-semibold text-[var(--marketing-blue)]">404</p><h1 className="mt-6 text-4xl font-semibold">{t('marketing.not_found.title')}</h1><p className="mx-auto mt-5 max-w-xl leading-8 text-[var(--marketing-muted)]">{t('marketing.not_found.description')}</p><Link href="/" className="marketing-button marketing-button-primary mt-8">{t('marketing.not_found.action')}</Link></div></div></section></MarketingLayout>;
}

