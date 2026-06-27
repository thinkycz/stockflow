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

const isAdmin = computed(() => options.value.length > 0);

// Guards against the snap-back / double-submit race that made the switcher
// feel unreliable on slow or cold-cache sessions (e.g. anonymous windows).
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
        // Keep the rendered value in sync with the server-confirmed state.
        selectedId.value = previous;
        return;
    }

    // Optimistically reflect the user's choice immediately so the native
    // select never snaps back to the previous value while the request is
    // in flight.
    selectedId.value = next;
    switching.value = true;

    router.post(
        route('stores.switch'),
        { store_id: Number(next) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Reconcile with the server-confirmed store. The watch
                // may also fire, but onSuccess is the reliable reset path
                // when preserveState keeps the component alive across the
                // POST → 302 → GET redirect chain.
                const confirmedId =
                    activeStore.value !== null
                        ? String(activeStore.value.id)
                        : '';
                selectedId.value = confirmedId;
                switching.value = false;
            },
            onError: () => {
                selectedId.value = previous;
                switching.value = false;
            },
            onFinish: () => {
                // Safety net: ensure switching never gets stuck true even
                // if neither onSuccess nor onError fires (e.g. cancelled
                // visit, network timeout, or edge-case redirect handling).
                switching.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        v-if="isAdmin"
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
