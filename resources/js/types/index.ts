export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export interface SharedPageProps {
    appName: string;
    auth: {
        user: AuthUser | null;
    };
}