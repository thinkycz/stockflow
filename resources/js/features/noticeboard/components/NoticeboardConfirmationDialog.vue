<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import NoticeboardCardContent from '@/features/noticeboard/components/NoticeboardCardContent.vue';
import { noticeboardCardClass } from '@/features/noticeboard/useNoticeboard';
import { useNoticeboardConfirmation } from '@/features/noticeboard/useNoticeboardConfirmation';
import { formatDate } from '@/lib/format';
import type { NoticeboardConfirmation } from '@/types/noticeboard';

const props = defineProps<{ confirmation: NoticeboardConfirmation | null }>();
const { t } = useI18n();
const { checked, form, errors, allSelected, selectedIds, submit } =
    useNoticeboardConfirmation(props);
</script>

<template>
    <Modal
        :open="confirmation !== null"
        :title="t('noticeboard.confirmation.title')"
        :dismissible="false"
        :close-on-backdrop="false"
        size="lg"
        body-class="max-h-[60vh] overflow-y-auto"
    >
        <form
            v-if="confirmation"
            id="noticeboard-confirmation-form"
            class="space-y-4"
            @submit.prevent="submit"
        >
            <p class="text-sm text-on-surface-variant">
                {{
                    t('noticeboard.confirmation.description', {
                        name: confirmation.worker.name,
                        date: formatDate(confirmation.date),
                    })
                }}
            </p>
            <article
                v-for="item in confirmation.items"
                :key="item.id"
                :class="noticeboardCardClass(item.color, 'small')"
            >
                <p class="text-xs font-semibold text-slate-600">
                    {{ t(`noticeboard.labels.${item.label}`) }}
                </p>
                <NoticeboardCardContent :card="item" />
                <div
                    class="mt-5 flex items-center gap-3 border-t border-black/5 pt-4"
                >
                    <Checkbox
                        :id="`noticeboard-read-${item.id}`"
                        v-model="checked[item.id]"
                        :disabled="form.processing"
                    />
                    <Label :for="`noticeboard-read-${item.id}`">{{
                        t('noticeboard.confirmation.read')
                    }}</Label>
                </div>
            </article>
            <FieldError :message="errors.item_ids ?? errors.confirmation" />
        </form>
        <template #footer>
            <span
                v-if="confirmation"
                class="mr-auto text-sm text-on-surface-variant"
            >
                {{
                    t('noticeboard.confirmation.progress', {
                        selected: selectedIds.length,
                        total: confirmation.items.length,
                    })
                }}
            </span>
            <Button
                type="submit"
                form="noticeboard-confirmation-form"
                :disabled="!allSelected || form.processing"
                :loading="form.processing"
                :loading-label="t('common.saving')"
                >{{ t('noticeboard.confirmation.confirm') }}</Button
            >
        </template>
    </Modal>
</template>
