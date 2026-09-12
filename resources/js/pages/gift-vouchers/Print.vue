<script setup lang="ts">
import teachaLogo from '../../../images/teacha-logo-print.svg';
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
    branches: { id: number; name: string; address: string | null }[];
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
    await document.fonts.ready;
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
                <svg
                    class="voucher-botanical"
                    viewBox="0 0 120 150"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M26 145C28 94 51 52 96 10M43 93C16 91 10 73 11 60C36 59 48 73 43 93ZM59 64C57 40 67 27 83 25C89 43 77 59 59 64Z"
                    />
                    <path
                        d="M32 122C49 101 67 96 83 102C76 122 56 130 32 122ZM76 43C91 28 105 29 115 34C105 50 90 53 76 43"
                    />
                </svg>
                <div class="voucher-copy">
                    <header class="voucher-brand">
                        <img
                            :src="teachaLogo"
                            alt="teacha"
                            class="voucher-logo"
                        />
                        <h1 class="voucher-title">
                            {{ t('gift_vouchers.print.eyebrow') }}
                        </h1>
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
                        <div class="voucher-stamp">
                            {{ t('gift_vouchers.print.stamp_signature') }}
                        </div>
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
                <footer class="voucher-footer">
                    <p class="voucher-redemption">
                        {{ t('gift_vouchers.print.any_branch') }}
                    </p>
                    <ul v-if="branches.length" class="voucher-branches">
                        <li v-for="branch in branches" :key="branch.id">
                            <strong>{{ branch.name }}</strong
                            ><template v-if="branch.address">
                                · {{ branch.address }}</template
                            >
                        </li>
                    </ul>
                    <p class="voucher-terms">
                        {{ t('gift_vouchers.print.terms') }}
                    </p>
                </footer>
            </article>
        </section>
    </main>
</template>

<style scoped>
.print-root {
    --voucher-green: #344c28;
    margin: 0 auto;
    width: 190mm;
    background: #fff;
    color: var(--voucher-green);
    font-family: Arial, sans-serif;
}

.voucher-sheet {
    display: grid;
    grid-template-rows: repeat(3, minmax(0, 1fr));
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
    grid-template-columns: minmax(0, 1fr) 48mm;
    grid-template-rows: minmax(0, 1fr) auto;
    min-height: 0;
    break-inside: avoid;
    border-bottom: 0.25mm dashed #8c987f;
    background: #faf8f1;
}

.voucher::before {
    position: absolute;
    inset: 4mm;
    border: 0.25mm solid #bdc7b2;
    content: '';
    pointer-events: none;
}

.voucher-botanical {
    position: absolute;
    top: 6mm;
    right: 50mm;
    width: 18mm;
    height: 24mm;
    stroke: #a3b393;
    stroke-width: 1.3;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.voucher-copy {
    display: flex;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    padding: 8mm 7mm 3mm 10mm;
}

.voucher-brand {
    display: flex;
    align-items: center;
    gap: 4mm;
    padding-right: 16mm;
}

.voucher-logo {
    flex: none;
    width: 15mm;
    height: 15mm;
    object-fit: contain;
}

.voucher-title {
    margin: 0;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 6mm;
    font-weight: 400;
    line-height: 1.1;
}

.voucher-value {
    margin-top: 4mm;
    font-size: 9mm;
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -0.04em;
}

.voucher-message {
    margin: 2mm 0 0;
    color: #46523e;
    font-size: 2.8mm;
    line-height: 1.45;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.voucher-meta {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 4mm;
    margin-top: auto;
    padding-top: 3mm;
    color: #46523e;
    font-size: 2.5mm;
    line-height: 1.35;
}

.voucher-stamp {
    display: flex;
    flex: none;
    align-items: end;
    justify-content: center;
    width: 45mm;
    height: 15mm;
    padding-bottom: 1mm;
    border: 0.2mm dashed #8c987f;
    color: #46523e;
    font-size: 2.2mm;
}

.voucher-code-panel {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-block: 8mm 3mm;
    border-left: 0.25mm solid #bdc7b2;
    padding: 5mm 6mm 5mm 4mm;
}

.voucher-qr {
    width: 32mm;
    height: 32mm;
    flex: none;
    background: #fff;
}

.voucher-code {
    margin: 3mm 0 0;
    color: #1b2417;
    font-family: ui-monospace, monospace;
    font-size: 2.65mm;
    font-weight: 700;
    white-space: nowrap;
}

.voucher-code-help {
    margin: 2mm 0 0;
    color: #46523e;
    font-size: 2.6mm;
    line-height: 1.45;
    text-align: center;
}

.voucher-footer {
    grid-column: 1 / -1;
    margin: 0 10mm 7mm;
    border-top: 0.25mm solid #bdc7b2;
    padding-top: 2.5mm;
    color: #46523e;
    font-size: 2.4mm;
    line-height: 1.4;
}

.voucher-redemption {
    margin: 0;
    font-weight: 700;
}
.voucher-branches {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7mm 4mm;
    margin: 1mm 0;
    padding: 0;
    list-style: none;
    overflow-wrap: anywhere;
}
.voucher-terms {
    margin: 1mm 0 0;
}

@media screen {
    .print-root {
        margin-block: 20px;
        box-shadow: 0 8px 40px rgb(52 76 40 / 12%);
    }
}

@media print {
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
