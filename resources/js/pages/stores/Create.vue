<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, reactive, ref } from 'vue';
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
    is_warehouse: boolean;
};

const { t } = useI18n();

useBoundLocale();

const form = reactive({
    name: '',
    address: '',
    status: 'active',
    notes: '',
    is_warehouse: false,
});

const processing = ref(false);
const page = usePage<{ errors?: Partial<Record<keyof StoreFields, string>> }>();
const errors = computed<Partial<Record<keyof StoreFields, string>>>(
    () =>
        (page.props.errors ?? {}) as Partial<Record<keyof StoreFields, string>>,
);

function submit(): void {
    processing.value = true;
    router.post(
        '/stores',
        { ...form },
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :title="t('stores.title_create')">
        <Head :title="t('stores.title_create')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('stores.title_create') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('stores.subtitle_create') }}
                </p>
            </header>

            <Card padded>
                <form @submit.prevent="submit" class="space-y-5">
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
                        <FieldError :message="errors.name" />
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
                        <FieldError :message="errors.address" />
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
                        <FieldError :message="errors.status" />
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">{{
                            t('stores.columns.notes')
                        }}</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-xs text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
                        ></textarea>
                        <FieldError :message="errors.notes" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_warehouse"
                            v-model="form.is_warehouse"
                            type="checkbox"
                            class="size-4 rounded border-outline-glass text-primary focus:ring-primary/20"
                        />
                        <Label for="is_warehouse">{{
                            t('stores.columns.is_warehouse')
                        }}</Label>
                    </div>
                    <FieldError :message="errors.is_warehouse" />

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link href="/stores">
                            <Button variant="secondary" type="button">
                                {{ t('common.cancel') }}
                            </Button>
                        </Link>
                        <Button type="submit" :disabled="processing">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
