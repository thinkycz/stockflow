import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const jsRoot = resolve(process.cwd(), 'resources/js');

describe('recipe test session UI contract', () => {
    test('catalog uses the shared accessible menu and owns the test start action', () => {
        const index = readFileSync(
            resolve(jsRoot, 'pages/recipes/Index.vue'),
            'utf8',
        );
        const show = readFileSync(
            resolve(jsRoot, 'pages/recipes/Show.vue'),
            'utf8',
        );
        const menu = readFileSync(
            resolve(jsRoot, 'components/ui/DropdownMenu.vue'),
            'utf8',
        );

        expect(index).toContain('<DropdownMenu');
        expect(index).toContain("route('recipe-test-sessions.store')");
        expect(show).not.toContain('recipe-tests.store');
        expect(menu).toContain("event.key === 'Escape'");
        expect(menu).toContain("event.key === 'ArrowDown'");
        expect(menu).toContain('pointerdown');
    });

    test('wizard keeps all three answers local and submits once', () => {
        const source = readFileSync(
            resolve(jsRoot, 'pages/recipes/TestSession.vue'),
            'utf8',
        );

        expect(source).toContain('currentIndex');
        expect(source).toContain('inputmode="decimal"');
        expect(source).toContain("route('recipe-test-sessions.update'");
        expect(source).toContain('{ answers: answers.value }');
    });
});
