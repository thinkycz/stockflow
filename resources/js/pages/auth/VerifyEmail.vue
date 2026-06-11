<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const form = useForm({});
const { t } = useI18n();

useBoundLocale();

function submit(): void {
    form.post('/verify-email');
}
</script>
<template>
    <AppLayout :title="t('auth.verify.title')">
        <section
            class="max-w-xl rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <p
                class="max-w-xl text-xs leading-relaxed text-on-surface-variant font-medium"
            >
                {{ t('auth.verify.description') }}
            </p>

            <Button class="mt-5" :disabled="form.processing" @click="submit">{{
                t('auth.verify.submit')
            }}</Button>
        </section>
    </AppLayout>
</template>
