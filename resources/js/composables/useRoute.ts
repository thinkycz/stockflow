import { usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import type { SharedProps } from '@/types';

/**
 * Resolve a named route via Ziggy using the current page's shared
 * `ziggy` prop. The page's props carry the route table that the
 * server's `HandleInertiaRequests` middleware injected, so this
 * works in every component without any extra setup.
 *
 * @example
 *   const route = useRoute();
 *   <Link :href="route('items.show', { item: 1 })">
 */
export function useRoute(): (
    name: string,
    params?: Record<string, string | number | boolean | null>,
    absolute?: boolean,
) => string {
    const page = usePage<SharedProps>();
    const ziggy = page.props.ziggy;

    return (name, params, absolute) =>
        ziggyRoute(name, params, absolute, ziggy);
}
