import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleCheck,
    Info,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';
import { cn } from '@/lib/utils';

export type NoticeboardCard = {
    id: number;
    body_html: string;
    label: 'information' | 'important' | 'task' | 'event';
    color: 'yellow' | 'pink' | 'blue' | 'green' | 'purple';
    size: 'small' | 'medium' | 'large';
    image_url: string | null;
    expires_on: string | null;
    version: number;
};

export type NoticeboardPayload = {
    cards: NoticeboardCard[];
    filters: {
        status: 'active' | 'expired' | 'trash';
        label: string | null;
        search: string;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    labels: NoticeboardCard['label'][];
    colors: NoticeboardCard['color'][];
    sizes: NoticeboardCard['size'][];
    can_view_trash: boolean;
};
export type NoticeboardProps = {
    noticeboard: NoticeboardPayload;
    activeStore: { id: number; name: string } | null;
};

export function useNoticeboard(props: NoticeboardProps) {
    const { t } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const formOpen = ref(false);

    const editingCard = ref<NoticeboardCard | null>(null);

    const search = ref(props.noticeboard.filters.search);

    const label = ref(props.noticeboard.filters.label ?? '');

    const selectedImageUrl = ref<string | null>(null);

    let searchTimer: ReturnType<typeof setTimeout> | null = null;

    const form = useForm({
        body_html: '<p></p>',
        label: 'information',
        color: 'yellow',
        size: 'medium',
        expires_on: '',
        image: null as File | null,
        remove_image: false,
        lock_version: 1,
    });

    const labelOptions = computed(() => [
        { value: '', label: t('noticeboard.filters.all_labels') },
        ...props.noticeboard.labels.map((value) => ({
            value,
            label: t(`noticeboard.labels.${value}`),
        })),
    ]);

    const statusTabs = computed(() => [
        {
            value: 'active',
            label: t('noticeboard.status.active'),
        },
        {
            value: 'expired',
            label: t('noticeboard.status.expired'),
        },
        ...(props.noticeboard.can_view_trash
            ? [
                  {
                      value: 'trash',
                      label: t('noticeboard.status.trash'),
                      icon: Trash2,
                  },
              ]
            : []),
    ]);

    watch(
        () => props.noticeboard.filters,
        (filters) => {
            search.value = filters.search;
            label.value = filters.label ?? '';
        },
    );

    watch(search, () => {
        if (searchTimer !== null) clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 300);
    });

    watch(label, applyFilters);

    onBeforeUnmount(() => {
        if (searchTimer !== null) clearTimeout(searchTimer);
        revokeSelectedImage();
    });

    function query(status = props.noticeboard.filters.status) {
        return {
            status,
            label: label.value || undefined,
            search: search.value || undefined,
        };
    }

    function applyFilters(): void {
        router.get(route('dashboard'), query(), {
            preserveState: true,
            preserveScroll: true,
            only: ['noticeboard'],
        });
    }

    function changeStatus(status: 'active' | 'expired' | 'trash'): void {
        router.get(route('dashboard'), query(status), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function openCreate(): void {
        editingCard.value = null;
        form.reset();
        form.clearErrors();
        form.body_html = '<p></p>';
        formOpen.value = true;
    }

    function openEdit(card: NoticeboardCard): void {
        editingCard.value = card;
        form.clearErrors();
        form.body_html = card.body_html;
        form.label = card.label;
        form.color = card.color;
        form.size = card.size;
        form.expires_on = card.expires_on ?? '';
        form.image = null;
        form.remove_image = false;
        form.lock_version = card.version;
        formOpen.value = true;
    }

    function closeForm(): void {
        formOpen.value = false;
        revokeSelectedImage();
    }

    function selectImage(event: Event): void {
        revokeSelectedImage();
        const file = (event.target as HTMLInputElement).files?.[0] ?? null;
        form.image = file;
        form.remove_image = false;
        selectedImageUrl.value = file ? URL.createObjectURL(file) : null;
    }

    function revokeSelectedImage(): void {
        if (selectedImageUrl.value) URL.revokeObjectURL(selectedImageUrl.value);
        selectedImageUrl.value = null;
    }

    function removeImage(): void {
        form.image = null;
        form.remove_image = true;
        revokeSelectedImage();
    }

    function submit(): void {
        const current = editingCard.value;
        form.transform((data) =>
            current === null ? data : { ...data, _method: 'put' },
        ).post(
            current === null
                ? route('noticeboard-cards.store')
                : route('noticeboard-cards.update', current.id),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: closeForm,
            },
        );
    }

    async function trash(card: NoticeboardCard): Promise<void> {
        if (
            !(await dialog.confirm({
                title: `${t('common.delete')} #${card.id}`,
                message: t('noticeboard.confirm_trash'),
                confirmLabel: t('common.delete'),
                variant: 'warning',
            }))
        )
            return;
        router.delete(
            route('noticeboard-cards.destroy', card.id),
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    function restore(card: NoticeboardCard): void {
        router.post(
            route('noticeboard-cards.restore', card.id),
            {},
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    async function forceDestroy(card: NoticeboardCard): Promise<void> {
        const token = t('common.confirmation_token');
        if (
            !(await dialog.confirm({
                title: `${t('noticeboard.force_delete')} #${card.id}`,
                message: t('noticeboard.confirm_force_delete'),
                confirmLabel: t('noticeboard.force_delete'),
                variant: 'danger',
                verification: {
                    label: t('common.type_to_confirm', { value: token }),
                    expected: token,
                },
            }))
        )
            return;
        router.delete(
            route('noticeboard-cards.force-destroy', card.id),
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    function labelIcon(labelValue: NoticeboardCard['label']) {
        return {
            information: Info,
            important: TriangleAlert,
            task: CircleCheck,
            event: CalendarDays,
        }[labelValue];
    }

    function colorSwatchClass(color: NoticeboardCard['color']): string {
        return {
            yellow: 'bg-amber-300',
            pink: 'bg-pink-300',
            blue: 'bg-sky-300',
            green: 'bg-emerald-300',
            purple: 'bg-violet-300',
        }[color];
    }

    function cardClass(
        color: NoticeboardCard['color'],
        size: NoticeboardCard['size'],
    ): string {
        return cn(
            'group relative flex flex-col overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:border-primary/25 hover:shadow-md',
            {
                yellow: 'border-amber-200/80 bg-amber-50',
                pink: 'border-pink-200/80 bg-pink-50',
                blue: 'border-sky-200/80 bg-sky-50',
                green: 'border-emerald-200/80 bg-emerald-50',
                purple: 'border-violet-200/80 bg-violet-50',
            }[color],
            {
                small: 'min-h-48',
                medium: 'min-h-60 sm:col-span-2',
                large: 'min-h-72 sm:col-span-2 xl:col-span-4',
            }[size],
        );
    }
    return {
        t,
        route,
        formOpen,
        editingCard,
        search,
        label,
        selectedImageUrl,
        form,
        labelOptions,
        statusTabs,
        query,
        changeStatus,
        openCreate,
        openEdit,
        closeForm,
        selectImage,
        removeImage,
        submit,
        trash,
        restore,
        forceDestroy,
        labelIcon,
        colorSwatchClass,
        cardClass,
    };
}
