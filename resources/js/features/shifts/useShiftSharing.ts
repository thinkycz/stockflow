import { router, useForm } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type { ShiftShareLink } from './scheduling-types';

export function useShiftSharing(
    props: { store: { id: number } | null },
    month: Ref<number>,
    year: Ref<number>,
) {
    const route = useRoute();
    const dialog = useDialog();
    const { t } = useI18n();
    // --- Public shift links ---

    const shareLinksModalOpen = ref<boolean>(false);
    const copyingShareLinkId = ref<number | null>(null);
    const copiedShareLinkId = ref<number | null>(null);
    const shareLinkError = ref<string>('');

    type ShareLinkForm = {
        name: string;
    };

    const shareLinkForm = useForm<ShareLinkForm>({ name: '' });

    function openShareLinksModal(): void {
        shareLinkError.value = '';
        copiedShareLinkId.value = null;
        shareLinksModalOpen.value = true;
    }

    function closeShareLinksModal(): void {
        shareLinksModalOpen.value = false;
        shareLinkForm.reset();
        shareLinkForm.clearErrors();
        shareLinkError.value = '';
        copiedShareLinkId.value = null;
    }

    function submitShareLink(): void {
        if (props.store === null) return;

        shareLinkForm.post(
            route('shift-share-links.store', {
                store_id: props.store.id,
                month: month.value,
                year: year.value,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => shareLinkForm.reset(),
            },
        );
    }

    async function copyShareLink(link: ShiftShareLink): Promise<void> {
        copyingShareLinkId.value = link.id;
        copiedShareLinkId.value = null;
        shareLinkError.value = '';

        try {
            await copyText(link.url);
            copiedShareLinkId.value = link.id;
        } catch {
            shareLinkError.value = t('shifts.public_links.copy_error');
        } finally {
            copyingShareLinkId.value = null;
        }
    }

    async function deleteShareLink(link: ShiftShareLink): Promise<void> {
        if (props.store === null) return;

        if (
            !(await dialog.confirm({
                title: `${t('common.delete')}: ${link.name}`,
                message: t('shifts.public_links.confirm_delete'),
                confirmLabel: t('common.delete'),
                variant: 'danger',
            }))
        ) {
            return;
        }

        router.delete(
            route('shift-share-links.destroy', {
                shiftShareLink: link.id,
                store_id: props.store.id,
                month: month.value,
                year: year.value,
            }),
            withActionErrorToast({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    }

    return {
        shareLinksModalOpen,
        copyingShareLinkId,
        copiedShareLinkId,
        shareLinkError,
        shareLinkForm,
        openShareLinksModal,
        closeShareLinksModal,
        submitShareLink,
        copyShareLink,
        deleteShareLink,
    };
}

async function copyText(value: string): Promise<void> {
    if (navigator.clipboard !== undefined) {
        try {
            await navigator.clipboard.writeText(value);
            return;
        } catch {
            // Fall back for browsers that expose Clipboard API but deny it.
        }
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();

    const copied = document.execCommand('copy');
    input.remove();

    if (!copied) {
        throw new Error('Clipboard copy failed.');
    }
}
