<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    Eye,
    ImagePlus,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import RichTextEditor from '@/components/noticeboard/RichTextEditor.vue';
import { useRoute } from '@/composables/useRoute';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

export type NoticeboardCard = {
    id: number;
    title: string;
    body_html: string;
    label: 'information' | 'important' | 'task' | 'event';
    color: 'yellow' | 'pink' | 'blue' | 'green' | 'purple';
    image_url: string | null;
    expires_on: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    created_by_email: string | null;
    updated_by_email: string | null;
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
    can_view_trash: boolean;
};

const props = defineProps<{
    noticeboard: NoticeboardPayload;
    activeStore: { id: number; name: string } | null;
}>();

const { t } = useI18n();
const route = useRoute();
const formOpen = ref(false);
const detailCard = ref<NoticeboardCard | null>(null);
const editingCard = ref<NoticeboardCard | null>(null);
const search = ref(props.noticeboard.filters.search);
const label = ref(props.noticeboard.filters.label ?? '');
const selectedImageUrl = ref<string | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
    title: '',
    body_html: '<p></p>',
    label: 'information',
    color: 'yellow',
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

const formLabelOptions = computed(() =>
    props.noticeboard.labels.map((value) => ({
        value,
        label: t(`noticeboard.labels.${value}`),
    })),
);

const colorOptions = computed(() =>
    props.noticeboard.colors.map((value) => ({
        value,
        label: t(`noticeboard.colors.${value}`),
    })),
);

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
    detailCard.value = null;
    editingCard.value = card;
    form.clearErrors();
    form.title = card.title;
    form.body_html = card.body_html;
    form.label = card.label;
    form.color = card.color;
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

function trash(card: NoticeboardCard): void {
    if (!window.confirm(t('noticeboard.confirm_trash'))) return;
    router.delete(route('noticeboard-cards.destroy', card.id), {
        preserveScroll: true,
    });
}

function restore(card: NoticeboardCard): void {
    router.post(
        route('noticeboard-cards.restore', card.id),
        {},
        {
            preserveScroll: true,
        },
    );
}

function forceDestroy(card: NoticeboardCard): void {
    if (!window.confirm(t('noticeboard.confirm_force_delete'))) return;
    router.delete(route('noticeboard-cards.force-destroy', card.id), {
        preserveScroll: true,
    });
}

function cardClass(color: NoticeboardCard['color']): string {
    return cn(
        'group relative flex min-h-60 cursor-pointer flex-col overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:border-primary/25 hover:shadow-md focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
        {
            yellow: 'border-amber-200/80 bg-amber-50',
            pink: 'border-pink-200/80 bg-pink-50',
            blue: 'border-sky-200/80 bg-sky-50',
            green: 'border-emerald-200/80 bg-emerald-50',
            purple: 'border-violet-200/80 bg-violet-50',
        }[color],
    );
}
</script>

<template>
    <section>
        <div
            class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h2 class="font-heading text-xl font-bold text-on-surface">
                    {{ t('noticeboard.title') }}
                </h2>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{
                        activeStore
                            ? t('noticeboard.subtitle')
                            : t('noticeboard.no_store')
                    }}
                </p>
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
                class="grid items-start gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <article
                    v-for="card in noticeboard.cards"
                    :key="card.id"
                    :class="cardClass(card.color)"
                    tabindex="0"
                    @click="detailCard = card"
                    @keydown.enter="detailCard = card"
                >
                    <div class="flex items-start justify-between gap-3">
                        <Badge variant="neutral">
                            {{ t(`noticeboard.labels.${card.label}`) }}
                        </Badge>
                        <div
                            class="flex opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            @click.stop
                        >
                            <template
                                v-if="noticeboard.filters.status === 'trash'"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg p-2 hover:bg-black/5"
                                    :aria-label="t('noticeboard.restore')"
                                    @click="restore(card)"
                                >
                                    <ArchiveRestore :size="15" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-error-red hover:bg-black/5"
                                    :aria-label="t('noticeboard.force_delete')"
                                    @click="forceDestroy(card)"
                                >
                                    <Trash2 :size="15" />
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 hover:bg-black/5"
                                    :aria-label="t('common.edit')"
                                    @click="openEdit(card)"
                                >
                                    <Pencil :size="15" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-error-red hover:bg-black/5"
                                    :aria-label="t('common.delete')"
                                    @click="trash(card)"
                                >
                                    <Trash2 :size="15" />
                                </button>
                            </template>
                        </div>
                    </div>
                    <img
                        v-if="card.image_url"
                        :src="card.image_url"
                        :alt="card.title"
                        class="mt-4 h-32 w-full rounded-xl object-cover"
                    />
                    <h3
                        class="mt-4 font-heading text-lg font-bold text-slate-900"
                    >
                        {{ card.title }}
                    </h3>
                    <div
                        class="noticeboard-rich-text mt-2 max-h-36 overflow-hidden text-sm leading-relaxed text-slate-700"
                        v-html="card.body_html"
                    />
                    <div
                        class="mt-auto flex items-end justify-between gap-3 border-t border-black/5 pt-4 text-[11px] text-slate-600"
                    >
                        <span>
                            {{
                                card.updated_by_email ??
                                t('noticeboard.deleted_user')
                            }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1 font-semibold"
                        >
                            <Eye :size="12" />
                            {{ t('noticeboard.show_all') }}
                        </span>
                    </div>
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
                <label
                    for="noticeboard-title"
                    class="mb-1.5 block text-xs font-semibold text-on-surface"
                >
                    {{ t('noticeboard.form.title') }}
                </label>
                <Input
                    id="noticeboard-title"
                    v-model="form.title"
                    maxlength="120"
                    :invalid="Boolean(form.errors.title)"
                    required
                />
                <p v-if="form.errors.title" class="mt-1 text-xs text-error-red">
                    {{ form.errors.title }}
                </p>
            </div>

            <div>
                <label
                    class="mb-1.5 block text-xs font-semibold text-on-surface"
                >
                    {{ t('noticeboard.form.content') }}
                </label>
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

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold">
                        {{ t('noticeboard.form.label') }}
                    </label>
                    <Select
                        v-model="form.label"
                        :options="formLabelOptions"
                        required
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold">
                        {{ t('noticeboard.form.color') }}
                    </label>
                    <Select
                        v-model="form.color"
                        :options="colorOptions"
                        required
                    />
                </div>
                <div>
                    <label
                        for="noticeboard-expiration"
                        class="mb-1.5 block text-xs font-semibold"
                    >
                        {{ t('noticeboard.form.expires_on') }}
                    </label>
                    <Input
                        id="noticeboard-expiration"
                        v-model="form.expires_on"
                        type="date"
                    />
                </div>
            </div>

            <div>
                <label
                    class="mb-1.5 block text-xs font-semibold text-on-surface"
                >
                    {{ t('noticeboard.form.image') }}
                </label>
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

    <Modal
        :open="detailCard !== null"
        :title="detailCard?.title"
        class="max-h-[92vh] max-w-3xl overflow-y-auto"
        @close="detailCard = null"
    >
        <template v-if="detailCard">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <Badge variant="neutral">
                    {{ t(`noticeboard.labels.${detailCard.label}`) }}
                </Badge>
                <span
                    v-if="detailCard.expires_on"
                    class="text-xs text-on-surface-variant"
                >
                    {{
                        t('noticeboard.expires', {
                            date: detailCard.expires_on,
                        })
                    }}
                </span>
            </div>
            <img
                v-if="detailCard.image_url"
                :src="detailCard.image_url"
                :alt="detailCard.title"
                class="mb-5 max-h-[28rem] w-full rounded-2xl object-contain"
            />
            <div
                class="noticeboard-rich-text text-sm leading-relaxed text-on-surface"
                v-html="detailCard.body_html"
            />
            <div
                class="mt-6 border-t border-outline-glass pt-4 text-xs text-on-surface-variant"
            >
                {{
                    t('noticeboard.updated_by', {
                        email:
                            detailCard.updated_by_email ??
                            t('noticeboard.deleted_user'),
                        date: formatDateTime(detailCard.updated_at),
                    })
                }}
            </div>
            <div
                v-if="noticeboard.filters.status !== 'trash'"
                class="mt-5 flex justify-end gap-2"
            >
                <Button variant="secondary" @click="openEdit(detailCard)">
                    <Pencil :size="14" />
                    {{ t('common.edit') }}
                </Button>
                <Button variant="danger" @click="trash(detailCard)">
                    <Trash2 :size="14" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </template>
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
