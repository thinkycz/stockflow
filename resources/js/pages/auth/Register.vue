<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
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

const route = useRoute();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);

const form = useForm<RegisterFields>({
    email: '',
    password: '',
    password_confirmation: '',
    locale: app.value.locale ?? 'en',
});

function submit(): void {
    form.post(route('register.store'), {
        onError: (): void => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <AuthLayout
        :title="t('auth.register.title')"
        :subtitle="t('auth.register.subtitle')"
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
                    autocomplete="new-password"
                    required
                />
                <FieldError :message="form.errors.password" />
            </div>

            <div class="space-y-2">
                <Label for="password_confirmation">{{
                    t('fields.password_confirmation')
                }}</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError :message="form.errors.password_confirmation" />
            </div>

            <div class="space-y-2">
                <Label for="locale">{{ t('fields.locale') }}</Label>
                <Select
                    id="locale"
                    v-model="form.locale"
                    :options="localeOptions"
                    required
                />
                <FieldError :message="form.errors.locale" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">{{
                t('auth.register.submit')
            }}</Button>
        </form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            {{ t('auth.register.login_prompt') }}
            <Link
                :href="route('login.show')"
                class="ml-1 font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.title') }}</Link
            >
        </p>
    </AuthLayout>
</template>
