<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import {
    formatCzechDate,
    formatCzechDateTime,
} from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import type {
    OperationalDigestStatus,
    OperationalDigestSummary,
} from '@/types/operational-digest';

defineProps<{ digests: OperationalDigestSummary[] }>();

const { t } = useI18n();
const route = useRoute();

useBoundLocale();

function statusVariant(
    status: OperationalDigestStatus,
): 'neutral' | 'warning' | 'success' | 'danger' {
    if (status === 'sent') return 'success';
    if (status === 'failed') return 'danger';
    if (status === 'queued') return 'warning';
    return 'neutral';
}
</script>

<template>
    <AppLayout :title="t('settings.slack.digest_archive.title')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <div>
                <BackLink :href="route('settings.show')">
                    {{ t('settings.slack.digest_archive.back') }}
                </BackLink>
            </div>

            <PageHeader
                :title="t('settings.slack.digest_archive.title')"
                :subtitle="t('settings.slack.digest_archive.subtitle')"
            />

            <EmptyState
                v-if="digests.length === 0"
                icon="inbox"
                :title="t('settings.slack.digest_archive.empty_title')"
                :description="
                    t('settings.slack.digest_archive.empty_description')
                "
            />

            <div v-else class="flex flex-col gap-3">
                <Link
                    v-for="digest in digests"
                    :key="digest.id"
                    :href="route('settings.slack-digests.show', digest.id)"
                    class="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                >
                    <Card
                        class="transition group-hover:border-primary/30 group-hover:shadow-md"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="font-heading text-base font-semibold text-on-surface"
                                    >
                                        {{ formatCzechDate(digest.date) }}
                                    </h2>
                                    <Badge
                                        :variant="statusVariant(digest.status)"
                                    >
                                        {{
                                            t(
                                                `settings.slack.digest_archive.status.${digest.status}`,
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <p class="mt-2 text-sm text-on-surface-variant">
                                    {{
                                        t(
                                            'settings.slack.digest_archive.milestones',
                                            { count: digest.activity_count },
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="digest.last_error"
                                    class="mt-2 text-xs font-medium text-error-red"
                                >
                                    {{ digest.last_error }}
                                </p>
                            </div>
                            <dl
                                class="grid shrink-0 grid-cols-2 gap-x-5 gap-y-2 text-xs"
                            >
                                <div>
                                    <dt class="text-on-surface-variant">
                                        {{
                                            t(
                                                'settings.slack.digest_archive.attempts',
                                            )
                                        }}
                                    </dt>
                                    <dd class="font-semibold text-on-surface">
                                        {{ digest.attempt_count }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-on-surface-variant">
                                        {{
                                            t(
                                                'settings.slack.digest_archive.delivered',
                                            )
                                        }}
                                    </dt>
                                    <dd class="font-semibold text-on-surface">
                                        {{
                                            formatCzechDateTime(digest.sent_at)
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
