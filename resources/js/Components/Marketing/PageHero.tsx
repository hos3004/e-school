import { Reveal } from './Reveal';

interface PageHeroProps {
    eyebrow: string;
    title: string;
    description: string;
    image: string;
    imageAlt: string;
}

export default function PageHero({ eyebrow, title, description, image, imageAlt }: PageHeroProps) {
    return (
        <section className="marketing-page-hero">
            <div className="marketing-container grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr]">
                <Reveal>
                    <p className="marketing-eyebrow">{eyebrow}</p>
                    <h1 className="mt-5 text-4xl font-semibold leading-tight text-[var(--marketing-ink)] md:text-6xl">{title}</h1>
                    <p className="mt-6 max-w-2xl text-lg leading-9 text-[var(--marketing-muted)]">{description}</p>
                </Reveal>
                <Reveal delay={100} className="marketing-image-frame aspect-[16/11]">
                    <img src={image} alt={imageAlt} className="h-full w-full object-cover" />
                </Reveal>
            </div>
        </section>
    );
}

