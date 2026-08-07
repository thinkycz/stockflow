import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const projectRoot = process.cwd();

function source(path: string): string {
    return readFileSync(resolve(projectRoot, path), 'utf8');
}

describe('Teacha PWA contract', () => {
    test('publishes an installable Teacha manifest with complete icon assets', () => {
        const manifest = JSON.parse(source('public/manifest.webmanifest')) as {
            name: string;
            short_name: string;
            start_url: string;
            scope: string;
            display: string;
            theme_color: string;
            background_color: string;
            icons: Array<{
                src: string;
                sizes: string;
                type: string;
                purpose?: string;
            }>;
        };

        expect(manifest).toMatchObject({
            name: 'Teacha',
            short_name: 'Teacha',
            start_url: '/',
            scope: '/',
            display: 'standalone',
            theme_color: '#344c28',
            background_color: '#f8fafc',
        });
        expect(manifest.icons).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    src: '/pwa-192x192.png',
                    sizes: '192x192',
                    type: 'image/png',
                    purpose: 'any',
                }),
                expect.objectContaining({
                    src: '/pwa-512x512.png',
                    sizes: '512x512',
                    type: 'image/png',
                    purpose: 'any',
                }),
                expect.objectContaining({
                    src: '/pwa-maskable-512x512.png',
                    sizes: '512x512',
                    type: 'image/png',
                    purpose: 'maskable',
                }),
            ]),
        );

        for (const icon of manifest.icons) {
            expect(
                existsSync(resolve(projectRoot, 'public', icon.src.slice(1))),
                icon.src,
            ).toBe(true);
        }
    });

    test('keeps the service worker limited to versioned assets and brand icons', () => {
        const serviceWorker = source('public/sw.js');

        expect(serviceWorker).toContain(
            "url.pathname.startsWith('/build/assets/')",
        );
        expect(serviceWorker).toContain("request.mode === 'navigate'");
        expect(serviceWorker).toContain("request.method !== 'GET'");
        expect(serviceWorker).not.toContain('fetch(event.request)');
        expect(serviceWorker).not.toContain('event.request.clone()');
    });

    test('links PWA metadata and uses the shared Teacha mark', () => {
        const shell = source('resources/views/app.blade.php');
        const brand = source('resources/js/components/ui/Brand.vue');
        const entrypoint = source('resources/js/app.ts');

        expect(shell).toContain('rel="manifest" href="/manifest.webmanifest"');
        expect(shell).toContain('name="theme-color" content="#344c28"');
        expect(shell).toContain('rel="apple-touch-icon"');
        expect(shell).toContain('rel="icon" type="image/svg+xml"');
        expect(brand).toContain('/teacha-mark.svg');
        expect(brand).toContain("t('app.name')");
        expect(brand).not.toContain('{{ app.name }}');
        expect(brand).not.toContain('>\n            S\n        </div>');
        expect(entrypoint).toContain('import.meta.env.PROD');
        expect(entrypoint).toContain(
            "navigator.serviceWorker.register('/sw.js')",
        );
    });
});
