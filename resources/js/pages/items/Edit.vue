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

type ItemFields = {
    title: string;
    sku: string;
    unit: string;
    purchase_price: string;
    description: string;
};

const props = defineProps<{
    item: {
        id: number;
        title: string;
        sku: string | null;
        unit: string | null;
        purchase_price: number;
        description: string | null;
    };
    units: string[];
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<ItemFields>({
    title: props.item.title,
    sku: props.item.sku ?? '',
    unit: props.item.unit ?? '',
    purchase_price: props.item.purchase_price.toFixed(2),
    description: props.item.description ?? '',
});

function submit(): void {
    form.put(route('items.update', props.item.id));
}
</script>

<template>
    <AppLayout :title="t('items.title_edit')">
        <Head :title="t('items.title_edit')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('items.title_edit') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('items.subtitle_edit') }}
                </p>
            </header>

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
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            :aria-invalid="
                                form.errors.description ? 'true' : undefined
                            "
                            aria-describedby="description-error"
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
                        ></textarea>
                        <FieldError
                            id="description-error"
                            :message="form.errors.description"
                        />
                    </div>

                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
                    >
                        {{ t('items.quantity_help') }}
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('items.show', item.id)">
                            <Button variant="secondary" type="button">
                                {{ t('common.cancel') }}
                            </Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
