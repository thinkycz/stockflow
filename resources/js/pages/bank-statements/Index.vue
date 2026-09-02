<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRoute } from '@/composables/useRoute';

type StatementSummary = {
    id: number;
    status: string;
    bank_name: string | null;
    statement_number: string | null;
    period_from: string | null;
    period_to: string | null;
    currency: string | null;
    original_name: string;
    attempt_count: number;
    created_at: string;
};

const props = defineProps<{
    statements: StatementSummary[];
    active_store: { id: number; name: string } | null;
}>();

const { t } = useI18n();
const route = useRoute();
const form = useForm<{ document: File | null }>({ document: null });

function submit(): void {
    form.post(route('bank-statements.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function chooseFile(event: Event): void {
    form.document = (event.target as HTMLInputElement).files?.item(0) ?? null;
}

function badgeVariant(
    status: string,
): 'neutral' | 'success' | 'warning' | 'danger' {
    if (status === 'confirmed') return 'success';
    if (status === 'review') return 'warning';
    if (status === 'failed') return 'danger';
    return 'neutral';
}
</script>

<template>
    <AppLayout :title="t('bank_statements.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('bank_statements.title')"
                :subtitle="t('bank_statements.subtitle')"
            >
                <template #context><StoreContextIndicator /></template>
            </PageHeader>

            <Card padded>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <h2
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('bank_statements.upload.title') }}
                        </h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('bank_statements.upload.description') }}
                        </p>
                    </div>
                    <Alert variant="warning">
                        {{ t('bank_statements.upload.external_ai_notice') }}
                    </Alert>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <Input
                                type="file"
                                accept="application/pdf,.pdf"
                                class="block w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-xs"
                                @change="chooseFile"
                            />
                            <FieldError :message="form.errors.document" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.document === null"
                            :loading="form.processing"
                        >
                            <Upload :size="15" />
                            {{ t('bank_statements.upload.action') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <EmptyState
                v-if="props.statements.length === 0"
                icon="inbox"
                :title="t('bank_statements.empty.title')"
                :description="t('bank_statements.empty.description')"
            />

            <DataTable v-else density="compact">
                <thead>
                    <tr>
                        <th>{{ t('bank_statements.columns.file') }}</th>
                        <th>{{ t('bank_statements.columns.period') }}</th>
                        <th>{{ t('bank_statements.columns.bank') }}</th>
                        <th>{{ t('bank_statements.columns.status') }}</th>
                        <th class="text-right">
                            {{ t('bank_statements.columns.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="statement in props.statements"
                        :key="statement.id"
                    >
                        <td>{{ statement.original_name }}</td>
                        <td>
                            {{ statement.period_from ?? '—' }} –
                            {{ statement.period_to ?? '—' }}
                        </td>
                        <td>{{ statement.bank_name ?? '—' }}</td>
                        <td>
                            <Badge :variant="badgeVariant(statement.status)">
                                {{
                                    t(
                                        `bank_statements.status.${statement.status}`,
                                    )
                                }}
                            </Badge>
                        </td>
                        <td class="text-right">
                            <Link
                                :href="
                                    route('bank-statements.show', {
                                        bankStatement: statement.id,
                                    })
                                "
                            >
                                <Button variant="secondary" size="compact">
                                    {{ t('bank_statements.actions.detail') }}
                                </Button>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </DataTable>
        </div>
    </AppLayout>
</template>
