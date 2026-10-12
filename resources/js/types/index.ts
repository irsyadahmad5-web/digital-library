export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

export type SettingValue = string | number | boolean | null;

export interface SiteSettings {
    general: Record<string, SettingValue> & {
        site_name?: string;
        short_name?: string;
        tagline?: string;
        description?: string;
        logo_path?: string;
        favicon_path?: string;
        logo_url?: string | null;
        favicon_url?: string | null;
    };
    appearance: Record<string, SettingValue>;
    homepage: Record<string, SettingValue>;
    reader: Record<string, SettingValue>;
    downloads: Record<string, SettingValue>;
    seo: Record<string, SettingValue>;
    maintenance: Record<string, SettingValue>;
    [group: string]: Record<string, SettingValue>;
}

export interface PublicFooterStatistic {
    key: 'books' | 'reads' | 'categories' | 'authors' | string;
    label: string;
    value: number;
}

export interface SharedPageProps {
    [key: string]: unknown;
    appName: string;
    site: SiteSettings;
    publicStats: PublicFooterStatistic[];
    auth: {
        user: AuthUser | null;
    };
    flash: {
        status: string | null;
    };
}
