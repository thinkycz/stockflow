<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
    baseUrl: string;
    queryParams?: Record<string, string | number | undefined>;
}>();

const linkClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-outline-glass bg-white text-xs font-semibold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white';

const buildHref = (page: number): string => {
    const params = new URLSearchParams();
    if (props.queryParams) {
        for (const [k, v] of Object.entries(props.queryParams)) {
            if (v !== undefined && v !== '' && v !== null) {
                params.set(k, String(v));
            }
        }
    }
    if (page > 1) {
        params.set('page', String(page));
    }
    const query = params.toString();
    return query ? `${props.baseUrl}?${query}` : props.baseUrl;
};

const window = computed<number[]>(() => {
    const out: number[] = [];
    const start = Math.max(1, props.currentPage - 2);
    const end = Math.min(props.lastPage, start + 4);
    for (let i = start; i <= end; i++) {
        out.push(i);
    }
    return out;
});
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex flex-col items-center justify-between gap-3 border-t border-outline-glass pt-4 sm:flex-row"
    >
        <p class="text-xs font-medium text-on-surface-variant">
            {{ total }} {{ $t('common.results') }}
        </p>
        <div class="flex items-center gap-1.5">
            <Link
                v-if="currentPage > 1"
                :href="buildHref(currentPage - 1)"
                :class="cn(linkClass)"
                :aria-label="$t('common.previous')"
            >
                <ChevronLeft :size="14" />
            </Link>
            <Link
                v-for="page in window"
                :key="page"
                :href="buildHref(page)"
                :aria-current="page === currentPage ? 'page' : undefined"
                :class="
                    cn(
                        linkClass,
                        page === currentPage
                            ? 'border-primary bg-primary text-white hover:bg-primary'
                            : '',
                    )
                "
            >
                {{ page }}
            </Link>
            <Link
                v-if="currentPage < lastPage"
                :href="buildHref(currentPage + 1)"
                :class="cn(linkClass)"
                :aria-label="$t('common.next')"
            >
                <ChevronRight :size="14" />
            </Link>
        </div>
    </div>
</template>
