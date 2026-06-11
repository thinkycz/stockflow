<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import { useI18n } from 'vue-i18n';

type Status = 'in_stock' | 'low_stock' | 'out_of_stock';

const props = defineProps<{
    status: Status;
}>();

const { t } = useI18n();

const variant = computed<'success' | 'warning' | 'danger'>(() => {
    switch (props.status) {
        case 'in_stock':
            return 'success';
        case 'low_stock':
            return 'warning';
        case 'out_of_stock':
            return 'danger';
    }
});

const label = computed<string>(() => {
    switch (props.status) {
        case 'in_stock':
            return t('items.status.in_stock');
        case 'low_stock':
            return t('items.status.low_stock');
        case 'out_of_stock':
            return t('items.status.out_of_stock');
    }
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
