<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
import { useSharedProps } from '@/composables/useSharedProps';

type ProfileFields = {
    email: string;
    locale: string;
};

type PasswordFields = {
    password: string;
    new_password: string;
};

const { user, app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
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
                <Form
                    v-slot="{ errors, processing }"
                    action="/settings/profile"
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
                            :default-value="user?.email ?? ''"
                            required
                        />
                        <FieldError
                            :message="
                                (
                                    errors as ProfileFields extends object
                                        ? ProfileFields
                                        : never
                                )['email']
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="locale">{{ t('fields.locale') }}</Label>
                        <Select
                            id="locale"
                            name="locale"
                            :options="localeOptions"
                            :default-value="user?.locale ?? app.locale"
                            required
                        />
                        <FieldError
                            :message="
                                (
                                    errors as ProfileFields extends object
                                        ? ProfileFields
                                        : never
                                )['locale']
                            "
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.profile.submit') }}
                        </Button>
                    </div>
                </Form>
            </Card>

            <Card padded>
                <CardHeader>
                    <CardTitle>{{ t('settings.password.title') }}</CardTitle>
                </CardHeader>
                <Form
                    v-slot="{ errors, processing }"
                    action="/settings/password"
                    method="post"
                    :reset-on-success="['password', 'new_password']"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="password">{{
                            t('fields.current_password')
                        }}</Label>
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
                                    errors as PasswordFields extends object
                                        ? PasswordFields
                                        : never
                                )['password']
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="new_password">{{
                            t('fields.new_password')
                        }}</Label>
                        <Input
                            id="new_password"
                            name="new_password"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                        <FieldError
                            :message="
                                (
                                    errors as PasswordFields extends object
                                        ? PasswordFields
                                        : never
                                )['new_password']
                            "
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.password.submit') }}
                        </Button>
                    </div>
                </Form>
            </Card>
        </div>
    </AppLayout>
</template>
