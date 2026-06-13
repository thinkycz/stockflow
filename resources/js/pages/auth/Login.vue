<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

type LoginFields = {
    email: string;
    password: string;
};

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AuthLayout
        :title="t('auth.login.title')"
        :subtitle="t('auth.login.subtitle')"
    >
        <Form
            v-slot="{ errors, processing }"
            action="/login"
            method="post"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="email">{{ t('fields.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                />
                <FieldError
                    :message="
                        (
                            errors as LoginFields extends object
                                ? LoginFields
                                : never
                        )['email']
                    "
                />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('fields.password') }}</Label>
                    <Link
                        href="/forgot-password"
                        class="text-xs font-semibold text-primary hover:text-primary-container"
                        >{{ t('auth.login.forgot_link') }}</Link
                    >
                </div>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
                <FieldError
                    :message="
                        (
                            errors as LoginFields extends object
                                ? LoginFields
                                : never
                        )['password']
                    "
                />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.login.submit')
            }}</Button>
        </Form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            {{ t('auth.login.register_prompt') }}
            <Link
                href="/register"
                class="ml-1 font-bold text-primary hover:text-primary-container"
                >{{ t('auth.register.title') }}</Link
            >
        </p>
    </AuthLayout>
</template>
