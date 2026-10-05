import { Reveal } from './Reveal';

interface SectionIntroProps {
    eyebrow: string;
    title: string;
    description: string;
    align?: 'start' | 'center';
}

export default function SectionIntro({ eyebrow, title, description, align = 'start' }: SectionIntroProps) {
    return (
        <Reveal className={align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-3xl'}>
            <p className="marketing-eyebrow">{eyebrow}</p>
            <h2 className="mt-4 text-3xl font-semibold leading-tight text-[var(--marketing-ink)] md:text-5xl">{title}</h2>
            <p className="mt-6 text-base leading-8 text-[var(--marketing-muted)] md:text-lg">{description}</p>
        </Reveal>
    );
}

