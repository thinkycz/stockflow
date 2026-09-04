import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** Read a page and its explicitly imported workflow owners for structural contracts. */
export function workflowSource(
    file: string,
    encoding: 'utf8' = 'utf8',
): string {
    const source = readFileSync(file, encoding);
    const workflowImports = [
        ...source.matchAll(/from ['"](@\/features\/[^'"]+\/use[^'"]+)['"]/g),
    ];

    return [
        source,
        ...workflowImports.map((match) =>
            readFileSync(
                resolve(
                    process.cwd(),
                    'resources/js',
                    `${match[1].slice(2)}.ts`,
                ),
                encoding,
            ),
        ),
    ].join('\n');
}
