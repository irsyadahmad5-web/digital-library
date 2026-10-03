export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

export interface SharedPageProps {
    [key: string]: unknown;
    appName: string;
    auth: {
        user: AuthUser | null;
    };
    flash: {
        status: string | null;
    };
}
