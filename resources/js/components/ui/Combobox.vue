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

watch(isOpen, (open) => {
    if (open) {
        startTracking();
    } else {
        stopTracking();
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

// On iOS Safari, `getBoundingClientRect()` returns coordinates relative to the
// *layout viewport* (which doesn't shrink when the keyboard opens), but
// `position: fixed` elements are placed relative to the *visual viewport*
// (which does shrink). When the keyboard is open these two coordinate systems
// diverge, causing the dropdown to appear in the wrong place. We convert
// layout-viewport coordinates to visual-viewport coordinates using
// `visualViewport.offsetTop` / `offsetLeft`. On desktop these offsets are 0,
// so the conversion is a no-op.
function visualViewportOffsetY(): number {
    return window.visualViewport?.offsetTop ?? 0;
}

function visualViewportOffsetX(): number {
    return window.visualViewport?.offsetLeft ?? 0;
}

function updatePosition(): void {
    const input = inputRef.value;
    if (input === null) {
        return;
    }
    const rect = input.getBoundingClientRect();
    const offsetY = visualViewportOffsetY();
    const offsetX = visualViewportOffsetX();
    // Convert layout-viewport coordinates to visual-viewport coordinates so
    // they match what `position: fixed` uses on iOS Safari.
    const inputTop = rect.top - offsetY;
    const inputBottom = rect.bottom - offsetY;
    const inputLeft = rect.left - offsetX;
    const viewportHeight = getViewportHeight();
    const spaceBelow = viewportHeight - inputBottom - 8;
    const spaceAbove = inputTop - 8;
    const maxDropdownHeight = 240;
    const gap = 4;

    if (spaceBelow >= maxDropdownHeight || spaceBelow >= spaceAbove) {
        dropdownStyle.value = {
            top: String(inputBottom + gap) + 'px',
            left: String(inputLeft) + 'px',
            width: String(rect.width) + 'px',
            maxHeight:
                String(Math.max(Math.min(spaceBelow, maxDropdownHeight), 0)) +
                'px',
        };
    } else {
        const availableHeight = Math.max(
            Math.min(spaceAbove, maxDropdownHeight),
            0,
        );
        // Use the dropdown's actual rendered height (not the available space)
        // so it sits flush above the input even when there are only a few
        // options. Falls back to availableHeight on the first frame before
        // the dropdown has measured.
        const renderedHeight = dropdownRef.value?.offsetHeight ?? null;
        const actualHeight =
            renderedHeight !== null
                ? Math.min(renderedHeight, availableHeight)
                : availableHeight;
        dropdownStyle.value = {
            top: String(Math.max(inputTop - gap - actualHeight, 8)) + 'px',
            left: String(inputLeft) + 'px',
            width: String(rect.width) + 'px',
            maxHeight: String(availableHeight) + 'px',
        };
    }
}

// A requestAnimationFrame loop keeps the dropdown glued to the input while
// open. This is the reliable way to handle mobile browsers, which
// asynchronously scroll the focused input into view, animate the keyboard in,
// and (iOS Safari) shift the coordinate system of `position: fixed` elements
// during scroll. Event-based repositioning races these animations and captures
// stale rects, leaving the dropdown floating far from the input. Tracking every
// frame guarantees the dropdown stays adjacent regardless of what the browser
// does, and lets the user scroll freely without the dropdown disappearing.
let rafId: number | null = null;

function trackingFrame(): void {
    if (!isOpen.value) {
        rafId = null;
        return;
    }
    updatePosition();
    rafId = requestAnimationFrame(trackingFrame);
}

function startTracking(): void {
    stopTracking();
    // Measure immediately, then keep tracking every frame.
    updatePosition();
    rafId = requestAnimationFrame(trackingFrame);
}

function stopTracking(): void {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
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

onMounted(() => {
    document.addEventListener('mousedown', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentClick);
    stopTracking();
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
