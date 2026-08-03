<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type WorkerFields = {
    first_name: string;
    last_name: string;
    hourly_rate: string;
    attendance_rating_enabled: boolean;
};

const props = defineProps<{
    worker: {
        id: number;
        first_name: string;
        last_name: string;
        hourly_rate: number;
        attendance_rating_enabled: boolean;
    };
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<WorkerFields>({
    first_name: props.worker.first_name,
    last_name: props.worker.last_name,
    hourly_rate: String(props.worker.hourly_rate),
    attendance_rating_enabled: props.worker.attendance_rating_enabled,
});

function submit(): void {
    form.put(route('workers.update', props.worker.id));
}
</script>

<template>
    <AppLayout :title="t('workers.title_edit')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <PageHeader
                :title="t('workers.title_edit')"
                :subtitle="t('workers.subtitle_edit')"
            />

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

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="attendance_rating_enabled"
                                v-model="form.attendance_rating_enabled"
                            />
                            <Label for="attendance_rating_enabled">{{
                                t('workers.attendance_rating_enabled')
                            }}</Label>
                        </div>
                        <p class="text-xs text-on-surface-variant">
                            {{ t('workers.attendance_rating_help') }}
                        </p>
                        <FieldError
                            :message="form.errors.attendance_rating_enabled"
                        />
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-outline-glass pt-4"
                    >
                        <Link :href="route('workers.index')">
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
