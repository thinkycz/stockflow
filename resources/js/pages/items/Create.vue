<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type ItemFields = {
    title: string;
    sku: string;
    unit: string;
    purchase_price: string;
    description: string;
};

defineProps<{
    units: string[];
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<ItemFields>({
    title: '',
    sku: '',
    unit: '',
    purchase_price: '',
    description: '',
});

function submit(): void {
    form.post(route('items.store'));
}
</script>

<template>
    <AppLayout :title="t('items.title_create')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <PageHeader
                :title="t('items.title_create')"
                :subtitle="t('items.subtitle_create')"
            />

            <Card padded>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="title" :required="true">{{
                            t('items.columns.title')
                        }}</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            type="text"
                            required
                        />
                        <FieldError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="sku">{{
                                t('items.columns.sku')
                            }}</Label>
                            <Input id="sku" v-model="form.sku" type="text" />
                            <FieldError :message="form.errors.sku" />
                        </div>
                        <div class="space-y-2">
                            <Label for="unit">{{
                                t('items.columns.unit')
                            }}</Label>
                            <Select
                                id="unit"
                                v-model="form.unit"
                                :options="[
                                    { value: '', label: t('items.unit_none') },
                                    ...units.map((u) => ({
                                        value: u,
                                        label: u,
                                    })),
                                ]"
                            />
                            <FieldError :message="form.errors.unit" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="purchase_price" :required="true">{{
                            t('items.columns.price')
                        }}</Label>
                        <Input
                            id="purchase_price"
                            v-model="form.purchase_price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                        <FieldError :message="form.errors.purchase_price" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">{{
                            t('items.columns.description')
                        }}</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            :rows="4"
                            :invalid="Boolean(form.errors.description)"
                            described-by="description-error"
                        />
                        <FieldError
                            id="description-error"
                            :message="form.errors.description"
                        />
                    </div>

                    <Alert variant="warning">
                        {{ t('items.quantity_help') }}
                    </Alert>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('items.index')">
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
