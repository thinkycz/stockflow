<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type ForgotPasswordFields = {
    email: string;
};

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<ForgotPasswordFields>({
    email: '',
});

function submit(): void {
    form.post(route('forgot-password.store'));
}
</script>

<template>
    <AuthLayout
        :title="t('auth.forgot.title')"
        :subtitle="t('auth.forgot.subtitle')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="email">{{ t('fields.email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                />
                <FieldError :message="form.errors.email" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">{{
                t('auth.forgot.submit')
            }}</Button>
        </form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            <Link
                :href="route('login.show')"
                class="font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.back_link') }}</Link
            >
        </p>
    </AuthLayout>
</template>
