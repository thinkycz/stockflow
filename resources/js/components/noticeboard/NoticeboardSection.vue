<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    CalendarDays,
    CircleCheck,
    ImagePlus,
    Info,
    Pencil,
    Plus,
    Search,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import RichTextEditor from '@/components/noticeboard/RichTextEditor.vue';
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

const props = defineProps<{
    noticeboard: NoticeboardPayload;
    activeStore: { id: number; name: string } | null;
}>();

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
</script>

<template>
    <section>
        <div
            class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('noticeboard.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{
                        activeStore
                            ? t('noticeboard.subtitle')
                            : t('noticeboard.no_store')
                    }}
                </p>
                <StoreContextIndicator />
            </div>
            <Button :disabled="!activeStore" @click="openCreate">
                <Plus :size="16" />
                {{ t('noticeboard.add') }}
            </Button>
        </div>

        <template v-if="activeStore">
            <div
                class="mb-5 grid gap-3 md:grid-cols-[minmax(14rem,1fr)_13rem_auto]"
            >
                <div class="relative">
                    <Search
                        :size="15"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="t('noticeboard.filters.search')"
                        class="pl-9"
                    />
                </div>
                <Select v-model="label" :options="labelOptions" />
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="status in ['active', 'expired'] as const"
                        :key="status"
                        :variant="
                            noticeboard.filters.status === status
                                ? 'primary'
                                : 'secondary'
                        "
                        @click="changeStatus(status)"
                    >
                        {{ t(`noticeboard.status.${status}`) }}
                    </Button>
                    <Button
                        v-if="noticeboard.can_view_trash"
                        :variant="
                            noticeboard.filters.status === 'trash'
                                ? 'primary'
                                : 'secondary'
                        "
                        @click="changeStatus('trash')"
                    >
                        <Trash2 :size="14" />
                        {{ t('noticeboard.status.trash') }}
                    </Button>
                </div>
            </div>

            <EmptyState
                v-if="noticeboard.cards.length === 0"
                :title="t('noticeboard.empty.title')"
                :description="t('noticeboard.empty.description')"
            >
                <template
                    v-if="noticeboard.filters.status === 'active'"
                    #action
                >
                    <Button @click="openCreate">
                        <Plus :size="14" />
                        {{ t('noticeboard.add') }}
                    </Button>
                </template>
            </EmptyState>

            <div
                v-else
                class="grid items-start gap-5 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    v-for="card in noticeboard.cards"
                    :key="card.id"
                    :class="cardClass(card.color, card.size)"
                    :data-card-color="card.color"
                    :data-card-size="card.size"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/60 px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm"
                            :title="t(`noticeboard.labels.${card.label}`)"
                        >
                            <component
                                :is="labelIcon(card.label)"
                                :size="15"
                                aria-hidden="true"
                            />
                            {{ t(`noticeboard.labels.${card.label}`) }}
                        </span>
                        <div
                            class="flex opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            @click.stop
                        >
                            <template
                                v-if="noticeboard.filters.status === 'trash'"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('noticeboard.restore')"
                                    @click="restore(card)"
                                >
                                    <ArchiveRestore :size="15" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-error-red hover:text-error-red"
                                    :aria-label="t('noticeboard.force_delete')"
                                    @click="forceDestroy(card)"
                                >
                                    <Trash2 :size="15" />
                                </Button>
                            </template>
                            <template v-else>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('common.edit')"
                                    @click="openEdit(card)"
                                >
                                    <Pencil :size="15" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-error-red hover:text-error-red"
                                    :aria-label="t('common.delete')"
                                    @click="trash(card)"
                                >
                                    <Trash2 :size="15" />
                                </Button>
                            </template>
                        </div>
                    </div>
                    <img
                        v-if="card.image_url"
                        :src="card.image_url"
                        :alt="t(`noticeboard.labels.${card.label}`)"
                        class="mt-4 h-32 w-full rounded-xl object-cover"
                    />
                    <div
                        class="noticeboard-rich-text mt-4 max-h-48 overflow-y-auto pr-1 text-sm leading-relaxed text-slate-700"
                        v-html="card.body_html"
                    />
                </article>
            </div>

            <Pagination
                class="mt-6"
                :current-page="noticeboard.pagination.current_page"
                :last-page="noticeboard.pagination.last_page"
                :per-page="noticeboard.pagination.per_page"
                :total="noticeboard.pagination.total"
                :base-url="route('dashboard')"
                :query-params="query()"
            />
        </template>
    </section>

    <Modal
        :open="formOpen"
        :title="
            editingCard
                ? t('noticeboard.form.edit_title')
                : t('noticeboard.form.create_title')
        "
        class="max-h-[92vh] max-w-3xl overflow-y-auto"
        @close="closeForm"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <Label class="mb-1.5 block">
                    {{ t('noticeboard.form.content') }}
                </Label>
                <RichTextEditor
                    v-model="form.body_html"
                    :invalid="Boolean(form.errors.body_html)"
                />
                <p
                    v-if="form.errors.body_html"
                    class="mt-1 text-xs text-error-red"
                >
                    {{ form.errors.body_html }}
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <fieldset>
                    <legend class="mb-2 block text-xs font-semibold">
                        {{ t('noticeboard.form.label') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="labelValue in noticeboard.labels"
                            :key="labelValue"
                            type="button"
                            :aria-label="t(`noticeboard.labels.${labelValue}`)"
                            :title="t(`noticeboard.labels.${labelValue}`)"
                            :aria-pressed="form.label === labelValue"
                            :class="[
                                'flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition',
                                form.label === labelValue
                                    ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/15'
                                    : 'border-outline-glass bg-white text-on-surface-variant hover:border-primary/40 hover:text-primary',
                            ]"
                            @click="form.label = labelValue"
                        >
                            <component
                                :is="labelIcon(labelValue)"
                                :size="17"
                                aria-hidden="true"
                            />
                            {{ t(`noticeboard.labels.${labelValue}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 block text-xs font-semibold">
                        {{ t('noticeboard.form.color') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="color in noticeboard.colors"
                            :key="color"
                            type="button"
                            :aria-label="t(`noticeboard.colors.${color}`)"
                            :title="t(`noticeboard.colors.${color}`)"
                            :aria-pressed="form.color === color"
                            class="flex size-10 items-center justify-center rounded-full transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            @click="form.color = color"
                        >
                            <span
                                :class="[
                                    'size-7 rounded-full border-2 border-white shadow-sm transition',
                                    colorSwatchClass(color),
                                    form.color === color
                                        ? 'scale-110 ring-2 ring-primary ring-offset-2'
                                        : 'hover:scale-105',
                                ]"
                            />
                        </button>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 block text-xs font-semibold">
                        {{ t('noticeboard.form.size') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="size in noticeboard.sizes"
                            :key="size"
                            type="button"
                            :aria-pressed="form.size === size"
                            :class="[
                                'flex min-w-20 flex-col items-center gap-1.5 rounded-xl border px-3 py-2 text-[11px] font-semibold transition',
                                form.size === size
                                    ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/15'
                                    : 'border-outline-glass bg-white text-on-surface-variant hover:border-primary/40',
                            ]"
                            @click="form.size = size"
                        >
                            <span
                                :class="[
                                    'rounded-sm bg-current opacity-70',
                                    {
                                        small: 'h-2.5 w-4',
                                        medium: 'h-3 w-6',
                                        large: 'h-3.5 w-8',
                                    }[size],
                                ]"
                            />
                            {{ t(`noticeboard.sizes.${size}`) }}
                        </button>
                    </div>
                </fieldset>

                <div>
                    <Label for="noticeboard-expiration" class="mb-1.5 block">
                        {{ t('noticeboard.form.expires_on') }}
                    </Label>
                    <Input
                        id="noticeboard-expiration"
                        v-model="form.expires_on"
                        type="date"
                    />
                </div>
            </div>

            <div>
                <Label class="mb-1.5 block">
                    {{ t('noticeboard.form.image') }}
                </Label>
                <div
                    class="flex flex-col gap-3 rounded-xl border border-dashed border-outline-glass p-4 sm:flex-row sm:items-center"
                >
                    <img
                        v-if="
                            selectedImageUrl ||
                            (editingCard?.image_url && !form.remove_image)
                        "
                        :src="selectedImageUrl ?? editingCard?.image_url ?? ''"
                        alt=""
                        class="h-24 w-32 rounded-lg object-cover"
                    />
                    <div class="flex flex-wrap gap-2">
                        <label
                            class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold"
                        >
                            <ImagePlus :size="15" />
                            {{ t('noticeboard.form.choose_image') }}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="selectImage"
                            />
                        </label>
                        <Button
                            v-if="
                                selectedImageUrl ||
                                (editingCard?.image_url && !form.remove_image)
                            "
                            variant="ghost"
                            @click="removeImage"
                        >
                            {{ t('noticeboard.form.remove_image') }}
                        </Button>
                    </div>
                </div>
                <p v-if="form.errors.image" class="mt-1 text-xs text-error-red">
                    {{ form.errors.image }}
                </p>
            </div>

            <p
                v-if="form.errors.lock_version"
                class="rounded-xl bg-error-red/10 p-3 text-sm text-error-red"
            >
                {{ form.errors.lock_version }}
            </p>

            <div class="flex justify-end gap-2">
                <Button variant="secondary" @click="closeForm">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>

<style>
.noticeboard-rich-text p + p,
.noticeboard-rich-text ul + p,
.noticeboard-rich-text ol + p {
    margin-top: 0.65rem;
}

.noticeboard-rich-text ul {
    list-style: disc;
    padding-left: 1.25rem;
}

.noticeboard-rich-text ol {
    list-style: decimal;
    padding-left: 1.25rem;
}

.noticeboard-rich-text a {
    color: var(--color-primary);
    text-decoration: underline;
}

.noticeboard-rich-text [data-text-size='small'] {
    font-size: 0.75rem;
}

.noticeboard-rich-text [data-text-size='large'] {
    font-size: 1.25rem;
}
</style>
