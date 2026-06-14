<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

type ForgotPasswordFields = {
    email: string;
};

const { t } = useI18n();

useBoundLocale();
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
void route; // referenced from the <template>
</script>

<template>
    <AuthLayout
        :title="t('auth.forgot.title')"
        :subtitle="t('auth.forgot.subtitle')"
    >
        <Form
            v-slot="{ errors, processing }"
            :action="route('forgot-password.store')"
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
                            errors as ForgotPasswordFields
                        )['email']
                    "
                />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.forgot.submit')
            }}</Button>
        </Form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            <Link
                href="route('login.show')"
                class="font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.back_link') }}</Link
            >
        </p>
    </AuthLayout>
</template>
