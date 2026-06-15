<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type ResetPasswordFields = {
    email: string;
    token: string;
    password: string;
};

const props = defineProps<{
    email: string;
    token: string;
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<ResetPasswordFields>({
    email: props.email,
    token: props.token,
    password: '',
});

function submit(): void {
    form.post(route('reset-password.store'), {
        onError: (): void => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <AuthLayout
        :title="t('auth.reset.title')"
        :subtitle="t('auth.reset.subtitle')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="email">{{ t('auth.reset.labels.email') }}</Label>
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
                <Label for="token">{{ t('auth.reset.labels.token') }}</Label>
                <Input
                    id="token"
                    v-model="form.token"
                    autocomplete="one-time-code"
                    required
                />
                <FieldError :message="form.errors.token" />
            </div>

            <div class="space-y-2">
                <Label for="password">{{
                    t('auth.reset.labels.new_password')
                }}</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">{{
                t('auth.reset.submit')
            }}</Button>
        </form>
    </AuthLayout>
</template>
