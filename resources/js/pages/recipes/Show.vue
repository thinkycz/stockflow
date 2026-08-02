<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Archive, ClipboardCheck, Pencil, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import RecipeInstructionList, {
    type RecipeInstructionData,
} from '@/components/recipes/RecipeInstructionList.vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    is_admin: boolean;
    recipe: {
        id: number;
        name: string;
        note: string | null;
        archived: boolean;
        category: { id: number; name: string };
        variants: Array<{
            id: number;
            name: string | null;
            instructions: RecipeInstructionData[];
        }>;
    };
    workers: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();
const route = useRoute();
const testModalOpen = ref(false);
const workerId = ref('');
const starting = ref(false);
const selectedVariantIndex = ref(0);
const selectedVariant = computed(
    () => props.recipe.variants[selectedVariantIndex.value],
);

function setArchived(archived: boolean): void {
    router.put(route('recipes.archive', props.recipe.id), { archived });
}

function startTest(): void {
    if (!workerId.value) return;
    starting.value = true;
    router.post(
        route('recipe-tests.store'),
        { recipe_id: props.recipe.id, worker_id: Number(workerId.value) },
        { onFinish: () => (starting.value = false) },
    );
}
</script>

<template>
    <AppLayout :title="recipe.name">
        <div class="space-y-6">
            <header>
                <BackLink :href="route('recipes.index')">{{
                    t('recipes.back')
                }}</BackLink>
                <div
                    class="mt-3 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge>{{ recipe.category.name }}</Badge
                            ><Badge v-if="recipe.archived" variant="warning">{{
                                t('recipes.archived')
                            }}</Badge>
                        </div>
                        <h1
                            class="mt-3 font-heading text-2xl font-bold text-on-surface"
                        >
                            {{ recipe.name }}
                        </h1>
                        <p
                            v-if="recipe.note"
                            class="mt-2 max-w-3xl whitespace-pre-line text-sm text-on-surface-variant"
                        >
                            {{ recipe.note }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template v-if="is_admin">
                            <Link :href="route('recipes.edit', recipe.id)"
                                ><Button
                                    ><Pencil :size="15" />{{
                                        t('common.edit')
                                    }}</Button
                                ></Link
                            >
                            <Button
                                v-if="!recipe.archived"
                                variant="secondary"
                                @click="setArchived(true)"
                                ><Archive :size="15" />{{
                                    t('recipes.archive')
                                }}</Button
                            >
                            <Button
                                v-else
                                variant="secondary"
                                @click="setArchived(false)"
                                ><RotateCcw :size="15" />{{
                                    t('recipes.restore')
                                }}</Button
                            >
                        </template>
                        <Button
                            v-else
                            :disabled="workers.length === 0"
                            @click="testModalOpen = true"
                            ><ClipboardCheck :size="16" />{{
                                t('recipes.test.start')
                            }}</Button
                        >
                    </div>
                </div>
            </header>

            <section v-if="selectedVariant" class="mx-auto w-full max-w-3xl">
                <div
                    v-if="recipe.variants.length > 1"
                    class="mb-3 flex flex-wrap gap-1 rounded-xl bg-surface-container-low p-1"
                    role="tablist"
                    :aria-label="t('recipes.variant_name')"
                >
                    <Button
                        v-for="(variant, index) in recipe.variants"
                        :key="variant.id"
                        type="button"
                        role="tab"
                        variant="ghost"
                        size="compact"
                        :aria-selected="selectedVariantIndex === index"
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold transition"
                        :class="
                            selectedVariantIndex === index
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-on-surface-variant hover:text-on-surface'
                        "
                        @click="selectedVariantIndex = index"
                    >
                        {{ variant.name || t('recipes.default_variant') }}
                    </Button>
                </div>
                <RecipeInstructionList
                    :instructions="selectedVariant.instructions"
                />
            </section>
        </div>

        <Modal
            :open="testModalOpen"
            :title="t('recipes.test.choose_worker')"
            @close="testModalOpen = false"
        >
            <Label for="test-worker" required>{{
                t('recipes.test.worker')
            }}</Label>
            <Select
                id="test-worker"
                v-model="workerId"
                class="mt-1"
                :options="
                    workers.map((worker) => ({
                        value: String(worker.id),
                        label: worker.name,
                    }))
                "
                :placeholder="t('recipes.test.choose_worker_placeholder')"
            />
            <template #footer>
                <Button variant="secondary" @click="testModalOpen = false">{{
                    t('common.cancel')
                }}</Button>
                <Button :disabled="!workerId || starting" @click="startTest">{{
                    starting ? t('common.saving') : t('recipes.test.start')
                }}</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
