<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';

type ProfileFields = {
    email: string;
    locale: string;
};

type PasswordFields = {
    password: string;
    new_password: string;
    new_password_confirmation: string;
};

const { user, app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const route = useRoute();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);

const profileForm = useForm<ProfileFields>({
    email: user?.value?.email ?? '',
    locale: user?.value?.locale ?? app.value.locale ?? 'en',
});

const passwordForm = useForm<PasswordFields>({
    password: '',
    new_password: '',
    new_password_confirmation: '',
});

function submitProfile(): void {
    profileForm.post(route('settings.profile.update'));
}

function submitPassword(): void {
    passwordForm.post(route('settings.password.update'), {
        onSuccess: (): void => {
            passwordForm.reset(
                'password',
                'new_password',
                'new_password_confirmation',
            );
        },
    });
}
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <Head :title="t('settings.title')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('settings.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('settings.subtitle') }}
                </p>
            </header>

            <Card padded>
                <CardHeader>
                    <CardTitle>{{ t('settings.profile.title') }}</CardTitle>
                </CardHeader>
                <form class="space-y-5" @submit.prevent="submitProfile">
                    <div class="space-y-2">
                        <Label for="email">{{ t('fields.email') }}</Label>
                        <Input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                        <FieldError :message="profileForm.errors.email" />
                    </div>

                    <div class="space-y-2">
                        <Label for="locale">{{ t('fields.locale') }}</Label>
                        <Select
                            id="locale"
                            v-model="profileForm.locale"
                            :options="localeOptions"
                            required
                        />
                        <FieldError :message="profileForm.errors.locale" />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button
                            type="submit"
                            :disabled="profileForm.processing"
                        >
                            {{ t('settings.profile.submit') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card padded>
                <CardHeader>
                    <CardTitle>{{ t('settings.password.title') }}</CardTitle>
                </CardHeader>
                <form class="space-y-5" @submit.prevent="submitPassword">
                    <div class="space-y-2">
                        <Label for="password">{{
                            t('fields.current_password')
                        }}</Label>
                        <Input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="current-password"
                            required
                        />
                        <FieldError :message="passwordForm.errors.password" />
                    </div>

                    <div class="space-y-2">
                        <Label for="new_password">{{
                            t('fields.new_password')
                        }}</Label>
                        <Input
                            id="new_password"
                            v-model="passwordForm.new_password"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                        <FieldError
                            :message="passwordForm.errors.new_password"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="new_password_confirmation">{{
                            t('fields.new_password_confirmation')
                        }}</Label>
                        <Input
                            id="new_password_confirmation"
                            v-model="passwordForm.new_password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                        <FieldError
                            :message="
                                passwordForm.errors.new_password_confirmation
                            "
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button
                            type="submit"
                            :disabled="passwordForm.processing"
                        >
                            {{ t('settings.password.submit') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
