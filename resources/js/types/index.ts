import type { Config as ZiggyConfig } from 'ziggy-js';

export interface AuthUser {
    id: number;
    email: string;
    locale: string;
    email_verified_at: string | null;
    is_admin: boolean;
    assigned_store_id: number | null;
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
    ziggy: ZiggyConfig & { location: string };
}

declare global {
    interface Window {
        // The params type is intentionally permissive: ziggy's
        // RouteParams is generic over the route name and we want
        // call sites to stay free of casts.
        route: (
            name: string,
            params?:
                | Record<string, string | number | boolean | null>
                | string
                | number,
            absolute?: boolean,
        ) => string;
    }
}
