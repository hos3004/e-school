import { Head } from '@inertiajs/react';

interface SeoProps {
    title: string;
    description: string;
    image?: string;
    schema?: Record<string, unknown>;
}

export default function Seo({ title, description, image = '/images/marketing/brand-story.jpg', schema }: SeoProps) {
    return (
        <Head title={title}>
            <meta name="description" content={description} />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={image} />
            <meta property="og:type" content="website" />
            <meta name="twitter:card" content="summary_large_image" />
            {schema && <script type="application/ld+json">{JSON.stringify(schema)}</script>}
        </Head>
    );
}

