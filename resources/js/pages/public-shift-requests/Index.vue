<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardPen,
    LockKeyhole,
    Zap,
} from '@lucide/vue';
import ShiftMonthCalendar from '@/features/shifts/components/ShiftMonthCalendar.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import {
    usePublicShiftRequests,
    type PublicShiftRequestsProps,
} from '@/features/shifts/usePublicShiftRequests';

const props = defineProps<PublicShiftRequestsProps>();
const {
    t,
    route,
    selectedWorkerId,
    selectedStartTime,
    selectedEndTime,
    quickAddActive,
    pendingDates,
    currentMonthLabel,
    weekdayLabels,
    workerOptions,
    timeOptions,
    isFirstAllowedMonth,
    calendarDays,
    navigateMonth,
    startQuickAdd,
    stopQuickAdd,
    toggleRequest,
} = usePublicShiftRequests(props);
</script>

<template>
    <Head :title="t('shifts.requests.title', { store: store.name })" />

    <main
        class="min-h-screen bg-surface-bg px-4 py-6 font-sans sm:px-6 sm:py-10"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="space-y-3">
                <Link
                    :href="route('public-shifts.index', { token: share_token })"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant transition hover:text-primary"
                >
                    <ChevronLeft :size="13" />
                    {{ t('shifts.requests.back') }}
                </Link>
                <div class="flex items-center gap-3 text-primary">
                    <ClipboardPen :size="24" />
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface sm:text-3xl"
                    >
                        {{ t('shifts.requests.heading') }}
                    </h1>
                </div>
                <p class="text-sm text-on-surface-variant">
                    {{ t('shifts.requests.subtitle', { store: store.name }) }}
                </p>
            </header>

            <Card padded>
                <div
                    class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end"
                >
                    <div class="space-y-2">
                        <Label for="request_worker">{{
                            t('shifts.quick_add.employee')
                        }}</Label>
                        <Select
                            id="request_worker"
                            v-model="selectedWorkerId"
                            :disabled="quickAddActive"
                            :options="workerOptions"
                            :placeholder="t('shifts.select_worker')"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="request_start">{{
                            t('shifts.requests.start_time')
                        }}</Label>
                        <Select
                            id="request_start"
                            v-model="selectedStartTime"
                            :disabled="quickAddActive"
                            :options="timeOptions"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="request_end">{{
                            t('shifts.requests.end_time')
                        }}</Label>
                        <Select
                            id="request_end"
                            v-model="selectedEndTime"
                            :disabled="quickAddActive"
                            :options="timeOptions"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Button
                            v-if="!quickAddActive"
                            :disabled="
                                is_locked ||
                                selectedWorkerId === '' ||
                                selectedStartTime >= selectedEndTime
                            "
                            @click="startQuickAdd"
                        >
                            <Zap :size="14" />
                            {{ t('shifts.requests.start') }}
                        </Button>
                        <Button
                            v-else
                            variant="secondary"
                            @click="stopQuickAdd"
                        >
                            <Check :size="14" />
                            {{ t('shifts.quick_add.done') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="quickAddActive"
                    class="mt-4 text-xs font-semibold text-primary"
                >
                    {{ t('shifts.requests.active_help') }}
                </p>
                <p
                    v-else-if="selectedStartTime >= selectedEndTime"
                    class="mt-4 text-xs font-semibold text-error-red"
                >
                    {{ t('shifts.requests.invalid_time') }}
                </p>
            </Card>

            <div
                v-if="is_locked"
                role="status"
                class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950"
            >
                <LockKeyhole :size="20" class="mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-bold">
                        {{ t('shifts.requests.locked_title') }}
                    </p>
                    <p class="mt-1 text-xs">
                        {{ t('shifts.requests.locked_help') }}
                    </p>
                </div>
            </div>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="flex items-center gap-2">
                        <CalendarDays
                            :size="18"
                            class="text-on-surface-variant"
                        />
                        <h2
                            class="font-heading text-lg font-bold text-on-surface capitalize"
                        >
                            {{ currentMonthLabel }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            type="button"
                            :disabled="isFirstAllowedMonth"
                            :aria-label="t('shifts.prev_month')"
                            @click="navigateMonth(-1)"
                        >
                            <ChevronLeft :size="16" />
                        </Button>
                        <Button
                            variant="ghost"
                            type="button"
                            :aria-label="t('shifts.next_month')"
                            @click="navigateMonth(1)"
                        >
                            <ChevronRight :size="16" />
                        </Button>
                    </div>
                </div>

                <ShiftMonthCalendar
                    :days="calendarDays"
                    :weekday-labels="weekdayLabels"
                    :interactive="quickAddActive && !is_locked"
                    :editable="!is_locked"
                    :quick-add-active="quickAddActive"
                    :pending-dates="pendingDates"
                    @activate="toggleRequest"
                />
            </section>
        </div>
    </main>
</template>
