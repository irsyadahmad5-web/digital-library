export interface SeoSocialPayload {
    type?: string;
    locale?: string;
    site_name?: string;
    title: string;
    description: string;
    url?: string;
    image?: string | null;
    image_alt?: string;
}

export interface SeoTwitterPayload {
    card: 'summary' | 'summary_large_image';
    title: string;
    description: string;
    image?: string | null;
    image_alt?: string;
}

export interface SeoPayload {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    google_site_verification?: string;
    open_graph: SeoSocialPayload;
    twitter: SeoTwitterPayload;
    json_ld: Record<string, unknown>[];
}
