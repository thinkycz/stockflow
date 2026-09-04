<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import BackLink from '@/components/ui/BackLink.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
defineProps<{
    is_admin: boolean;
    setting: {
        public_name: string;
        message: string | null;
        logo: string | null;
    } | null;
}>();

const { t } = useI18n();
useBoundLocale();
const route = useRoute();

const issueForm = useForm({
    quantity: 10,
    amount: '',
    expires_on: '',
    branding: '',
});
function issue(): void {
    issueForm.post(route('gift-voucher-batches.store'));
}
</script>
<template>
    <AppLayout :title="t('gift_vouchers.issue.title')">
        <div class="flex flex-col gap-6">
            <BackLink v-if="is_admin" :href="route('gift-vouchers.index')">{{
                t('gift_vouchers.back_to_overview')
            }}</BackLink>
            <PageHeader :title="t('gift_vouchers.issue.title')" />
            <section class="mx-auto w-full max-w-2xl">
                <Card class="space-y-6">
                    <div>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('gift_vouchers.issue.description') }}
                        </p>
                    </div>
                    <div
                        v-if="setting === null"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {{ t('gift_vouchers.issue.branding_required') }}
                        <Link :href="route('gift-voucher-settings.edit')">
                            <Button
                                type="button"
                                variant="ghost"
                                size="compact"
                                class="ml-1 underline"
                            >
                                {{ t('gift_vouchers.tabs.settings') }}
                            </Button>
                        </Link>
                    </div>
                    <form class="space-y-5" @submit.prevent="issue">
                        <div class="space-y-2">
                            <Label for="voucher_quantity" :required="true">
                                {{ t('gift_vouchers.issue.quantity') }}
                            </Label>
                            <div class="flex gap-2">
                                <Button
                                    v-for="quick in [10, 20]"
                                    :key="quick"
                                    type="button"
                                    variant="secondary"
                                    :class="
                                        issueForm.quantity === quick
                                            ? 'border-primary bg-primary text-white'
                                            : 'border-outline-glass bg-white'
                                    "
                                    @click="issueForm.quantity = quick"
                                >
                                    {{ quick }}
                                </Button>
                                <Input
                                    id="voucher_quantity"
                                    v-model="issueForm.quantity"
                                    type="number"
                                    min="1"
                                    max="100"
                                    required
                                />
                            </div>
                            <FieldError :message="issueForm.errors.quantity" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="voucher_amount" :required="true">
                                    {{ t('gift_vouchers.issue.amount') }}
                                </Label>
                                <Input
                                    id="voucher_amount"
                                    v-model="issueForm.amount"
                                    type="number"
                                    min="0.01"
                                    max="999999.99"
                                    step="0.01"
                                    required
                                />
                                <FieldError
                                    :message="issueForm.errors.amount"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="voucher_expires">
                                    {{ t('gift_vouchers.issue.expires_on') }}
                                </Label>
                                <Input
                                    id="voucher_expires"
                                    v-model="issueForm.expires_on"
                                    type="date"
                                />
                                <FieldError
                                    :message="issueForm.errors.expires_on"
                                />
                            </div>
                        </div>
                        <FieldError :message="issueForm.errors.branding" />
                        <div
                            class="flex justify-end border-t border-outline-glass pt-5"
                        >
                            <Button
                                type="submit"
                                :disabled="
                                    issueForm.processing || setting === null
                                "
                            >
                                <Sparkles :size="15" />
                                {{ t('gift_vouchers.issue.submit') }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
