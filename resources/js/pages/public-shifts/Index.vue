<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardPen,
    Download,
    Gauge,
    Share,
} from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import ShiftMonthCalendar from '@/features/shifts/components/ShiftMonthCalendar.vue';
import ShiftMonthlySummaryTable from '@/features/shifts/components/ShiftMonthlySummaryTable.vue';
import {
    usePublicShifts,
    type PublicShiftsProps,
} from '@/features/shifts/usePublicShifts';

const props = defineProps<PublicShiftsProps>();
const {
    t,
    route,
    canInstall,
    iosBrowser,
    instructionsOpen,
    install,
    currentMonthLabel,
    weekdayLabels,
    calendarDays,
    navigateMonth,
} = usePublicShifts(props);
</script>

<template>
    <Head :title="t('shifts.public_title', { store: store.name })" />

    <main
        class="min-h-screen bg-surface-bg px-4 py-6 font-sans sm:px-6 sm:py-10"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3 text-primary">
                        <CalendarDays :size="24" />
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface sm:text-3xl"
                        >
                            {{ store.name }}
                        </h1>
                    </div>
                    <p class="text-sm text-on-surface-variant">
                        {{ t('shifts.public_subtitle') }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Button
                        v-if="canInstall"
                        variant="secondary"
                        type="button"
                        @click="install"
                    >
                        <Download :size="15" />
                        {{ t('shifts.install.action') }}
                    </Button>
                    <Link
                        as="button"
                        :href="
                            route('public-shift-requests.index', {
                                token: share_token,
                            })
                        "
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-[0_4px_12px_rgba(0,104,95,0.15)] transition hover:brightness-105 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none active:scale-[0.98]"
                    >
                        <ClipboardPen :size="15" />
                        {{ t('shifts.requests.open_form') }}
                    </Link>
                </div>
            </header>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <h2
                        class="font-heading text-lg font-bold capitalize text-on-surface sm:text-xl"
                    >
                        {{ currentMonthLabel }}
                    </h2>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            type="button"
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
                />
            </section>

            <section class="space-y-4">
                <div class="mb-4 flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary"
                    >
                        <Gauge :size="20" />
                    </span>
                    <div>
                        <h2
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('shifts.rating.summary.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{
                                t('shifts.rating.summary.subtitle', {
                                    month: currentMonthLabel,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <ShiftMonthlySummaryTable :rows="monthly_summary" />
            </section>
        </div>
    </main>

    <Modal
        :open="instructionsOpen"
        :title="t('shifts.install.title')"
        size="sm"
        @close="instructionsOpen = false"
    >
        <div class="space-y-5 text-sm text-on-surface-variant">
            <p v-if="iosBrowser === 'safari'">
                {{ t('shifts.install.safari_intro') }}
            </p>
            <p v-else-if="iosBrowser === 'chrome'">
                {{ t('shifts.install.chrome_intro') }}
            </p>
            <p v-else>
                {{ t('shifts.install.other_intro') }}
            </p>

            <ol class="space-y-3">
                <li class="flex items-start gap-3">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-fixed text-primary"
                    >
                        <Share :size="17" />
                    </span>
                    <span class="pt-1.5">{{ t('shifts.install.share') }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-container font-semibold text-on-surface"
                        >2</span
                    >
                    <span class="pt-1.5">{{
                        t('shifts.install.add_home')
                    }}</span>
                </li>
                <li
                    v-if="iosBrowser === 'safari'"
                    class="flex items-start gap-3"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-container font-semibold text-on-surface"
                        >3</span
                    >
                    <span class="pt-1.5">{{
                        t('shifts.install.web_app')
                    }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-container font-semibold text-on-surface"
                        >{{ iosBrowser === 'safari' ? 4 : 3 }}</span
                    >
                    <span class="pt-1.5">{{
                        t('shifts.install.confirm')
                    }}</span>
                </li>
            </ol>
        </div>
    </Modal>
</template>
