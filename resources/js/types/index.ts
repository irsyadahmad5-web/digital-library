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

export interface SharedPageProps {
    [key: string]: unknown;
    appName: string;
    site: SiteSettings;
    auth: {
        user: AuthUser | null;
    };
    flash: {
        status: string | null;
    };
}
