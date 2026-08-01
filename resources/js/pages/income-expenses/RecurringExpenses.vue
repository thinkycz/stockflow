<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import FilterField from '@/components/ui/FilterField.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';

type RecurringExpense = {
    id: number;
    label: string;
    amount: number;
    due_day: number;
    note: string | null;
    starts_on: string;
    ends_before: string | null;
    effective_from: string;
    status: 'active' | 'upcoming' | 'ended';
};

const props = defineProps<{
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    filters: { year: number; month: number };
    recurring_expenses: RecurringExpense[];
}>();

const { t, locale } = useI18n();
useBoundLocale();
const route = useRoute();
const editorMode = ref<'create' | 'change' | 'terminate'>('create');
const selectedExpense = ref<RecurringExpense | null>(null);
const statuses = ['active', 'upcoming', 'ended'] as const;

const expenseForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    effective_period: selectedPeriod(),
    label: '',
    amount: '',
    due_day: '1',
    note: '',
});

const terminationForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    ends_before_period: selectedPeriod(),
});

const statusCounts = computed(() => ({
    active: props.recurring_expenses.filter(
        (expense) => expense.status === 'active',
    ).length,
    upcoming: props.recurring_expenses.filter(
        (expense) => expense.status === 'upcoming',
    ).length,
    ended: props.recurring_expenses.filter(
        (expense) => expense.status === 'ended',
    ).length,
}));

function selectedPeriod(): string {
    return `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`;
}

function followingPeriod(value: string): string {
    const [year, month] = value.split('-').map(Number);
    const next = new Date(Date.UTC(year, month, 1));
    return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
}

function startCreate(): void {
    selectedExpense.value = null;
    editorMode.value = 'create';
    expenseForm.clearErrors();
    expenseForm.reset();
    expenseForm.year = props.filters.year;
    expenseForm.month = props.filters.month;
    expenseForm.effective_period = selectedPeriod();
    expenseForm.due_day = '1';
}

function startChange(expense: RecurringExpense): void {
    selectedExpense.value = expense;
    editorMode.value = 'change';
    expenseForm.clearErrors();
    expenseForm.year = props.filters.year;
    expenseForm.month = props.filters.month;
    expenseForm.label = expense.label;
    expenseForm.amount = String(expense.amount);
    expenseForm.due_day = String(expense.due_day);
    expenseForm.note = expense.note ?? '';
    expenseForm.effective_period =
        expense.starts_on > selectedPeriod()
            ? expense.starts_on
            : selectedPeriod();
}

function startTermination(expense: RecurringExpense): void {
    selectedExpense.value = expense;
    editorMode.value = 'terminate';
    terminationForm.clearErrors();
    terminationForm.year = props.filters.year;
    terminationForm.month = props.filters.month;
    terminationForm.ends_before_period = followingPeriod(
        expense.starts_on > selectedPeriod()
            ? expense.starts_on
            : selectedPeriod(),
    );
}

function submitExpense(): void {
    const options = { onSuccess: startCreate };
    if (editorMode.value === 'change' && selectedExpense.value) {
        expenseForm.put(
            route(
                'income-expenses.recurring-expenses.update',
                selectedExpense.value.id,
            ),
            options,
        );
        return;
    }
    expenseForm.post(
        route('income-expenses.recurring-expenses.store'),
        options,
    );
}

function submitTermination(): void {
    if (!selectedExpense.value) return;
    terminationForm.post(
        route(
            'income-expenses.recurring-expenses.terminate',
            selectedExpense.value.id,
        ),
        { onSuccess: startCreate },
    );
}

function changeMonth(value: string): void {
    const [year, month] = value.split('-').map(Number);
    if (!year || !month) return;
    router.get(
        route('income-expenses.recurring-expenses.index'),
        { year, month },
        { preserveState: true },
    );
}

function money(value: number): string {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
    }).format(value);
}

function period(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 7)}-01T12:00:00`));
}
</script>

<template>
    <AppLayout :title="t('income_expenses.recurring.title')">
        <Head :title="t('income_expenses.recurring.title')" />

        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <BackLink
                        :href="
                            route('income-expenses.index', {
                                year: filters.year,
                                month: filters.month,
                            })
                        "
                    >
                        {{ t('income_expenses.recurring.back') }}
                    </BackLink>
                    <div class="mt-3 flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <CalendarClock :size="20" />
                        </div>
                        <div>
                            <h1
                                class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                            >
                                {{ t('income_expenses.recurring.title') }}
                            </h1>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                {{ t('income_expenses.recurring.page_help') }}
                            </p>
                        </div>
                    </div>
                    <StoreContextIndicator />
                </div>
                <FilterField
                    for="recurring_expenses_month"
                    :label="t('income_expenses.month')"
                >
                    <MonthPicker
                        id="recurring_expenses_month"
                        :model-value="selectedPeriod()"
                        @change="changeMonth"
                    />
                </FilterField>
            </header>

            <EmptyState
                v-if="!active_store || active_store.is_warehouse"
                :title="t('income_expenses.warehouse_title')"
                :description="t('income_expenses.warehouse_description')"
            />

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Card
                        v-for="status in statuses"
                        :key="status"
                        class="flex items-center justify-between"
                        padded
                    >
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{
                                    t(
                                        `income_expenses.recurring.status.${status}`,
                                    )
                                }}
                            </p>
                            <p class="mt-2 font-heading text-2xl font-bold">
                                {{ statusCounts[status] }}
                            </p>
                        </div>
                        <Badge
                            :variant="
                                status === 'active'
                                    ? 'success'
                                    : status === 'upcoming'
                                      ? 'warning'
                                      : 'neutral'
                            "
                        >
                            {{
                                t(`income_expenses.recurring.status.${status}`)
                            }}
                        </Badge>
                    </Card>
                </div>

                <div
                    class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]"
                >
                    <Card :padded="false" class="overflow-hidden">
                        <div
                            class="flex items-center justify-between gap-4 border-b border-outline-glass px-5 py-4"
                        >
                            <div>
                                <h2 class="font-heading text-lg font-bold">
                                    {{
                                        t('income_expenses.recurring.overview')
                                    }}
                                </h2>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ t('income_expenses.recurring.help') }}
                                </p>
                            </div>
                            <Button size="compact" @click="startCreate">
                                <Plus :size="14" />{{
                                    t('income_expenses.recurring.new')
                                }}
                            </Button>
                        </div>

                        <EmptyState
                            v-if="recurring_expenses.length === 0"
                            class="m-5"
                            density="compact"
                            icon="inbox"
                            :title="t('income_expenses.recurring.empty')"
                        />

                        <div v-else class="divide-y divide-outline-glass">
                            <article
                                v-for="expense in recurring_expenses"
                                :key="expense.id"
                                :data-testid="`recurring-expense-${expense.id}`"
                                class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="font-semibold">
                                            {{ expense.label }}
                                        </h3>
                                        <Badge
                                            :variant="
                                                expense.status === 'active'
                                                    ? 'success'
                                                    : expense.status ===
                                                        'upcoming'
                                                      ? 'warning'
                                                      : 'neutral'
                                            "
                                        >
                                            {{
                                                t(
                                                    `income_expenses.recurring.status.${expense.status}`,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-2 text-sm font-semibold text-on-surface"
                                    >
                                        {{ money(expense.amount) }}
                                        <span
                                            class="font-normal text-on-surface-variant"
                                        >
                                            ·
                                            {{
                                                t(
                                                    'income_expenses.recurring.due_day_value',
                                                    {
                                                        day: expense.due_day,
                                                    },
                                                )
                                            }}
                                        </span>
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-on-surface-variant"
                                    >
                                        {{
                                            t(
                                                'income_expenses.recurring.validity',
                                                {
                                                    from: period(
                                                        expense.starts_on,
                                                    ),
                                                    until: expense.ends_before
                                                        ? period(
                                                              expense.ends_before,
                                                          )
                                                        : t(
                                                              'income_expenses.recurring.indefinite',
                                                          ),
                                                },
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="expense.note"
                                        class="mt-2 text-xs text-on-surface-variant"
                                    >
                                        {{ expense.note }}
                                    </p>
                                </div>
                                <div
                                    v-if="expense.status !== 'ended'"
                                    class="flex shrink-0 flex-wrap gap-2"
                                >
                                    <Button
                                        variant="secondary"
                                        size="compact"
                                        @click="startChange(expense)"
                                    >
                                        <Pencil :size="13" />{{
                                            t(
                                                'income_expenses.recurring.change',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="compact"
                                        class="text-error-red"
                                        @click="startTermination(expense)"
                                    >
                                        {{
                                            t(
                                                'income_expenses.recurring.terminate',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </article>
                        </div>
                    </Card>

                    <Card class="lg:sticky lg:top-6">
                        <template v-if="editorMode !== 'terminate'">
                            <h2 class="font-heading text-lg font-bold">
                                {{
                                    t(
                                        editorMode === 'change'
                                            ? 'income_expenses.recurring.change_title'
                                            : 'income_expenses.recurring.add_title',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ t('income_expenses.recurring.editor_help') }}
                            </p>
                            <form
                                class="mt-5 space-y-4"
                                @submit.prevent="submitExpense"
                            >
                                <div class="space-y-2">
                                    <Label for="recurring-label" required>{{
                                        t('income_expenses.item')
                                    }}</Label>
                                    <Input
                                        id="recurring-label"
                                        v-model="expenseForm.label"
                                        required
                                    />
                                    <FieldError
                                        :message="expenseForm.errors.label"
                                    />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label
                                            for="recurring-amount"
                                            required
                                            >{{
                                                t('income_expenses.amount')
                                            }}</Label
                                        >
                                        <Input
                                            id="recurring-amount"
                                            v-model="expenseForm.amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                        />
                                        <FieldError
                                            :message="expenseForm.errors.amount"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="recurring-day" required>{{
                                            t(
                                                'income_expenses.recurring.due_day',
                                            )
                                        }}</Label>
                                        <Input
                                            id="recurring-day"
                                            v-model="expenseForm.due_day"
                                            type="number"
                                            min="1"
                                            max="31"
                                            required
                                        />
                                        <FieldError
                                            :message="
                                                expenseForm.errors.due_day
                                            "
                                        />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="recurring-effective" required>{{
                                        t(
                                            editorMode === 'change'
                                                ? 'income_expenses.recurring.effective_from'
                                                : 'income_expenses.recurring.starts_on',
                                        )
                                    }}</Label>
                                    <MonthPicker
                                        id="recurring-effective"
                                        v-model="expenseForm.effective_period"
                                    />
                                    <FieldError
                                        :message="
                                            expenseForm.errors.effective_period
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="recurring-note">{{
                                        t('income_expenses.note')
                                    }}</Label>
                                    <Textarea
                                        id="recurring-note"
                                        v-model="expenseForm.note"
                                        :rows="4"
                                    />
                                    <FieldError
                                        :message="expenseForm.errors.note"
                                    />
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <Button
                                        v-if="editorMode === 'change'"
                                        type="button"
                                        variant="secondary"
                                        @click="startCreate"
                                    >
                                        {{ t('common.cancel') }}
                                    </Button>
                                    <Button
                                        type="submit"
                                        :disabled="expenseForm.processing"
                                    >
                                        {{ t('common.save') }}
                                    </Button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <h2 class="font-heading text-lg font-bold">
                                {{
                                    t(
                                        'income_expenses.recurring.terminate_title',
                                    )
                                }}
                            </h2>
                            <p class="mt-2 text-sm text-on-surface-variant">
                                {{
                                    t(
                                        'income_expenses.recurring.terminate_help',
                                        {
                                            item: selectedExpense?.label ?? '',
                                        },
                                    )
                                }}
                            </p>
                            <form
                                class="mt-5 space-y-4"
                                @submit.prevent="submitTermination"
                            >
                                <div class="space-y-2">
                                    <Label
                                        for="recurring-ends-before"
                                        required
                                        >{{
                                            t(
                                                'income_expenses.recurring.ends_before',
                                            )
                                        }}</Label
                                    >
                                    <MonthPicker
                                        id="recurring-ends-before"
                                        v-model="
                                            terminationForm.ends_before_period
                                        "
                                    />
                                    <FieldError
                                        :message="
                                            terminationForm.errors
                                                .ends_before_period
                                        "
                                    />
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        @click="startCreate"
                                    >
                                        {{ t('common.cancel') }}
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="warning"
                                        :disabled="terminationForm.processing"
                                    >
                                        {{
                                            t(
                                                'income_expenses.recurring.confirm_terminate',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </template>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
