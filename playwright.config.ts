import { defineConfig, devices } from '@playwright/test';

const e2ePort = process.env.E2E_PORT ?? '8010';
const e2eBaseUrl = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${e2ePort}`;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 30000,
    use: {
        baseURL: e2eBaseUrl,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        actionTimeout: 10000,
        navigationTimeout: 15000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: `php artisan optimize:clear && php artisan migrate:fresh --env=testing --force && php artisan db:seed --class=Database\\\\Seeders\\\\E2ESeeder --env=testing --force && php artisan serve --host=127.0.0.1 --port=${e2ePort} --no-reload`,
        url: e2eBaseUrl,
        reuseExistingServer: false,
        timeout: 60000,
        env: {
            APP_ENV: 'testing',
            APP_URL: e2eBaseUrl,
            AI_ASSISTANT_ENABLED: 'true',
            CACHE_STORE: 'array',
            E2E_DISABLE_THROTTLE: 'true',
            SESSION_SECURE_COOKIE: 'false',
            SESSION_DRIVER: 'file',
            MAIL_MAILER: 'log',
        },
    },
});
