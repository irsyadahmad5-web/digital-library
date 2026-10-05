export interface PublicAuthor {
    name: string;
    slug: string;
}

export interface PublicNamedLink {
    name: string;
    slug: string;
}

export interface PublicLanguage {
    name: string;
    code: string;
}

export interface PublicBookCard {
    id: number;
    title: string;
    subtitle: string | null;
    slug: string;
    cover_url: string | null;
    authors: PublicAuthor[];
    publisher: PublicNamedLink | null;
    language: PublicLanguage | null;
    collection: PublicNamedLink | null;
    categories: PublicNamedLink[];
    publication_year: number | null;
    page_count: number | null;
    read_enabled: boolean;
    download_enabled: boolean;
    published_at: string | null;
}

export interface PublicBookDetail extends PublicBookCard {
    description: string | null;
    isbn: string | null;
    edition: string | null;
    reader_revision: string | null;
    tags: PublicNamedLink[];
}

export interface DirectoryItem {
    name: string;
    slug: string;
    description: string | null;
    count: number;
    parent?: PublicNamedLink | null;
}

export interface PublicPaginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface TaxonomyInfo {
    name: string;
    slug: string;
    description: string | null;
    parent: PublicNamedLink | null;
}

export interface HomepageSectionPayload {
    id: number;
    type: 'hero' | 'search' | 'latest_books' | 'popular_books' | 'recommendations' | 'categories' | 'collections';
    title: string;
    config: Record<string, unknown>;
    data: PublicBookCard[] | DirectoryItem[] | null;
}
