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

type StoreFields = {
    name: string;
    address: string;
    status: string;
    notes: string;
    is_warehouse: boolean;
};

const props = defineProps<{
    store: {
        id: number;
        name: string;
        address: string | null;
        status: 'active' | 'inactive';
        notes: string | null;
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
    is_warehouse: props.store.is_warehouse,
});

function submit(): void {
    form.put(route('stores.update', props.store.id));
}
</script>

<template>
    <AppLayout :title="t('stores.title_edit')">
        <Head :title="t('stores.title_edit')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('stores.title_edit') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('stores.subtitle_edit') }}
                </p>
            </header>

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
                        <Label for="notes">{{
                            t('stores.columns.notes')
                        }}</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            :aria-invalid="
                                form.errors.notes ? 'true' : undefined
                            "
                            aria-describedby="notes-error"
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
                        ></textarea>
                        <FieldError
                            id="notes-error"
                            :message="form.errors.notes"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_warehouse"
                            type="checkbox"
                            value="1"
                            :checked="form.is_warehouse"
                            class="size-4 rounded border-outline-glass text-primary focus:ring-primary/20"
                            @change="
                                form.is_warehouse = (
                                    $event.target as HTMLInputElement
                                ).checked
                            "
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
                        <Button type="submit" :disabled="form.processing">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
