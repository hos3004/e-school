import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { useI18n } from '@/lib/i18n';

const nav = [
    ['/', 'home'], ['/about', 'about'], ['/programs', 'programs'], ['/projects', 'projects'],
    ['/activities', 'activities'], ['/faq', 'faq'], ['/contact', 'contact'],
] as const;

export default function MarketingLayout({ children }: { children: ReactNode }) {
    const t = useI18n();
    const { url } = usePage();
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen overflow-x-hidden bg-[var(--marketing-paper)] text-[var(--marketing-ink)]">
            <a href="#main-content" className="marketing-skip-link">{t('marketing.a11y.skip')}</a>
            <div className="bg-[var(--marketing-navy)] px-4 py-2 text-center text-xs font-medium text-white/80 md:text-sm">{t('marketing.topbar')}</div>
            <header className="sticky top-0 z-50 border-b border-[var(--marketing-line)] bg-white/90 backdrop-blur-xl">
                <div className="marketing-container flex min-h-20 items-center justify-between gap-6">
                    <Link href="/" aria-label={t('marketing.a11y.home')} className="shrink-0">
                        <img src="/images/marketing/brand-lockup.png" alt={t('marketing.brand_alt')} className="h-12 w-auto max-w-[220px] object-contain" />
                    </Link>
                    <nav className="hidden items-center gap-1 xl:flex" aria-label={t('marketing.a11y.main_nav')}>
                        {nav.map(([href, key]) => <Link key={href} href={href} className={`marketing-nav-link ${url === href || (href !== '/' && url.startsWith(href)) ? 'is-active' : ''}`}>{t(`marketing.nav.${key}`)}</Link>)}
                    </nav>
                    <div className="hidden items-center gap-3 md:flex">
                        <Link href="/login" className="marketing-button marketing-button-ghost">{t('marketing.nav.login')}</Link>
                        <Link href="/register/student" className="marketing-button marketing-button-primary">{t('marketing.nav.join')}</Link>
                    </div>
                    <div className="xl:hidden">
                        <button type="button" className="marketing-button marketing-button-ghost" aria-expanded={open} aria-controls="marketing-mobile-nav" onClick={() => setOpen((value) => !value)}>{t(open ? 'marketing.nav.close' : 'marketing.nav.menu')}</button>
                    </div>
                </div>
                {open && (
                    <nav id="marketing-mobile-nav" className="border-t border-[var(--marketing-line)] bg-white px-4 py-6 xl:hidden" aria-label={t('marketing.a11y.mobile_nav')}>
                        <div className="mx-auto flex max-w-7xl flex-col gap-2">
                            {nav.map(([href, key]) => <Link key={href} href={href} onClick={() => setOpen(false)} className="rounded-xl px-4 py-3 font-medium hover:bg-[var(--marketing-soft)]">{t(`marketing.nav.${key}`)}</Link>)}
                            <div className="mt-3 grid grid-cols-2 gap-3"><Link href="/login" className="marketing-button marketing-button-ghost">{t('marketing.nav.login')}</Link><Link href="/register/student" className="marketing-button marketing-button-primary">{t('marketing.nav.join')}</Link></div>
                        </div>
                    </nav>
                )}
            </header>
            <main id="main-content">{children}</main>
            <footer className="mt-24 bg-[var(--marketing-navy)] text-white">
                <div className="marketing-container grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-4">
                    <div className="lg:col-span-2"><img src="/images/marketing/brand-lockup.png" alt={t('marketing.brand_alt')} className="h-14 w-auto rounded-lg bg-white object-contain px-2" /><p className="mt-6 max-w-xl leading-8 text-white/70">{t('marketing.footer.about')}</p></div>
                    <div><h2 className="font-semibold">{t('marketing.footer.explore')}</h2><div className="mt-5 flex flex-col gap-3 text-sm text-white/70"><Link href="/programs">{t('marketing.nav.programs')}</Link><Link href="/projects">{t('marketing.nav.projects')}</Link><Link href="/activities">{t('marketing.nav.activities')}</Link><Link href="/faq">{t('marketing.nav.faq')}</Link></div></div>
                    <div><h2 className="font-semibold">{t('marketing.footer.contact')}</h2><div className="mt-5 flex flex-col gap-3 text-sm text-white/70"><a href="tel:+905301833478" dir="ltr">+90 530 183 34 78</a><a href="https://wa.me/905301833478" target="_blank" rel="noreferrer">{t('marketing.footer.whatsapp')}</a><Link href="/contact">{t('marketing.footer.contact_page')}</Link></div></div>
                </div>
                <div className="border-t border-white/10"><div className="marketing-container flex flex-col gap-4 py-6 text-xs text-white/55 sm:flex-row sm:items-center sm:justify-between"><p>{t('marketing.footer.copyright')}</p><div className="flex gap-5"><Link href="/privacy">{t('marketing.footer.privacy')}</Link><Link href="/terms">{t('marketing.footer.terms')}</Link></div></div></div>
            </footer>
            <a href="https://wa.me/905301833478" target="_blank" rel="noreferrer" className="marketing-whatsapp" aria-label={t('marketing.footer.whatsapp')}>{t('marketing.footer.whatsapp_short')}</a>
        </div>
    );
}
