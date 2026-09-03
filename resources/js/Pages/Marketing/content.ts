export type ProgramSlug = 'quran' | 'children' | 'coding' | 'professional' | 'family';

export interface ProgramDefinition {
    slug: ProgramSlug;
    image: string;
    gallery: string[];
    accent: 'gold' | 'green' | 'blue' | 'violet' | 'coral';
    outcomes: number;
    modules: number;
}

export const programs: ProgramDefinition[] = [
    {
        slug: 'quran',
        image: '/images/marketing/quran-hero.jpg',
        gallery: ['/images/marketing/quran-girls-circle.jpg', '/images/marketing/quran-boys-circle.jpg', '/images/marketing/quran-women.jpg'],
        accent: 'gold', outcomes: 5, modules: 4,
    },
    {
        slug: 'children',
        image: '/images/marketing/quran-girl.jpg',
        gallery: ['/images/marketing/quran-boy.jpg', '/images/marketing/quran-girls-circle.jpg', '/images/marketing/facebook-values.jpg'],
        accent: 'green', outcomes: 5, modules: 4,
    },
    {
        slug: 'coding',
        image: '/images/marketing/coding-boys.jpg',
        gallery: ['/images/marketing/python-lab.jpg', '/images/marketing/ai-girls.jpg', '/images/marketing/robotics.jpg'],
        accent: 'blue', outcomes: 5, modules: 5,
    },
    {
        slug: 'professional',
        image: '/images/marketing/creative-design.jpg',
        gallery: ['/images/marketing/data-analysis.jpg', '/images/marketing/app-development.jpg', '/images/marketing/brand-story.jpg'],
        accent: 'violet', outcomes: 5, modules: 4,
    },
    {
        slug: 'family',
        image: '/images/marketing/facebook-future.jpg',
        gallery: ['/images/marketing/facebook-identity.jpg', '/images/marketing/facebook-distance.jpg', '/images/marketing/facebook-join.jpg'],
        accent: 'coral', outcomes: 5, modules: 4,
    },
];

export const getProgram = (slug: ProgramSlug): ProgramDefinition =>
    programs.find((program) => program.slug === slug) ?? {
        slug: 'quran',
        image: '/images/marketing/quran-hero.jpg',
        gallery: ['/images/marketing/quran-girls-circle.jpg', '/images/marketing/quran-boys-circle.jpg', '/images/marketing/quran-women.jpg'],
        accent: 'gold', outcomes: 5, modules: 4,
    };
