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

type LoginFields = {
    email: string;
    password: string;
};

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<LoginFields>({
    email: '',
    password: '',
});

function submit(): void {
    form.post(route('login.store'));
}
</script>

<template>
    <AuthLayout
        :title="t('auth.login.title')"
        :subtitle="t('auth.login.subtitle')"
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

            <div class="space-y-2">
                <Label for="password">{{ t('fields.password') }}</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
                <div class="flex items-start justify-between gap-3">
                    <FieldError :message="form.errors.password" />
                    <Link
                        :href="route('forgot-password.show')"
                        class="shrink-0 text-xs font-semibold text-primary hover:text-primary-container"
                        >{{ t('auth.login.forgot_link') }}</Link
                    >
                </div>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">{{
                t('auth.login.submit')
            }}</Button>
        </form>
    </AuthLayout>
</template>
