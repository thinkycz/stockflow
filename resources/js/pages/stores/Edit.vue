<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

type StoreFields = {
    name: string;
    address: string;
    status: string;
    notes: string;
};

defineProps<{
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
                <Form
                    v-slot="{ errors, processing }"
                    :action="`/stores/${store.id}`"
                    method="put"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="name" :required="true">{{
                            t('stores.columns.name')
                        }}</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            :default-value="store.name"
                            required
                        />
                        <FieldError
                            :message="
                                (
                                    errors as StoreFields
                                )['name']
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="address">{{
                            t('stores.columns.address')
                        }}</Label>
                        <Input
                            id="address"
                            name="address"
                            type="text"
                            :default-value="store.address ?? ''"
                        />
                        <FieldError
                            :message="
                                (
                                    errors as StoreFields
                                )['address']
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="status" :required="true">{{
                            t('stores.columns.status')
                        }}</Label>
                        <Select
                            id="status"
                            name="status"
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
                            :default-value="store.status"
                        />
                        <FieldError
                            :message="
                                (
                                    errors as StoreFields
                                )['status']
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">{{
                            t('stores.columns.notes')
                        }}</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            :aria-invalid="
                                (
                                    errors as StoreFields
                                )['notes']
                                    ? 'true'
                                    : undefined
                            "
                            aria-describedby="notes-error"
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
                            :value="store.notes ?? ''"
                        ></textarea>
                        <FieldError
                            id="notes-error"
                            :message="
                                (
                                    errors as StoreFields
                                )['notes']
                            "
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_warehouse"
                            name="is_warehouse"
                            type="checkbox"
                            value="1"
                            :checked="store.is_warehouse"
                            class="size-4 rounded border-outline-glass text-primary focus:ring-primary/20"
                        />
                        <Label for="is_warehouse">{{
                            t('stores.columns.is_warehouse')
                        }}</Label>
                    </div>
                    <FieldError
                        :message="
                            (errors as Partial<Record<string, string>>)[
                                'is_warehouse'
                            ]
                        "
                    />

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="`/stores/${store.id}`">
                            <Button variant="secondary" type="button">
                                {{ t('common.cancel') }}
                            </Button>
                        </Link>
                        <Button type="submit" :disabled="processing">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </Form>
            </Card>
        </div>
    </AppLayout>
</template>
