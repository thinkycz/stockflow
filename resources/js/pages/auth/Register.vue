<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

type RegisterFields = {
    email: string;
    password: string;
    password_confirmation: string;
    locale: string;
};

const { app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const locale = ref<string>(app.value.locale ?? 'en');

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
void route; // referenced from the <template>
</script>

<template>
    <AuthLayout
        :title="t('auth.register.title')"
        :subtitle="t('auth.register.subtitle')"
    >
        <Form
            v-slot="{ errors, processing }"
            :action="route('register.store')"
            method="post"
            :reset-on-error="['password', 'password_confirmation']"
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
                            errors as RegisterFields
                        )['email']
                    "
                />
            </div>

            <div class="space-y-2">
                <Label for="password">{{ t('fields.password') }}</Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError
                    :message="
                        (
                            errors as RegisterFields
                        )['password']
                    "
                />
            </div>

            <div class="space-y-2">
                <Label for="password_confirmation">{{
                    t('fields.password_confirmation')
                }}</Label>
                <Input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError
                    :message="
                        (
                            errors as RegisterFields
                        )['password_confirmation']
                    "
                />
            </div>

            <div class="space-y-2">
                <Label for="locale">{{ t('fields.locale') }}</Label>
                <Select
                    id="locale"
                    name="locale"
                    v-model="locale"
                    :options="localeOptions"
                    required
                />
                <FieldError
                    :message="
                        (
                            errors as RegisterFields
                        )['locale']
                    "
                />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.register.submit')
            }}</Button>
        </Form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            {{ t('auth.register.login_prompt') }}
            <Link
                href="route('login.show')"
                class="ml-1 font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.title') }}</Link
            >
        </p>
    </AuthLayout>
</template>
