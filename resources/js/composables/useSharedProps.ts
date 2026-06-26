import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '@/types';

export function useSharedProps() {
    const page = usePage<SharedProps>();

    return {
        app: computed(() => page.props.app),
        auth: computed(() => page.props.auth),
        user: computed(() => page.props.auth?.user ?? null),
        activeStore: computed(() => page.props.active_store ?? null),
        availableStores: computed(() => page.props.available_stores ?? []),
        flash: computed(() => page.props.flash),
        flashSuccess: computed(() => page.props.flash?.success ?? null),
        flashError: computed(() => page.props.flash?.error ?? null),
        errors: computed(() => page.props.errors),
    };
}
