<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
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
import PageHeader from '@/components/ui/PageHeader.vue';
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

type SlackFields = {
    company_slack_channel: string;
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

const slackForm = useForm<SlackFields>({
    company_slack_channel: user?.value?.company_slack_channel ?? '',
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

function submitSlack(): void {
    slackForm.post(route('settings.slack.update'));
}
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <PageHeader
                :title="t('settings.title')"
                :subtitle="t('settings.subtitle')"
            />

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
                    <CardTitle>{{ t('settings.slack.title') }}</CardTitle>
                </CardHeader>
                <form class="space-y-5" @submit.prevent="submitSlack">
                    <p class="text-sm text-on-surface-variant">
                        {{ t('settings.slack.subtitle') }}
                    </p>
                    <div class="space-y-2">
                        <Label for="company_slack_channel">
                            {{ t('settings.slack.channel') }}
                        </Label>
                        <Input
                            id="company_slack_channel"
                            v-model="slackForm.company_slack_channel"
                            type="text"
                            maxlength="100"
                            placeholder="#company-operations"
                        />
                        <FieldError
                            :message="slackForm.errors.company_slack_channel"
                        />
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-outline-glass pt-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            :href="route('settings.slack-digests.index')"
                            class="text-xs font-semibold text-primary hover:underline"
                        >
                            {{ t('settings.slack.digest_archive.open') }}
                        </Link>
                        <Button type="submit" :disabled="slackForm.processing">
                            {{ t('settings.slack.submit') }}
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
