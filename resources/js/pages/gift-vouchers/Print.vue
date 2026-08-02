<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatDate, formatMoney } from '@/lib/format';

type PrintableVoucher = {
    id: number;
    code: string;
    amount: number;
    qr: string;
};

defineProps<{
    batch: {
        id: number;
        brand_name: string;
        brand_message: string | null;
        brand_logo: string | null;
        expires_at: string | null;
    };
    sheets: PrintableVoucher[][];
}>();

const { t } = useI18n();
useBoundLocale();

onMounted(async () => {
    await nextTick();
    await Promise.all(
        Array.from(document.images).map((item) =>
            item.complete
                ? Promise.resolve()
                : new Promise<void>((resolve) => {
                      item.addEventListener('load', () => resolve(), {
                          once: true,
                      });
                      item.addEventListener('error', () => resolve(), {
                          once: true,
                      });
                  }),
        ),
    );
    window.print();
});
</script>

<template>
    <Head :title="t('gift_vouchers.print.title')" />
    <main class="print-root">
        <section
            v-for="(sheet, sheetIndex) in sheets"
            :key="sheetIndex"
            class="voucher-sheet"
            data-testid="gift-voucher-sheet"
        >
            <article
                v-for="voucher in sheet"
                :key="voucher.id"
                class="voucher"
                data-testid="gift-voucher-print-item"
            >
                <div class="voucher-accent" aria-hidden="true"></div>
                <div class="voucher-copy">
                    <header class="voucher-brand">
                        <img
                            v-if="batch.brand_logo"
                            :src="batch.brand_logo"
                            :alt="batch.brand_name"
                            class="voucher-logo"
                        />
                        <div v-else class="voucher-monogram" aria-hidden="true">
                            {{ batch.brand_name.slice(0, 1).toUpperCase() }}
                        </div>
                        <div>
                            <p class="voucher-brand-name">
                                {{ batch.brand_name }}
                            </p>
                            <p class="voucher-kicker">
                                {{ t('gift_vouchers.print.eyebrow') }}
                            </p>
                        </div>
                    </header>

                    <div class="voucher-value">
                        {{ formatMoney(voucher.amount) }}
                    </div>
                    <p class="voucher-message">
                        {{
                            batch.brand_message ||
                            t('gift_vouchers.print.default_message')
                        }}
                    </p>

                    <div class="voucher-meta">
                        <span v-if="batch.expires_at">
                            {{
                                t('gift_vouchers.print.valid_until', {
                                    date: formatDate(batch.expires_at),
                                })
                            }}
                        </span>
                        <span v-else>
                            {{ t('gift_vouchers.print.no_expiration') }}
                        </span>
                        <span>{{ t('gift_vouchers.print.one_use') }}</span>
                    </div>
                </div>

                <div class="voucher-code-panel">
                    <img
                        :src="voucher.qr"
                        :alt="t('gift_vouchers.print.qr_alt')"
                        class="voucher-qr"
                    />
                    <p class="voucher-code">{{ voucher.code }}</p>
                    <p class="voucher-code-help">
                        {{ t('gift_vouchers.print.code_help') }}
                    </p>
                </div>
            </article>
        </section>
    </main>
</template>

<style scoped>
.print-root {
    margin: 0 auto;
    width: 190mm;
    background: #fff;
    color: #0f172a;
}

.voucher-sheet {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    width: 190mm;
    height: 277mm;
    break-after: page;
}

.voucher-sheet:last-child {
    break-after: auto;
}

.voucher {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 50mm;
    min-height: 0;
    overflow: hidden;
    break-inside: avoid;
    border-bottom: 0.25mm dashed #94a3b8;
    background:
        radial-gradient(
            circle at 82% 12%,
            rgb(14 116 144 / 10%),
            transparent 32%
        ),
        linear-gradient(135deg, #fff 0%, #f8fafc 100%);
}

.voucher:last-child {
    border-bottom: 0;
}

.voucher-accent {
    position: absolute;
    inset: 0 auto 0 0;
    width: 3mm;
    background: linear-gradient(180deg, #0f172a, #0e7490);
}

.voucher-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    padding: 9mm 7mm 7mm 11mm;
}

.voucher-brand {
    display: flex;
    align-items: center;
    gap: 3mm;
}

.voucher-logo {
    width: auto;
    max-width: 30mm;
    height: 11mm;
    object-fit: contain;
}

.voucher-monogram {
    display: flex;
    width: 11mm;
    height: 11mm;
    align-items: center;
    justify-content: center;
    border-radius: 3mm;
    background: #0f172a;
    color: #fff;
    font-size: 5mm;
    font-weight: 800;
}

.voucher-brand-name {
    margin: 0;
    font-size: 4mm;
    font-weight: 800;
    line-height: 1.1;
}

.voucher-kicker {
    margin: 1mm 0 0;
    color: #64748b;
    font-size: 2.3mm;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.voucher-value {
    margin-top: auto;
    font-size: 11mm;
    font-weight: 850;
    line-height: 1;
    letter-spacing: -0.04em;
}

.voucher-message {
    max-width: 105mm;
    margin: 3mm 0 0;
    color: #475569;
    font-size: 3mm;
    line-height: 1.45;
}

.voucher-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 2mm 5mm;
    margin-top: auto;
    padding-top: 4mm;
    color: #64748b;
    font-size: 2.35mm;
    font-weight: 650;
}

.voucher-code-panel {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-left: 0.25mm solid #e2e8f0;
    padding: 6mm;
    background: rgb(255 255 255 / 75%);
}

.voucher-qr {
    width: 34mm;
    height: 34mm;
}

.voucher-code {
    margin: 3mm 0 0;
    font-family: 'Geist Mono', ui-monospace, monospace;
    font-size: 2.8mm;
    font-weight: 800;
    letter-spacing: 0.08em;
    white-space: nowrap;
}

.voucher-code-help {
    margin: 1.5mm 0 0;
    color: #64748b;
    font-size: 2mm;
    text-align: center;
}

@media screen {
    .print-root {
        margin-block: 20px;
        box-shadow: 0 8px 40px rgb(15 23 42 / 12%);
    }
}

@media print {
    .print-root,
    .voucher-sheet {
        width: 190mm;
    }

    .voucher-sheet {
        height: 277mm;
    }

    .voucher {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    @page {
        size: A4 portrait;
        margin: 10mm;
    }
}
</style>
