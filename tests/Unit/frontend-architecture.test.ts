import { readdirSync, readFileSync } from 'node:fs';
import { extname, relative, resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const root = resolve(process.cwd(), 'resources/js');
function sources(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const file = resolve(directory, entry.name);
        return entry.isDirectory()
            ? sources(file)
            : ['.vue', '.ts'].includes(extname(file))
              ? [file]
              : [];
    });
}

describe('frontend module dependencies', () => {
    test('feature workflows do not depend on page entrypoints or another feature', () => {
        for (const file of sources(resolve(root, 'features'))) {
            const owner = relative(resolve(root, 'features'), file).split(
                '/',
            )[0];
            const source = readFileSync(file, 'utf8');
            expect(source, file).not.toMatch(/from ['"]@\/pages\//);
            for (const imported of source.matchAll(
                /from ['"]@\/features\/([^/]+)\//g,
            )) {
                expect(imported[1], file).toBe(owner);
            }
        }
    });

    test('shared UI primitives remain independent of domain features', () => {
        for (const file of sources(resolve(root, 'components/ui'))) {
            expect(readFileSync(file, 'utf8'), file).not.toMatch(
                /from ['"]@\/(features|pages)\//,
            );
        }
    });
});
