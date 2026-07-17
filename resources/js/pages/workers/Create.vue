<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type WorkerFields = {
    first_name: string;
    last_name: string;
    hourly_rate: string;
};

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<WorkerFields>({
    first_name: '',
    last_name: '',
    hourly_rate: '',
});

function submit(): void {
    form.post(route('workers.store'));
}
</script>

<template>
    <AppLayout :title="t('workers.title_create')">
        <Head :title="t('workers.title_create')" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('workers.title_create') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('workers.subtitle_create') }}
                </p>
            </header>

            <Card padded>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="first_name" :required="true">{{
                                t('workers.columns.first_name')
                            }}</Label>
                            <Input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                required
                            />
                            <FieldError :message="form.errors.first_name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="last_name" :required="true">{{
                                t('workers.columns.last_name')
                            }}</Label>
                            <Input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                required
                            />
                            <FieldError :message="form.errors.last_name" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="hourly_rate" :required="true">{{
                            t('workers.columns.hourly_rate')
                        }}</Label>
                        <Input
                            id="hourly_rate"
                            v-model="form.hourly_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                        <FieldError :message="form.errors.hourly_rate" />
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('workers.index')">
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
