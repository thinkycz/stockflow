<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';
import { cn } from '@/lib/utils';

const props = defineProps<{
    compact?: boolean;
    integrated?: boolean;
}>();

const { t } = useI18n();
const route = useRoute();
const { activeStore, availableStores } = useSharedProps();

const selectedId = ref<string>(
    activeStore.value !== null ? String(activeStore.value.id) : '',
);

// Reconcile the local selection with the server-confirmed active store
// whenever the shared prop changes (e.g. after a successful switch, after
// navigation, or when the resolver falls back to a different store).
watch(
    () => activeStore.value?.id,
    (id) => {
        selectedId.value = id === undefined ? '' : String(id);
        switching.value = false;
    },
);

const options = computed(() =>
    availableStores.value.map((store) => ({
        value: String(store.id),
        label: store.is_warehouse
            ? `${store.name} (${t('store_switcher.warehouse')})`
            : store.name,
    })),
);

const hasStores = computed(() => options.value.length > 0);

const switching = ref(false);

function onChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (target === null) {
        return;
    }
    const next = target.value;
    const previous =
        activeStore.value !== null ? String(activeStore.value.id) : '';
    if (next === '' || next === previous || switching.value) {
        selectedId.value = previous;
        return;
    }

    // Optimistically reflect the user's choice immediately.
    selectedId.value = next;
    switching.value = true;

    // Use a plain axios POST instead of router.post so we avoid the
    // Inertia re-mount / preserveState race that caused the select to
    // revert to the old store on mobile. The controller returns JSON
    // with the confirmed active store; we then reload the Inertia page
    // to refresh all props (active_store + page-specific data like
    // items, movements, metrics, etc.) so the page reflects the new
    // active store immediately.
    window.axios
        .post(route('stores.switch'), { store_id: Number(next) })
        .then(() => {
            // Reload the current page to pick up the updated shared
            // props and page-specific data. The component tree stays
            // alive so the drawer remains open on mobile.
            router.reload();
        })
        .catch(() => {
            // Revert to the previous store on error.
            selectedId.value = previous;
        })
        .finally(() => {
            switching.value = false;
        });
}
</script>

<template>
    <div
        v-if="hasStores"
        :class="
            cn(
                'flex w-full min-w-0 items-center gap-2 rounded-xl border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs font-semibold text-on-surface',
                props.compact && 'px-2 py-1.5',
                props.integrated &&
                    'gap-3 rounded-lg border-0 bg-surface-container-low px-3 py-2.5 text-on-surface',
            )
        "
    >
        <Building2 :size="14" class="shrink-0 text-on-surface-variant" />
        <select
            v-if="props.compact"
            :value="selectedId"
            :disabled="switching"
            :aria-label="t('store_switcher.label')"
            :aria-busy="switching"
            class="min-w-0 flex-1 cursor-pointer bg-transparent text-xs font-semibold text-on-surface outline-none disabled:cursor-wait disabled:opacity-60"
            @change="onChange"
        >
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <div
            v-else-if="props.integrated"
            :title="t('store_switcher.label')"
            :class="
                cn(
                    'group relative flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-outline-glass bg-surface-container-lowest px-2.5 py-1.5 text-xs font-semibold text-on-surface transition hover:border-primary/40 hover:bg-surface-container-low hover:shadow-[0_1px_3px_rgba(15,23,42,0.06)] focus-within:border-primary focus-within:shadow-[0_0_0_3px_rgba(15,23,42,0.04)]',
                    switching && 'cursor-wait opacity-60',
                )
            "
        >
            <select
                :value="selectedId"
                :disabled="switching"
                :title="t('store_switcher.label')"
                :aria-label="t('store_switcher.label')"
                :aria-busy="switching"
                class="min-w-0 flex-1 cursor-pointer appearance-none bg-transparent pr-5 text-xs font-semibold text-on-surface outline-none disabled:cursor-wait"
                @change="onChange"
            >
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <ChevronDown
                :size="12"
                class="pointer-events-none absolute right-2.5 shrink-0 text-on-surface-variant transition group-hover:text-primary"
            />
        </div>
        <div v-else class="flex min-w-0 flex-1 flex-col">
            <span
                class="text-[9px] font-medium uppercase tracking-wider text-on-surface-variant opacity-85"
            >
                {{ t('store_switcher.label') }}
            </span>
            <select
                :value="selectedId"
                :disabled="switching"
                :aria-label="t('store_switcher.label')"
                :aria-busy="switching"
                class="min-w-0 cursor-pointer truncate bg-transparent text-xs font-semibold text-on-surface outline-none disabled:cursor-wait disabled:opacity-60"
                @change="onChange"
            >
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>
    </div>
</template>
