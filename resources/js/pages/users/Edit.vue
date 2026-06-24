<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type StoreOption = {
    id: number;
    name: string;
};

type UserData = {
    id: number;
    email: string;
    is_admin: boolean;
    assigned_store_id: number | null;
};

type Fields = {
    email: string;
    password: string;
    password_confirmation: string;
    assigned_store_id: string;
};

const props = defineProps<{
    user: UserData;
    stores: StoreOption[];
}>();

const { t } = useI18n();
const route = useRoute();

useBoundLocale();

const isSelf = props.user.is_admin;

const form = useForm<Fields>({
    email: props.user.email,
    password: '',
    password_confirmation: '',
    assigned_store_id: props.user.assigned_store_id
        ? String(props.user.assigned_store_id)
        : '',
});

function submit(): void {
    form.put(route('users.update', props.user.id));
}
</script>

<template>
    <AppLayout :title="t('users.edit.title')">
        <Head :title="t('users.edit.title')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('users.edit.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('users.subtitle') }}
                </p>
            </header>

            <Card padded>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="email" :required="true">{{
                            t('users.fields.email')
                        }}</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            required
                        />
                        <FieldError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="password">{{
                                isSelf
                                    ? t('users.fields.password')
                                    : t('users.fields.password_optional')
                            }}</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                            />
                            <FieldError :message="form.errors.password" />
                        </div>
                        <div v-if="form.password !== ''" class="space-y-2">
                            <Label
                                for="password_confirmation"
                                :required="true"
                                >{{
                                    t('users.fields.password_confirmation')
                                }}</Label
                            >
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>

                    <div v-if="!isSelf" class="space-y-2">
                        <Label for="assigned_store_id" :required="true">{{
                            t('users.fields.assigned_store')
                        }}</Label>
                        <Select
                            id="assigned_store_id"
                            v-model="form.assigned_store_id"
                            :options="[
                                {
                                    value: '',
                                    label: t('users.fields.select_store'),
                                },
                                ...stores.map((s) => ({
                                    value: String(s.id),
                                    label: s.name,
                                })),
                            ]"
                            required
                        />
                        <FieldError :message="form.errors.assigned_store_id" />
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('users.index')">
                            <Button variant="secondary" type="button">
                                {{ t('common.cancel') }}
                            </Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing">
                            {{ t('users.edit.submit') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
