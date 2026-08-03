<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import type {
    OperationalDigest,
    OperationalDigestStatus,
} from '@/types/operational-digest';

const props = defineProps<{ digest: OperationalDigest }>();

const { t } = useI18n();
const route = useRoute();
const retryForm = useForm({});

useBoundLocale();

function statusVariant(
    status: OperationalDigestStatus,
): 'neutral' | 'warning' | 'success' | 'danger' {
    if (status === 'sent') return 'success';
    if (status === 'failed') return 'danger';
    if (status === 'queued') return 'warning';
    return 'neutral';
}

function retry(): void {
    retryForm.post(route('settings.slack-digests.retry', props.digest.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="digest.snapshot.title">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <div>
                <BackLink :href="route('settings.slack-digests.index')">
                    {{ t('settings.slack.digest_archive.back_to_archive') }}
                </BackLink>
            </div>

            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ digest.snapshot.title }}
                        </h1>
                        <Badge :variant="statusVariant(digest.status)">
                            {{
                                t(
                                    `settings.slack.digest_archive.status.${digest.status}`,
                                )
                            }}
                        </Badge>
                    </div>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        {{ digest.snapshot.intro }}
                    </p>
                    <p class="mt-2 text-xs text-on-surface-variant">
                        {{ t('settings.slack.digest_archive.attempts') }}:
                        {{ digest.attempt_count }} ·
                        {{ t('settings.slack.digest_archive.delivered') }}:
                        {{ formatCzechDateTime(digest.sent_at) }}
                    </p>
                </div>
                <Button
                    v-if="digest.status === 'failed'"
                    variant="secondary"
                    :disabled="retryForm.processing"
                    @click="retry"
                >
                    {{ t('settings.slack.digest_archive.retry') }}
                </Button>
            </header>

            <Alert v-if="digest.last_error" variant="error">
                {{ digest.last_error }}
            </Alert>

            <Card
                v-for="section in digest.snapshot.sections"
                :key="section.key"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            v-if="section.is_warehouse"
                            class="text-[11px] font-semibold uppercase tracking-wide text-primary"
                        >
                            {{ t('settings.slack.digest_archive.warehouse') }}
                        </p>
                        <h2
                            class="font-heading text-lg font-semibold text-on-surface"
                        >
                            {{ section.name }}
                        </h2>
                    </div>
                    <span class="text-xs font-semibold text-on-surface-variant">
                        {{ section.activity_count }}
                    </span>
                </div>

                <div class="mt-4 space-y-2 text-sm text-on-surface-variant">
                    <p v-for="paragraph in section.paragraphs" :key="paragraph">
                        {{ paragraph }}
                    </p>
                </div>

                <div
                    v-if="section.details.length > 0"
                    class="mt-5 divide-y divide-outline-glass border-t border-outline-glass"
                >
                    <article
                        v-for="(detail, index) in section.details"
                        :key="`${detail.title}-${index}`"
                        class="py-4 last:pb-0"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold text-on-surface"
                                >
                                    {{ detail.title }}
                                </h3>
                                <p
                                    v-if="detail.body"
                                    class="mt-1 text-xs leading-5 text-on-surface-variant"
                                >
                                    {{ detail.body }}
                                </p>
                                <p
                                    v-if="detail.actor"
                                    class="mt-1 text-xs text-on-surface-variant"
                                >
                                    {{
                                        t(
                                            'settings.slack.digest_archive.actor',
                                        )
                                    }}:
                                    {{ detail.actor }}
                                </p>
                            </div>
                            <a
                                :href="detail.url"
                                class="shrink-0 text-xs font-semibold text-primary hover:underline"
                            >
                                {{ t('common.detail') }}
                            </a>
                        </div>
                    </article>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
