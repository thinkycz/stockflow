declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<{}, {}, any>;

    export default component;
}

interface Window {
    axios: import('axios').AxiosInstance;
    route: (
        name: string,
        params?:
            | Record<string, string | number | boolean | null>
            | string
            | number,
        absolute?: boolean,
    ) => string;
}

export {};

declare module 'vue' {
    interface ComponentCustomProperties {
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
