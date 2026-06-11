export interface AuthUser {
    id: number;
    email: string;
    locale: string;
    email_verified_at: string | null;
}

export interface AppMeta {
    name: string;
    locale: string;
    locales: string[];
}

export interface FlashProps {
    success: string | null;
    error: string | null;
}

export interface SharedProps {
    [key: string]: unknown;

    app: AppMeta;
    auth: {
        user: AuthUser | null;
    };
    flash: FlashProps;
    errors: Record<string, string>;
}
