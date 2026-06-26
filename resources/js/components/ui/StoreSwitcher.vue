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

watch(
    () => activeStore.value?.id,
    (id) => {
        selectedId.value = id === undefined ? '' : String(id);
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

function onChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (target === null) {
        return;
    }
    const next = target.value;
    const previous =
        activeStore.value !== null ? String(activeStore.value.id) : '';
    if (next === '' || next === previous) {
        return;
    }
    router.post(
        route('stores.switch'),
        { store_id: Number(next) },
        { preserveScroll: true },
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
            :aria-label="t('store_switcher.label')"
            class="min-w-0 flex-1 cursor-pointer bg-transparent text-xs font-semibold text-on-surface outline-none"
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
            class="group relative flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-outline-glass bg-surface-container-lowest px-2.5 py-1.5 text-xs font-semibold text-on-surface transition hover:border-primary/40 hover:bg-surface-container-low hover:shadow-[0_1px_3px_rgba(15,23,42,0.06)] focus-within:border-primary focus-within:shadow-[0_0_0_3px_rgba(15,23,42,0.04)]"
        >
            <select
                :value="selectedId"
                :title="t('store_switcher.label')"
                :aria-label="t('store_switcher.label')"
                class="min-w-0 flex-1 cursor-pointer appearance-none bg-transparent pr-5 text-xs font-semibold text-on-surface outline-none"
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
                :aria-label="t('store_switcher.label')"
                class="min-w-0 cursor-pointer truncate bg-transparent text-xs font-semibold text-on-surface outline-none"
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
