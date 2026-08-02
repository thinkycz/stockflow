<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import BackLink from '@/components/ui/BackLink.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useRoute } from '@/composables/useRoute';

type Step = { text: string };
type Variant = { name: string; steps: Step[] };
type FormData = {
    category_id: string;
    name: string;
    note: string | null;
    variants: Variant[];
};

const props = defineProps<{
    recipe: {
        id: number;
        category_id: number;
        name: string;
        note: string | null;
        variants: Variant[];
    } | null;
    categories: Array<{ id: number; name: string }>;
}>();

const { t } = useI18n();
const route = useRoute();
const form = useForm<FormData>({
    category_id: props.recipe ? String(props.recipe.category_id) : '',
    name: props.recipe?.name ?? '',
    note: props.recipe?.note ?? null,
    variants: props.recipe?.variants.map((variant) => ({
        name: variant.name ?? '',
        steps: variant.steps.map((step) => ({ ...step })),
    })) ?? [{ name: '', steps: [{ text: '' }, { text: '' }] }],
});

function move<T>(rows: T[], index: number, direction: -1 | 1): void {
    const target = index + direction;
    if (target < 0 || target >= rows.length) return;
    const [row] = rows.splice(index, 1);
    if (row !== undefined) rows.splice(target, 0, row);
}

function addVariant(): void {
    form.variants.push({ name: '', steps: [{ text: '' }, { text: '' }] });
}
function removeVariant(index: number): void {
    if (form.variants.length > 1) form.variants.splice(index, 1);
}
function addStep(variant: Variant): void {
    variant.steps.push({ text: '' });
}
function removeStep(variant: Variant, index: number): void {
    if (variant.steps.length > 2) variant.steps.splice(index, 1);
}

function submit(): void {
    if (props.recipe) form.put(route('recipes.update', props.recipe.id));
    else form.post(route('recipes.store'));
}
</script>

<template>
    <AppLayout :title="recipe ? t('recipes.edit') : t('recipes.create')">
        <div class="mx-auto max-w-4xl space-y-6">
            <header>
                <BackLink
                    :href="
                        recipe
                            ? route('recipes.show', recipe.id)
                            : route('recipes.index')
                    "
                    >{{ t('recipes.back') }}</BackLink
                >
                <h1
                    class="mt-3 font-heading text-2xl font-bold text-on-surface"
                >
                    {{ recipe ? t('recipes.edit') : t('recipes.create') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('recipes.form_help') }}
                </p>
            </header>

            <form class="space-y-5" @submit.prevent="submit">
                <Card class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="recipe-name" required>{{
                                t('recipes.name')
                            }}</Label
                            ><Input
                                id="recipe-name"
                                v-model="form.name"
                                class="mt-1"
                                required
                            /><FieldError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label for="recipe-category" required>{{
                                t('recipes.category')
                            }}</Label
                            ><Select
                                id="recipe-category"
                                v-model="form.category_id"
                                class="mt-1"
                                :options="
                                    categories.map((category) => ({
                                        value: String(category.id),
                                        label: category.name,
                                    }))
                                "
                                :placeholder="t('recipes.choose_category')"
                                required
                            /><FieldError :message="form.errors.category_id" />
                        </div>
                    </div>
                    <div>
                        <Label for="recipe-note">{{ t('recipes.note') }}</Label
                        ><Textarea
                            id="recipe-note"
                            v-model="form.note"
                            class="mt-1"
                            :rows="3"
                        /><FieldError :message="form.errors.note" />
                    </div>
                </Card>

                <Card
                    v-for="(variant, variantIndex) in form.variants"
                    :key="variantIndex"
                    class="space-y-4"
                >
                    <div class="flex items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <Label :for="`variant-${variantIndex}`">{{
                                t('recipes.variant_name')
                            }}</Label
                            ><Input
                                :id="`variant-${variantIndex}`"
                                v-model="variant.name"
                                class="mt-1"
                                :placeholder="t('recipes.default_variant')"
                            />
                        </div>
                        <Button
                            size="icon"
                            variant="ghost"
                            :disabled="variantIndex === 0"
                            :aria-label="t('common.move_up')"
                            @click="move(form.variants, variantIndex, -1)"
                            ><ArrowUp :size="15"
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            :disabled="
                                variantIndex === form.variants.length - 1
                            "
                            :aria-label="t('common.move_down')"
                            @click="move(form.variants, variantIndex, 1)"
                            ><ArrowDown :size="15"
                        /></Button>
                        <Button
                            size="icon"
                            variant="danger"
                            :disabled="form.variants.length === 1"
                            :aria-label="t('recipes.remove_variant')"
                            @click="removeVariant(variantIndex)"
                            ><Trash2 :size="15"
                        /></Button>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('recipes.steps') }}</Label>
                        <div
                            v-for="(step, stepIndex) in variant.steps"
                            :key="stepIndex"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="w-6 text-center text-xs font-bold text-on-surface-variant"
                                >{{ stepIndex + 1 }}</span
                            >
                            <Input
                                v-model="step.text"
                                required
                                :aria-label="
                                    t('recipes.step_number', {
                                        number: stepIndex + 1,
                                    })
                                "
                            />
                            <Button
                                size="icon"
                                variant="ghost"
                                :disabled="stepIndex === 0"
                                :aria-label="t('common.move_up')"
                                @click="move(variant.steps, stepIndex, -1)"
                                ><ArrowUp :size="14"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                :disabled="
                                    stepIndex === variant.steps.length - 1
                                "
                                :aria-label="t('common.move_down')"
                                @click="move(variant.steps, stepIndex, 1)"
                                ><ArrowDown :size="14"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                :disabled="variant.steps.length <= 2"
                                :aria-label="t('recipes.remove_step')"
                                @click="removeStep(variant, stepIndex)"
                                ><Trash2 :size="14"
                            /></Button>
                        </div>
                        <Button
                            variant="secondary"
                            size="compact"
                            @click="addStep(variant)"
                            ><Plus :size="14" />{{
                                t('recipes.add_step')
                            }}</Button
                        >
                    </div>
                </Card>

                <div class="flex flex-wrap justify-between gap-3">
                    <Button variant="secondary" @click="addVariant"
                        ><Plus :size="15" />{{
                            t('recipes.add_variant')
                        }}</Button
                    >
                    <div class="flex gap-2">
                        <Link
                            :href="
                                recipe
                                    ? route('recipes.show', recipe.id)
                                    : route('recipes.index')
                            "
                            ><Button variant="secondary">{{
                                t('common.cancel')
                            }}</Button></Link
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing
                                ? t('common.saving')
                                : t('common.save')
                        }}</Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
