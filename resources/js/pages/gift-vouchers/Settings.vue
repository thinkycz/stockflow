<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Textarea from '@/components/ui/Textarea.vue';
import BackLink from '@/components/ui/BackLink.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
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

const brandingForm = useForm<{
    public_name: string;
    message: string;
    logo: File | null;
    remove_logo: boolean;
}>({
    public_name: props.setting?.public_name ?? '',
    message: props.setting?.message ?? '',
    logo: null,
    remove_logo: false,
});
function saveBranding(): void {
    brandingForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('gift-voucher-settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
}

function chooseLogo(event: Event): void {
    brandingForm.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (brandingForm.logo !== null) brandingForm.remove_logo = false;
}
</script>
<template>
    <AppLayout :title="t('gift_vouchers.settings.title')">
        <div class="flex flex-col gap-6">
            <BackLink v-if="is_admin" :href="route('gift-vouchers.index')">{{
                t('gift_vouchers.back_to_overview')
            }}</BackLink>
            <PageHeader :title="t('gift_vouchers.settings.title')" />
            <section class="mx-auto w-full max-w-2xl">
                <Card class="space-y-6">
                    <div>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('gift_vouchers.settings.description') }}
                        </p>
                    </div>
                    <form class="space-y-5" @submit.prevent="saveBranding">
                        <div class="space-y-2">
                            <Label for="voucher_public_name" :required="true">
                                {{ t('gift_vouchers.settings.public_name') }}
                            </Label>
                            <Input
                                id="voucher_public_name"
                                v-model="brandingForm.public_name"
                                required
                            />
                            <FieldError
                                :message="brandingForm.errors.public_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="voucher_message">
                                {{ t('gift_vouchers.settings.message') }}
                            </Label>
                            <Textarea
                                id="voucher_message"
                                v-model="brandingForm.message"
                                :maxlength="240"
                                :rows="3"
                            />
                            <FieldError
                                :message="brandingForm.errors.message"
                            />
                        </div>
                        <div class="space-y-3">
                            <Label for="voucher_logo">
                                {{ t('gift_vouchers.settings.logo') }}
                            </Label>
                            <div
                                v-if="setting?.logo"
                                class="flex items-center gap-4 rounded-xl border border-outline-glass bg-surface-container-low p-4"
                            >
                                <img
                                    :src="setting.logo"
                                    :alt="setting.public_name"
                                    class="h-14 max-w-40 object-contain"
                                />
                                <Button
                                    variant="ghost"
                                    size="compact"
                                    @click="brandingForm.remove_logo = true"
                                >
                                    {{ t('common.delete') }}
                                </Button>
                            </div>
                            <Input
                                id="voucher_logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full rounded-xl border border-outline-glass bg-white p-2 text-xs"
                                @change="chooseLogo"
                            />
                            <FieldError :message="brandingForm.errors.logo" />
                        </div>
                        <div
                            class="flex justify-end border-t border-outline-glass pt-5"
                        >
                            <Button
                                type="submit"
                                :disabled="brandingForm.processing"
                            >
                                {{ t('common.save') }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
