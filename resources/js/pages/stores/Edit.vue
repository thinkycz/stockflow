<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type StoreFields = {
    name: string;
    address: string;
    status: string;
    notes: string;
    slack_channel: string;
    is_warehouse: boolean;
};

const props = defineProps<{
    store: {
        id: number;
        name: string;
        address: string | null;
        status: 'active' | 'inactive';
        notes: string | null;
        slack_channel: string | null;
        is_warehouse: boolean;
    };
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<StoreFields>({
    name: props.store.name,
    address: props.store.address ?? '',
    status: props.store.status,
    notes: props.store.notes ?? '',
    slack_channel: props.store.slack_channel ?? '',
    is_warehouse: props.store.is_warehouse,
});

function submit(): void {
    form.put(route('stores.update', props.store.id));
}
</script>

<template>
    <AppLayout :title="t('stores.title_edit')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <PageHeader
                :title="t('stores.title_edit')"
                :subtitle="t('stores.subtitle_edit')"
            />

            <Card padded>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name" :required="true">{{
                            t('stores.columns.name')
                        }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                        />
                        <FieldError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="address">{{
                            t('stores.columns.address')
                        }}</Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            type="text"
                        />
                        <FieldError :message="form.errors.address" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status" :required="true">{{
                            t('stores.columns.status')
                        }}</Label>
                        <Select
                            id="status"
                            v-model="form.status"
                            :options="[
                                {
                                    value: 'active',
                                    label: t('stores.status.active'),
                                },
                                {
                                    value: 'inactive',
                                    label: t('stores.status.inactive'),
                                },
                            ]"
                        />
                        <FieldError :message="form.errors.status" />
                    </div>

                    <div class="space-y-2">
                        <Label for="slack_channel">{{
                            t('stores.columns.slack_channel')
                        }}</Label>
                        <Input
                            id="slack_channel"
                            v-model="form.slack_channel"
                            type="text"
                            maxlength="100"
                            :placeholder="t('stores.slack_channel_placeholder')"
                        />
                        <p class="text-xs text-on-surface-variant">
                            {{ t('stores.slack_channel_help') }}
                        </p>
                        <FieldError :message="form.errors.slack_channel" />
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">{{
                            t('stores.columns.notes')
                        }}</Label>
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            :rows="4"
                            :invalid="Boolean(form.errors.notes)"
                            described-by="notes-error"
                        />
                        <FieldError
                            id="notes-error"
                            :message="form.errors.notes"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_warehouse"
                            v-model="form.is_warehouse"
                        />
                        <Label for="is_warehouse">{{
                            t('stores.columns.is_warehouse')
                        }}</Label>
                    </div>
                    <FieldError :message="form.errors.is_warehouse" />

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('stores.show', store.id)">
                            <Button variant="secondary" type="button">
                                {{ t('common.cancel') }}
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :loading-label="t('common.saving')"
                        >
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
