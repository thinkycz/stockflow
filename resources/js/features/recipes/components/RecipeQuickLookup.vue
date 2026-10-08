<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search, ArrowUpRight, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import Input from '@/components/ui/Input.vue';
import { searchRecipes, normalizeRecipeSearch } from '../search';
import type { RecipeLookupEntry } from '../types';

const props = defineProps<{ entries: RecipeLookupEntry[] }>();
const query = defineModel<string>({ default: '' });
const { t } = useI18n();
const listId = useId();
const open = ref(false);
const active = ref(0);
const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const matches = computed(() =>
    normalizeRecipeSearch(query.value).length >= 2
        ? searchRecipes(props.entries, query.value).slice(0, 8)
        : [],
);
const visible = computed(
    () => open.value && normalizeRecipeSearch(query.value).length >= 2,
);
watch(query, () => {
    open.value = true;
    active.value = 0;
});
watch(active, () =>
    list.value
        ?.querySelectorAll('[role="option"]')
        [active.value]?.scrollIntoView({ block: 'nearest' }),
);
function keydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false;
        event.preventDefault();
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        active.value = Math.max(
            0,
            Math.min(
                matches.value.length - 1,
                active.value + (event.key === 'ArrowDown' ? 1 : -1),
            ),
        );
    }
    if (event.key === 'Enter' && visible.value && matches.value[active.value]) {
        event.preventDefault();
        list.value
            ?.querySelectorAll<HTMLElement>('[role="option"]')
            [active.value]?.click();
    }
}
function dismiss(event: MouseEvent) {
    if (!root.value?.contains(event.target as Node)) open.value = false;
}
function blur(event: FocusEvent) {
    if (!root.value?.contains(event.relatedTarget as Node | null))
        open.value = false;
}
onMounted(() => document.addEventListener('click', dismiss));
onBeforeUnmount(() => document.removeEventListener('click', dismiss));
</script>

<template>
    <div
        ref="root"
        class="relative w-full"
        data-testid="recipe-lookup"
        @focusout="blur"
    >
        <label
            :for="listId"
            class="mb-2 block text-sm font-semibold text-on-surface"
            >{{ t('recipes.lookup_label') }}</label
        >
        <div class="relative">
            <Search
                :size="19"
                class="pointer-events-none absolute top-1/2 left-4 z-10 -translate-y-1/2 text-primary"
            />
            <Input
                :id="listId"
                v-model="query"
                class="h-12 rounded-2xl bg-white pr-11 pl-12 text-base"
                :placeholder="t('recipes.lookup_placeholder')"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="visible"
                :aria-controls="`${listId}-results`"
                :aria-activedescendant="
                    visible && matches.length
                        ? `${listId}-${active}`
                        : undefined
                "
                @focus="open = true"
                @keydown="keydown"
            />
            <button
                v-if="query"
                type="button"
                class="absolute top-1/2 right-3 -translate-y-1/2 rounded-lg p-1.5 text-on-surface-variant"
                :aria-label="t('recipes.clear_search')"
                @click="
                    query = '';
                    open = false;
                "
            >
                <X :size="16" />
            </button>
        </div>
        <div
            v-if="visible"
            :id="`${listId}-results`"
            ref="list"
            role="listbox"
            :aria-label="t('recipes.lookup_label')"
            class="absolute z-40 mt-2 max-h-[min(24rem,40dvh)] w-full overflow-y-auto rounded-2xl border border-outline-glass bg-white p-1.5 shadow-xl"
        >
            <Link
                v-for="(entry, index) in matches"
                :id="`${listId}-${index}`"
                :key="entry.key"
                :href="entry.url"
                role="option"
                :aria-selected="index === active"
                class="flex items-center justify-between gap-3 rounded-xl px-3 py-3"
                :class="
                    index === active
                        ? 'bg-primary/8'
                        : 'hover:bg-surface-container-low'
                "
                @mouseenter="active = index"
                @click="open = false"
            >
                <span class="min-w-0"
                    ><span class="block font-semibold text-on-surface">{{
                        entry.name
                    }}</span
                    ><span
                        class="mt-0.5 block text-xs text-on-surface-variant"
                        >{{ entry.category_name }}</span
                    ></span
                >
                <ArrowUpRight :size="17" class="shrink-0 text-primary" />
            </Link>
            <p
                v-if="!matches.length"
                role="status"
                class="p-4 text-sm text-on-surface-variant"
            >
                {{ t('recipes.no_matches') }}
            </p>
        </div>
        <p class="mt-2 text-xs text-on-surface-variant">
            {{ t('recipes.lookup_hint') }}
        </p>
    </div>
</template>
