<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/ui/FieldError.vue';
import Label from '@/components/ui/Label.vue';

const props = withDefaults(
    defineProps<{
        colors: string[];
        automaticColor?: string | null;
        error?: string;
    }>(),
    { automaticColor: null, error: undefined },
);

const model = defineModel<string>({ required: true });
const { t } = useI18n();

const pickerColor = computed(
    () => model.value || props.automaticColor || props.colors[0] || '#64748B',
);

function chooseColor(color: string): void {
    model.value = color.toUpperCase();
}

function chooseCustomColor(event: Event): void {
    chooseColor((event.target as HTMLInputElement).value);
}
</script>

<template>
    <div class="space-y-2">
        <Label>{{ t('workers.calendar_color.label') }}</Label>
        <p class="text-xs text-on-surface-variant">
            {{ t('workers.calendar_color.help') }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="h-9 rounded-lg border px-3 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                :class="
                    model === ''
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-outline-glass bg-surface text-on-surface hover:border-primary/40'
                "
                :aria-pressed="model === ''"
                @click="model = ''"
            >
                {{ t('workers.calendar_color.automatic') }}
            </button>

            <button
                v-for="color in colors"
                :key="color"
                type="button"
                class="size-9 rounded-full border-2 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                :class="
                    model === color
                        ? 'border-on-surface ring-2 ring-surface ring-offset-1'
                        : 'border-black/10'
                "
                :style="{ backgroundColor: color }"
                :aria-label="t('workers.calendar_color.choose', { color })"
                :aria-pressed="model === color"
                @click="chooseColor(color)"
            />
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <Label for="calendar_color_picker">
                {{ t('workers.calendar_color.custom') }}
            </Label>
            <input
                id="calendar_color_picker"
                type="color"
                :value="pickerColor"
                class="h-9 w-14 cursor-pointer rounded-lg border border-outline-glass bg-surface p-1"
                :aria-label="t('workers.calendar_color.custom')"
                @input="chooseCustomColor"
            />
            <span
                class="flex items-center gap-2 text-xs text-on-surface-variant"
            >
                <span
                    class="size-3 rounded-full border border-black/10"
                    :style="{ backgroundColor: pickerColor }"
                    aria-hidden="true"
                />
                {{
                    model ||
                    (automaticColor
                        ? t('workers.calendar_color.automatic_preview', {
                              color: automaticColor,
                          })
                        : t('workers.calendar_color.automatic_after_save'))
                }}
            </span>
        </div>

        <FieldError :message="error" />
    </div>
</template>
