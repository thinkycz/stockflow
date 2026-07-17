import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { FlashProps, SharedProps } from '@/types';

export function useSharedProps() {
    const page = usePage<SharedProps>();
    const flash = computed<FlashProps>(() => {
        const inertiaFlash = page.flash as Partial<FlashProps> | undefined;

        return {
            success:
                typeof inertiaFlash?.success === 'string'
                    ? inertiaFlash.success
                    : (page.props.flash?.success ?? null),
            error:
                typeof inertiaFlash?.error === 'string'
                    ? inertiaFlash.error
                    : (page.props.flash?.error ?? null),
        };
    });

    return {
        app: computed(() => page.props.app),
        auth: computed(() => page.props.auth),
        user: computed(() => page.props.auth?.user ?? null),
        activeStore: computed(() => page.props.active_store ?? null),
        availableStores: computed(() => page.props.available_stores ?? []),
        flash,
        flashSuccess: computed(() => flash.value.success),
        flashError: computed(() => flash.value.error),
        errors: computed(() => page.props.errors),
    };
}
