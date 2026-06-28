<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import { ChevronDown, Loader2, X } from '@lucide/vue';
import { cn } from '@/lib/utils';

type Item = {
    id: number;
    title: string;
    sku?: string | null;
    [key: string]: unknown;
};

const model = defineModel<string | number | null>();

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        items: Item[];
        loading?: boolean;
        placeholder?: string;
        noResultsText?: string;
        loadingText?: string;
        class?: string;
        required?: boolean;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        loading: false,
        placeholder: 'Search...',
        noResultsText: 'No results found.',
        loadingText: 'Loading...',
        class: '',
        required: false,
        invalid: false,
        describedBy: undefined,
    },
);

const emit = defineEmits<{
    (e: 'search', term: string): void;
    (e: 'select', item: Item): void;
}>();

const generatedId = useId();
const inputId = computed(() => props.id ?? `combobox-${generatedId}`);

const query = ref<string>('');
const isOpen = ref<boolean>(false);
const highlightedIndex = ref<number>(-1);
const inputRef = ref<HTMLInputElement | null>(null);
const containerRef = ref<HTMLDivElement | null>(null);
const dropdownRef = ref<HTMLUListElement | null>(null);

const dropdownStyle = ref<{
    top: string;
    left: string;
    width: string;
    maxHeight: string;
}>({ top: '0px', left: '0px', width: '0px', maxHeight: '15rem' });

const selectedItem = computed((): Item | null => {
    if (
        model.value === null ||
        model.value === undefined ||
        model.value === ''
    ) {
        return null;
    }
    return (
        props.items.find((item) => String(item.id) === String(model.value)) ??
        null
    );
});

const filteredItems = computed((): Item[] => {
    const term = query.value.trim().toLowerCase();
    if (term === '') {
        return props.items;
    }
    return props.items.filter((item) => {
        const title = item.title.toLowerCase();
        const sku = (item.sku ?? '').toLowerCase();
        return title.includes(term) || sku.includes(term);
    });
});

watch(
    () => model.value,
    (value) => {
        if (value === null || value === undefined || value === '') {
            query.value = '';
            return;
        }
        const item = props.items.find((i) => String(i.id) === String(value));
        if (item !== undefined) {
            query.value = item.title;
        }
    },
    { immediate: true },
);

watch(query, (value) => {
    emit('search', value);
    highlightedIndex.value = -1;
});

watch(isOpen, async (open) => {
    if (open) {
        await nextTick();
        updatePosition();
        // Mobile browsers scroll the focused input into view asynchronously
        // (and the keyboard animates in), so the first measurement is often
        // stale. Re-measure on the following frames and after a short delay.
        requestAnimationFrame(() => requestAnimationFrame(updatePosition));
        window.setTimeout(updatePosition, 120);
    }
});

watch(filteredItems, async () => {
    if (isOpen.value) {
        await nextTick();
        updatePosition();
    }
});

watch(highlightedIndex, (index) => {
    if (index < 0 || dropdownRef.value === null) {
        return;
    }
    const el = dropdownRef.value.querySelector<HTMLElement>(
        `#${inputId.value}-option-${index}`,
    );
    el?.scrollIntoView({ block: 'nearest' });
});

function getViewportHeight(): number {
    // `visualViewport` accounts for the mobile keyboard and browser chrome,
    // which `window.innerHeight` does not.
    return window.visualViewport?.height ?? window.innerHeight;
}

function updatePosition(): void {
    const input = inputRef.value;
    if (input === null) {
        return;
    }
    const rect = input.getBoundingClientRect();
    const viewportHeight = getViewportHeight();
    const spaceBelow = viewportHeight - rect.bottom - 8;
    const spaceAbove = rect.top - 8;
    const maxDropdownHeight = 240;
    const gap = 4;

    if (spaceBelow >= maxDropdownHeight || spaceBelow >= spaceAbove) {
        dropdownStyle.value = {
            top: String(rect.bottom + gap) + 'px',
            left: String(rect.left) + 'px',
            width: String(rect.width) + 'px',
            maxHeight:
                String(Math.max(Math.min(spaceBelow, maxDropdownHeight), 0)) +
                'px',
        };
    } else {
        const height = Math.max(Math.min(spaceAbove, maxDropdownHeight), 0);
        dropdownStyle.value = {
            // Clamp so the dropdown never renders above the visible viewport.
            top: String(Math.max(rect.top - gap - height, 8)) + 'px',
            left: String(rect.left) + 'px',
            width: String(rect.width) + 'px',
            maxHeight: String(height) + 'px',
        };
    }
}

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    query.value = target.value;
    if (!isOpen.value) {
        isOpen.value = true;
    }
    highlightedIndex.value = -1;
}

function onFocus(): void {
    isOpen.value = true;
}

function onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        isOpen.value = true;
        highlightedIndex.value = Math.min(
            highlightedIndex.value + 1,
            filteredItems.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        if (
            isOpen.value &&
            highlightedIndex.value >= 0 &&
            highlightedIndex.value < filteredItems.value.length
        ) {
            event.preventDefault();
            selectItem(filteredItems.value[highlightedIndex.value]);
        }
    } else if (event.key === 'Escape') {
        isOpen.value = false;
        highlightedIndex.value = -1;
    }
}

function selectItem(item: Item): void {
    model.value = item.id;
    emit('select', item);
    query.value = item.title;
    isOpen.value = false;
    highlightedIndex.value = -1;
}

function clearSelection(): void {
    model.value = null;
    query.value = '';
    emit('select', { id: 0, title: '' });
    inputRef.value?.focus();
}

function onDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (target === null) {
        return;
    }
    const insideContainer =
        containerRef.value !== null && containerRef.value.contains(target);
    const insideDropdown =
        dropdownRef.value !== null && dropdownRef.value.contains(target);
    if (!insideContainer && !insideDropdown) {
        isOpen.value = false;
    }
}

function isInputVisible(): boolean {
    const input = inputRef.value;
    if (input === null) {
        return false;
    }
    const rect = input.getBoundingClientRect();
    const viewportHeight = getViewportHeight();
    return rect.bottom > 0 && rect.top < viewportHeight;
}

function onScrollOrResize(): void {
    if (!isOpen.value) {
        return;
    }
    // Reposition the dropdown to follow the input instead of closing it.
    // This is essential on mobile, where the user often needs to scroll to
    // reach a dropdown that opened above the input. Only close when the input
    // itself has scrolled completely out of the viewport.
    if (!isInputVisible()) {
        isOpen.value = false;
        return;
    }
    updatePosition();
}

onMounted(() => {
    document.addEventListener('mousedown', onDocumentClick);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
    window.visualViewport?.addEventListener('resize', onScrollOrResize);
    window.visualViewport?.addEventListener('scroll', onScrollOrResize);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentClick);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
    window.visualViewport?.removeEventListener('resize', onScrollOrResize);
    window.visualViewport?.removeEventListener('scroll', onScrollOrResize);
});

const showDropdown = computed((): boolean => {
    return isOpen.value;
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <div class="relative">
            <input
                :id="inputId"
                ref="inputRef"
                type="text"
                :value="query"
                :name="props.name"
                :placeholder="props.placeholder"
                :required="props.required"
                :aria-invalid="props.invalid ? 'true' : undefined"
                :aria-describedby="props.describedBy"
                :aria-expanded="showDropdown"
                :aria-controls="`${inputId}-listbox`"
                role="combobox"
                :aria-activedescendant="
                    highlightedIndex >= 0
                        ? `${inputId}-option-${highlightedIndex}`
                        : undefined
                "
                autocomplete="off"
                :class="
                    cn(
                        'h-10 w-full rounded-xl border bg-white px-3 pr-9 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                        props.invalid
                            ? 'border-error-red focus-visible:border-error-red'
                            : 'border-outline-glass focus-visible:border-primary',
                        props.class,
                    )
                "
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeyDown"
            />
            <div
                class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-on-surface-variant"
            >
                <Loader2 v-if="props.loading" :size="14" class="animate-spin" />
                <ChevronDown v-else :size="14" />
            </div>
            <button
                v-if="selectedItem !== null && !props.loading"
                type="button"
                class="absolute inset-y-0 right-7 flex items-center text-on-surface-variant hover:text-on-surface"
                :aria-label="props.noResultsText"
                @click="clearSelection"
            >
                <X :size="12" />
            </button>
        </div>
    </div>
    <Teleport to="body">
        <ul
            v-if="showDropdown"
            :id="`${inputId}-listbox`"
            ref="dropdownRef"
            role="listbox"
            :style="{
                position: 'fixed',
                top: dropdownStyle.top,
                left: dropdownStyle.left,
                width: dropdownStyle.width,
                maxHeight: dropdownStyle.maxHeight,
            }"
            class="z-50 overflow-auto rounded-xl border border-outline-glass bg-white shadow-lg"
        >
            <li
                v-if="props.loading && filteredItems.length === 0"
                class="px-3 py-2 text-xs text-on-surface-variant"
            >
                {{ props.loadingText }}
            </li>
            <li
                v-else-if="filteredItems.length === 0"
                class="px-3 py-2 text-xs text-on-surface-variant"
            >
                {{ props.noResultsText }}
            </li>
            <li
                v-for="(item, index) in filteredItems"
                :id="`${inputId}-option-${index}`"
                :key="item.id"
                role="option"
                :aria-selected="index === highlightedIndex"
                :class="
                    cn(
                        'cursor-pointer px-3 py-2 text-xs transition',
                        index === highlightedIndex
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface hover:bg-surface-container-low',
                    )
                "
                @mousedown.prevent="selectItem(item)"
                @mouseenter="highlightedIndex = index"
            >
                <div class="font-semibold">{{ item.title }}</div>
                <div
                    v-if="item.sku"
                    class="font-mono text-[10px] text-on-surface-variant"
                >
                    {{ item.sku }}
                </div>
            </li>
        </ul>
    </Teleport>
</template>
