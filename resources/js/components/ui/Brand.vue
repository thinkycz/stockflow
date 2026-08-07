<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';

const props = withDefaults(
    defineProps<{
        href?: string;
        class?: string;
    }>(),
    {
        href: undefined,
        class: '',
    },
);

const route = useRoute();
const { t } = useI18n();

const resolvedHref = computed(() => props.href ?? route('dashboard'));
</script>

<template>
    <Link
        :href="resolvedHref"
        :class="[
            'flex items-center gap-3 font-medium select-none',
            $props.class,
        ]"
    >
        <img
            :src="'/teacha-mark.svg'"
            alt=""
            class="h-9 w-9 shrink-0 object-contain"
        />
        <div class="text-left">
            <h1
                class="mb-0.5 font-heading text-sm font-bold tracking-tight text-on-surface leading-none"
            >
                {{ t('app.name') }}
            </h1>
            <p
                class="font-mono text-[9px] font-semibold tracking-wider text-on-surface-variant uppercase opacity-75 leading-none"
            >
                {{ t('app.tagline') }}
            </p>
        </div>
    </Link>
</template>
