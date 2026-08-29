import { expect, test, type Page, type Route } from '@playwright/test';

const pendingConversationId = '019fef6f-a4ab-7813-a09c-518d7157e2e0';
const pendingWorkerConversationId = '019fef6f-a4ab-7813-a09c-518d7157e2e3';
const resolvedFailureConversationId = '019fef6f-a4ab-7813-a09c-518d7157e2e5';
const latestFailureConversationId = '019fef6f-a4ab-7813-a09c-518d7157e2e9';

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('test@test.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/);
}

async function fulfillStream(
    route: Route,
    parts: object[],
    headers: Record<string, string> = {},
): Promise<void> {
    await route.fulfill({
        status: 200,
        headers: {
            'Content-Type': 'text/event-stream',
            'x-vercel-ai-ui-message-stream': 'v1',
            ...headers,
        },
        body: `${parts.map((part) => `data: ${JSON.stringify(part)}\n\n`).join('')}data: [DONE]\n\n`,
    });
}

function readShiftsFixture(text: string): object[] {
    return [
        { type: 'start', messageId: 'assistant-message' },
        {
            type: 'tool-input-available',
            toolCallId: 'e2e-read-shifts',
            toolName: 'read_shifts',
            input: { limit: 20 },
        },
        {
            type: 'tool-output-available',
            toolCallId: 'e2e-read-shifts',
            output: { resource: 'shifts', records: [], count: 0 },
        },
        { type: 'text-start', id: 'text-part' },
        { type: 'text-delta', id: 'text-part', delta: text },
        { type: 'text-end', id: 'text-part' },
        { type: 'finish', finishReason: 'stop' },
    ];
}

function continuationFixture(
    text: string,
    denied = false,
    toolCallId = 'e2e-cross-store-transfer',
): object[] {
    return [
        { type: 'start', messageId: 'assistant-continuation' },
        {
            type: denied ? 'tool-output-denied' : 'tool-output-available',
            toolCallId,
            ...(denied
                ? {}
                : {
                      output: {
                          operation: 'create_stock_movement',
                          status: 'succeeded',
                      },
                  }),
        },
        { type: 'text-start', id: 'continuation-text' },
        { type: 'text-delta', id: 'continuation-text', delta: text },
        { type: 'text-end', id: 'continuation-text' },
        { type: 'finish', finishReason: 'stop' },
    ];
}

function choiceFixture(): object[] {
    const options = [
        { id: 'brno', label: 'Brno branch' },
        {
            id: 'ostrava',
            label: 'Ostrava depot',
            description: 'Use the destination warehouse.',
        },
    ];

    return [
        { type: 'start', messageId: 'assistant-choice' },
        {
            type: 'tool-input-available',
            toolCallId: 'e2e-choice',
            toolName: 'ask_user_choice',
            input: { question: 'Which store should be used?', options },
        },
        {
            type: 'tool-approval-request',
            toolCallId: 'e2e-choice',
            approvalId: 'e2e-choice',
            reason: JSON.stringify({
                version: 1,
                kind: 'choice',
                question: 'Which store should be used?',
                options,
            }),
        },
        { type: 'finish', finishReason: 'tool-calls' },
    ];
}

test.describe('main-admin AI assistant', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('appears directly below Workers in the Management section', async ({
        page,
    }) => {
        const managementItems = await page
            .getByTestId('nav-section-management')
            .locator('[data-testid^="nav-item-"]')
            .evaluateAll((items) =>
                items.map((item) => item.getAttribute('data-testid')),
            );

        expect(managementItems.indexOf('nav-item-assistant')).toBe(
            managementItems.indexOf('nav-item-workers') + 1,
        );
    });

    test('uses a full-height workspace with an adjacent conversation sidebar', async ({
        page,
    }) => {
        await page.goto('/assistant');

        const workspace = page.getByTestId('assistant-workspace');
        const conversations = page.getByTestId(
            'assistant-conversation-sidebar',
        );
        const content = page.getByTestId('assistant-main-content');

        await expect(workspace).toBeVisible();
        await expect(conversations).toBeVisible();
        await expect(content).toBeVisible();
        await expect(conversations.getByText(/processed by/i)).toHaveCount(0);

        const viewport = page.viewportSize();
        const workspaceBox = await workspace.boundingBox();
        const conversationsBox = await conversations.boundingBox();
        const contentBox = await content.boundingBox();

        expect(viewport).not.toBeNull();
        expect(workspaceBox).not.toBeNull();
        expect(conversationsBox).not.toBeNull();
        expect(contentBox).not.toBeNull();

        expect(workspaceBox?.x).toBe(256);
        expect(workspaceBox?.y).toBe(0);
        expect(workspaceBox?.width).toBe((viewport?.width ?? 0) - 256);
        expect(workspaceBox?.height).toBe(viewport?.height);
        expect(conversationsBox?.x).toBe(workspaceBox?.x);
        expect(contentBox?.x).toBe(
            (conversationsBox?.x ?? 0) + (conversationsBox?.width ?? 0),
        );
        expect((contentBox?.x ?? 0) + (contentBox?.width ?? 0)).toBe(
            (workspaceBox?.x ?? 0) + (workspaceBox?.width ?? 0),
        );
    });

    test('matches the send button height to the composer on desktop and mobile', async ({
        page,
    }) => {
        await page.goto('/assistant');

        for (const viewport of [
            { width: 1280, height: 720 },
            { width: 390, height: 844 },
        ]) {
            await page.setViewportSize(viewport);

            const composerBox = await page
                .getByPlaceholder(
                    'Ask Stockflow a question or describe a task…',
                )
                .boundingBox();
            const sendBox = await page
                .getByRole('button', { name: 'Send message' })
                .boundingBox();

            expect(composerBox).not.toBeNull();
            expect(sendBox).not.toBeNull();
            expect(
                Math.abs((composerBox?.height ?? 0) - (sendBox?.height ?? 0)),
            ).toBeLessThanOrEqual(1);
            expect(sendBox?.width).toBeGreaterThanOrEqual(48);
        }
    });

    test('does not resurrect a failed turn after its successful retry', async ({
        page,
    }) => {
        await page.goto(
            `/assistant/conversations/${resolvedFailureConversationId}`,
        );

        await expect(
            page.getByText('Recovered answer after retry.'),
        ).toBeVisible();
        await expect(page.getByText('Stale retry input')).toHaveCount(0);
        await expect(
            page.getByText(
                'The assistant response was interrupted. You can safely retry it.',
            ),
        ).toHaveCount(0);
    });

    test('keeps a genuine latest failure visible and retryable', async ({
        page,
    }) => {
        await page.goto(
            `/assistant/conversations/${latestFailureConversationId}`,
        );

        await expect(page.getByText('Latest failed input')).toBeVisible();
        await expect(
            page.getByText(
                'The assistant response was interrupted. You can safely retry it.',
            ),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Retry', exact: true }),
        ).toBeVisible();
    });

    test('keeps new chat in the conversation sidebar and opens saved conversations at the latest message', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 360 });
        await page.goto('/assistant');

        const sidebar = page.getByTestId('assistant-conversation-sidebar');
        const mainContent = page.getByTestId('assistant-main-content');

        await expect(
            sidebar.getByRole('link', { name: 'New chat', exact: true }),
        ).toHaveCount(1);
        await expect(
            mainContent.getByRole('button', {
                name: 'New chat',
                exact: true,
            }),
        ).toHaveCount(0);

        await sidebar
            .getByRole('link', {
                name: 'Pending cross-store transfer',
                exact: true,
            })
            .click();
        await page.waitForURL(
            `/assistant/conversations/${pendingConversationId}`,
        );
        await expect(
            mainContent.getByRole('button', {
                name: 'New chat',
                exact: true,
            }),
        ).toHaveCount(0);
        await expect(
            sidebar
                .getByRole('button', { name: 'Delete conversation' })
                .first(),
        ).toBeVisible();

        const messageViewport = mainContent.locator('[aria-live="polite"]');
        const maximumScroll = await messageViewport.evaluate(
            (element) => element.scrollHeight - element.clientHeight,
        );

        expect(maximumScroll).toBeGreaterThan(0);
        await expect
            .poll(() =>
                messageViewport.evaluate(
                    (element) =>
                        element.scrollHeight -
                        element.clientHeight -
                        element.scrollTop,
                ),
            )
            .toBeLessThanOrEqual(2);
    });

    test('streams a bounded read_shifts answer from live-data chat', async ({
        page,
    }) => {
        await page.route('**/assistant/chat', (route) =>
            fulfillStream(
                route,
                readShiftsFixture('No shifts are scheduled in this period.'),
            ),
        );

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Which shifts are scheduled in the current store?');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(
            page.getByText('No shifts are scheduled in this period.'),
        ).toBeVisible();
        await expect(
            page.getByText(
                'Review results before relying on them. Every proposed set of changes requires your explicit approval.',
            ),
        ).toHaveCount(0);
    });

    test('renders text deltas before the assistant response finishes', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            const originalFetch = window.fetch.bind(window);

            window.fetch = async (input, init) => {
                const url =
                    typeof input === 'string'
                        ? input
                        : input instanceof URL
                          ? input.toString()
                          : input.url;

                if (!url.includes('/assistant/chat')) {
                    return originalFetch(input, init);
                }

                const encoder = new TextEncoder();
                const event = (part: object): Uint8Array =>
                    encoder.encode(`data: ${JSON.stringify(part)}\n\n`);
                const stream = new ReadableStream<Uint8Array>({
                    start(controller) {
                        controller.enqueue(
                            event({
                                type: 'start',
                                messageId: 'streaming-text-message',
                            }),
                        );
                        controller.enqueue(
                            event({
                                type: 'text-start',
                                id: 'streaming-text-part',
                            }),
                        );

                        window.setTimeout(() => {
                            controller.enqueue(
                                event({
                                    type: 'text-delta',
                                    id: 'streaming-text-part',
                                    delta: 'Streaming begins',
                                }),
                            );
                        }, 100);

                        window.setTimeout(() => {
                            controller.enqueue(
                                event({
                                    type: 'text-delta',
                                    id: 'streaming-text-part',
                                    delta: ' before the response ends.',
                                }),
                            );
                        }, 800);

                        window.setTimeout(() => {
                            controller.enqueue(
                                event({
                                    type: 'text-end',
                                    id: 'streaming-text-part',
                                }),
                            );
                            controller.enqueue(
                                event({
                                    type: 'finish',
                                    finishReason: 'stop',
                                }),
                            );
                            controller.enqueue(
                                encoder.encode('data: [DONE]\n\n'),
                            );
                            controller.close();
                        }, 900);
                    },
                });

                return new Response(stream, {
                    status: 200,
                    headers: {
                        'Content-Type': 'text/event-stream',
                        'x-vercel-ai-ui-message-stream': 'v1',
                    },
                });
            };
        });

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Stream the answer.');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(page.getByText('Streaming begins')).toBeVisible();
        await expect(
            page.getByText('Streaming begins before the response ends.'),
        ).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Stop' })).toBeVisible();
        await expect(
            page.getByText('Streaming begins before the response ends.'),
        ).toBeVisible();
    });

    test('adds a new conversation to the sidebar immediately and reveals message times on hover', async ({
        page,
    }) => {
        const conversationId = '019fef6f-a4ab-7813-a09c-518d7157e2f1';

        await page.route('**/assistant/chat', (route) =>
            fulfillStream(
                route,
                readShiftsFixture('The new conversation is ready.'),
                {
                    'x-conversation-id': conversationId,
                    'x-conversation-title': 'My%20new%20conversation',
                },
            ),
        );

        await page.goto('/assistant');
        await expect(
            page
                .getByTestId('assistant-conversation-sidebar')
                .getByText('My new conversation'),
        ).toHaveCount(0);

        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Create my first conversation');
        const responsePromise = page.waitForResponse('**/assistant/chat');
        await page.getByRole('button', { name: 'Send message' }).click();
        const response = await responsePromise;

        expect(await response.headerValue('x-conversation-id')).toBe(
            conversationId,
        );
        expect(await response.headerValue('x-conversation-title')).toBe(
            'My%20new%20conversation',
        );

        const sidebarConversation = page
            .getByTestId('assistant-conversation-sidebar')
            .getByRole('link', { name: 'My new conversation' });
        await expect(sidebarConversation).toBeVisible();
        await expect(sidebarConversation).toHaveAttribute(
            'href',
            new RegExp(`/assistant/conversations/${conversationId}$`),
        );

        const firstMessage = page
            .getByTestId('assistant-message')
            .filter({ hasText: 'Create my first conversation' });
        const timestamp = firstMessage.getByTestId(
            'assistant-message-timestamp',
        );

        await expect(timestamp).toHaveAttribute('datetime', /2026-/);
        await expect(timestamp).toHaveCSS('opacity', '0');
        await firstMessage.hover();
        await expect(timestamp).toHaveCSS('opacity', '1');
    });

    test('renders assistant Markdown safely while user messages remain plain text', async ({
        page,
    }) => {
        await page.route('**/assistant/chat', (route) =>
            fulfillStream(
                route,
                readShiftsFixture(
                    '# Summary\n\n- **One**\n- Two\n\n| Store | Count |\n| --- | ---: |\n| Brno | 2 |\n\n[Safe link](https://example.com) [Unsafe](javascript:alert(1)) ![Remote](https://example.com/image.png) <script>window.markdownExecuted = true</script>\n\n```js\nconst ready = true;\n```',
                ),
            ),
        );

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('**Do not render this user text as Markdown.**');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(
            page.getByRole('heading', { name: 'Summary' }),
        ).toBeVisible();
        await expect(page.locator('table').getByText('Brno')).toBeVisible();
        await expect(page.locator('pre code')).toContainText(
            'const ready = true;',
        );
        await expect(
            page.getByRole('link', { name: 'Safe link' }),
        ).toHaveAttribute('rel', 'noopener noreferrer');
        await expect(
            page.locator('article script, article iframe, article img'),
        ).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Unsafe' })).toHaveCount(0);
        await expect(
            page.getByText('**Do not render this user text as Markdown.**', {
                exact: true,
            }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () =>
                    (window as Window & { markdownExecuted?: boolean })
                        .markdownExecuted,
            ),
        ).toBeUndefined();
    });

    test('shows action progress before a delayed approval and reveals buttons without reload', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            const originalFetch = window.fetch.bind(window);

            window.fetch = async (input, init) => {
                const url =
                    typeof input === 'string'
                        ? input
                        : input instanceof URL
                          ? input.toString()
                          : input.url;

                if (!url.includes('/assistant/chat')) {
                    return originalFetch(input, init);
                }

                const encoder = new TextEncoder();
                const event = (part: object): Uint8Array =>
                    encoder.encode(`data: ${JSON.stringify(part)}\n\n`);
                const stream = new ReadableStream<Uint8Array>({
                    start(controller) {
                        controller.enqueue(
                            event({
                                type: 'start',
                                messageId: 'delayed-approval-message',
                            }),
                        );
                        controller.enqueue(
                            event({
                                type: 'tool-input-start',
                                toolCallId: 'delayed-worker-call',
                                toolName: 'write_workers',
                            }),
                        );

                        window.setTimeout(() => {
                            controller.enqueue(
                                event({
                                    type: 'tool-input-delta',
                                    toolCallId: 'delayed-worker-call',
                                    inputTextDelta:
                                        '{"request":{"action":"create_worker"',
                                }),
                            );
                        }, 150);

                        window.setTimeout(() => {
                            const input = {
                                request: {
                                    action: 'create_worker',
                                    values: {
                                        first_name: 'Jan',
                                        last_name: 'Novák',
                                        hourly_rate: 180,
                                    },
                                },
                            };
                            controller.enqueue(
                                event({
                                    type: 'tool-input-available',
                                    toolCallId: 'delayed-worker-call',
                                    toolName: 'write_workers',
                                    input,
                                }),
                            );
                            controller.enqueue(
                                event({
                                    type: 'tool-approval-request',
                                    toolCallId: 'delayed-worker-call',
                                    approvalId: 'delayed-worker-call',
                                    reason: JSON.stringify({
                                        version: 2,
                                        kind: 'action_confirmation',
                                        summary_key:
                                            'assistant.action_summaries.create_worker',
                                        summary_params: {
                                            first_name: 'Jan',
                                            last_name: 'Novák',
                                            hourly_rate: 180,
                                        },
                                    }),
                                }),
                            );
                            controller.enqueue(
                                event({
                                    type: 'finish',
                                    finishReason: 'tool-calls',
                                }),
                            );
                            controller.enqueue(
                                encoder.encode('data: [DONE]\n\n'),
                            );
                            controller.close();
                        }, 900);
                    },
                });

                return new Response(stream, {
                    status: 200,
                    headers: {
                        'Content-Type': 'text/event-stream',
                        'x-vercel-ai-ui-message-stream': 'v1',
                    },
                });
            };
        });

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Create Jan Novák as a worker.');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(
            page.getByText('Preparing the action…').first(),
        ).toBeVisible();
        await expect(
            page.locator('[data-tool-call-id="delayed-worker-call"]'),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Perform' }),
        ).toBeVisible();
        await expect(
            page.getByText(
                'Create worker Jan Novák with an hourly rate of 180 CZK.',
            ),
        ).toBeVisible();
    });

    test('confirms every proposed action in one review and one click', async ({
        page,
    }) => {
        const toolCalls = Array.from({ length: 30 }, (_, index) => ({
            id: `batch-shift-${index + 1}`,
            date: `2026-09-${String(index + 1).padStart(2, '0')}`,
            start: '10:00',
            end: '16:00',
        }));
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/assistant/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(route, [
                    { type: 'start', messageId: 'batch-continuation' },
                    ...toolCalls.map((toolCall) => ({
                        type: 'tool-output-available',
                        toolCallId: toolCall.id,
                        output: {
                            operation: 'create_shift',
                            status: 'succeeded',
                        },
                    })),
                    { type: 'finish', finishReason: 'stop' },
                ]);

                return;
            }

            await fulfillStream(route, [
                { type: 'start', messageId: 'batch-approval' },
                ...toolCalls.flatMap((toolCall) => [
                    {
                        type: 'tool-input-available',
                        toolCallId: toolCall.id,
                        toolName: 'write_shifts',
                        input: {
                            request: {
                                action: 'create_shift',
                                values: {
                                    date: toolCall.date,
                                    start_time: toolCall.start,
                                    end_time: toolCall.end,
                                },
                            },
                        },
                    },
                    {
                        type: 'tool-approval-request',
                        toolCallId: toolCall.id,
                        approvalId: toolCall.id,
                        reason: JSON.stringify({
                            version: 2,
                            kind: 'action_confirmation',
                            summary_key:
                                'assistant.action_summaries.create_shift',
                            summary_params: {
                                date: toolCall.date,
                                start_time: toolCall.start,
                                end_time: toolCall.end,
                                store: 'Brno branch',
                            },
                        }),
                    },
                ]),
                { type: 'text-start', id: 'batch-explanation' },
                {
                    type: 'text-delta',
                    id: 'batch-explanation',
                    delta: 'I prepared the complete monthly schedule.',
                },
                { type: 'text-end', id: 'batch-explanation' },
                { type: 'finish', finishReason: 'tool-calls' },
            ]);
        });

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Create these 30 shifts.');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(page.getByRole('button', { name: 'Perform' })).toHaveCount(
            1,
        );
        await expect(page.getByRole('button', { name: 'Cancel' })).toHaveCount(
            1,
        );
        await expect(
            page.getByText('Review all 30 changes and confirm them together.'),
        ).toBeVisible();
        const explanation = page.getByText(
            'I prepared the complete monthly schedule.',
        );
        const approvalGroup = page.getByTestId('assistant-approval-group');

        await expect(explanation).toBeVisible();
        await expect(approvalGroup).toBeVisible();
        expect(
            await explanation.evaluate(
                (element, approval) => {
                    return (
                        element.closest('article') !==
                            approval.closest('article') &&
                        Boolean(
                            element.compareDocumentPosition(approval) &
                            Node.DOCUMENT_POSITION_FOLLOWING,
                        )
                    );
                },
                await approvalGroup.elementHandle(),
            ),
        ).toBe(true);
        await expect(
            page.getByText(/2026-09-01.+10:00.+16:00.+Brno branch/),
        ).toBeVisible();
        await expect(
            page.getByText(/2026-09-30.+10:00.+16:00.+Brno branch/),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Perform' }).click();

        await expect.poll(() => continuationBody).not.toBeNull();
        const decisions = continuationBody?.decisions as Record<
            string,
            unknown
        >;

        expect(Object.keys(decisions)).toHaveLength(30);
        expect(decisions).toMatchObject({
            'batch-shift-1': { action: 'approve' },
            'batch-shift-30': { action: 'approve' },
        });
        await expect(page.getByText('Finishing the action…')).toHaveCount(0);
    });

    test('submits one locked clarification option without performing a mutation', async ({
        page,
    }) => {
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/assistant/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(
                    route,
                    continuationFixture(
                        'I will use Ostrava depot.',
                        false,
                        'e2e-choice',
                    ),
                );

                return;
            }

            await fulfillStream(route, choiceFixture());
        });

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Use one of my stores.');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(
            page.getByText('Which store should be used?'),
        ).toBeVisible();
        await page.getByRole('button', { name: /B\s*Ostrava depot/ }).click();
        await expect(page.getByText('I will use Ostrava depot.')).toBeVisible();
        expect(continuationBody).toMatchObject({
            decisions: {
                'e2e-choice': {
                    action: 'select',
                    option_id: 'ostrava',
                },
            },
        });
        expect(JSON.stringify(continuationBody)).not.toContain('arguments');
    });

    test('replaces an HTML server failure with a bounded error', async ({
        page,
    }) => {
        await page.route('**/assistant/chat', (route) =>
            route.fulfill({
                status: 500,
                contentType: 'text/html',
                body: '<!DOCTYPE html><html><body>Connection refused and Laravel debug trace</body></html>',
            }),
        );

        await page.goto('/assistant');
        await page
            .getByPlaceholder('Ask Stockflow a question or describe a task…')
            .fill('Hello');
        await page.getByRole('button', { name: 'Send message' }).click();

        await expect(
            page.getByText(
                'The AI assistant is temporarily unavailable. Please try again.',
            ),
        ).toBeVisible();
        await expect(page.getByText(/DOCTYPE|Connection refused/)).toHaveCount(
            0,
        );
    });

    test('reviews and individually approves a stock movement without editing arguments', async ({
        page,
    }) => {
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/assistant/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(
                    route,
                    continuationFixture(
                        'The approved stock movement completed.',
                    ),
                );

                return;
            }

            await route.continue();
        });

        await page.goto(`/assistant/conversations/${pendingConversationId}`);

        const approval = page.locator(
            '[data-tool-call-id="e2e-cross-store-transfer"]',
        );
        await expect(approval.getByText('Confirm action')).toBeVisible();
        await expect(approval.locator('input, textarea, select')).toHaveCount(
            0,
        );
        await page.getByRole('button', { name: 'Perform' }).click();

        await expect(
            page.getByText('The approved stock movement completed.'),
        ).toBeVisible();
        expect(continuationBody).toMatchObject({
            decisions: {
                'e2e-cross-store-transfer': {
                    action: 'approve',
                },
            },
        });
    });

    test('rejects one proposed mutation without a blanket decision', async ({
        page,
    }) => {
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/assistant/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(
                    route,
                    continuationFixture(
                        'The proposed movement was rejected.',
                        true,
                    ),
                );

                return;
            }

            await route.continue();
        });

        await page.goto(`/assistant/conversations/${pendingConversationId}`);
        const approval = page.locator(
            '[data-tool-call-id="e2e-cross-store-transfer"]',
        );
        await expect(approval.locator('input, textarea, select')).toHaveCount(
            0,
        );
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();

        await expect(
            page.getByText('The proposed movement was rejected.'),
        ).toBeVisible();
        expect(continuationBody).toMatchObject({
            decisions: {
                'e2e-cross-store-transfer': {
                    action: 'reject',
                },
            },
        });
        expect(
            Object.keys(
                (continuationBody?.decisions as Record<string, unknown>) ?? {},
            ),
        ).toEqual(['e2e-cross-store-transfer']);
    });

    test('reloads and resumes a persisted cross-store approval', async ({
        page,
    }) => {
        await page.goto(`/assistant/conversations/${pendingConversationId}`);
        const approval = page.locator(
            '[data-tool-call-id="e2e-cross-store-transfer"]',
        );
        await expect(approval.getByText('Confirm action')).toBeVisible();
        await expect(approval.getByText(/Ostrava depo/)).toBeVisible();
        await expect(
            page
                .getByRole('combobox', { name: 'Active store' })
                .locator('option:checked'),
        ).toHaveText('Brno pobočka');

        await page.reload();

        await expect(approval.getByText('Confirm action')).toBeVisible();
        await expect(approval.getByText(/Ostrava depo/)).toBeVisible();
    });

    test('renders a non-technical worker confirmation with only perform and cancel', async ({
        page,
    }) => {
        await page.goto(
            `/assistant/conversations/${pendingWorkerConversationId}`,
        );

        const approval = page.locator(
            '[data-tool-call-id="e2e-create-worker"]',
        );
        await expect(approval.getByText('Confirm action')).toBeVisible();
        await expect(
            approval.getByText(
                'Create worker E2E Proposal with an hourly rate of 130 CZK.',
                { exact: true },
            ),
        ).toBeVisible();
        await expect(approval.locator('input, textarea, select')).toHaveCount(
            0,
        );
        await expect(
            approval.getByText(/write_workers|create_worker|#\d+/),
        ).toHaveCount(0);
        await expect(approval.getByText('Resolved store')).toHaveCount(0);
        await expect(approval.getByText('Locked context')).toHaveCount(0);
        await expect(
            approval.getByRole('button', { name: 'Perform' }),
        ).toBeVisible();
        await expect(
            approval.getByRole('button', { name: 'Cancel', exact: true }),
        ).toBeVisible();
    });

    test('remains usable at a mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/assistant');

        await expect(
            page.getByRole('heading', { name: 'AI Assistant' }),
        ).toBeVisible();
        await expect(
            page.getByPlaceholder(
                'Ask Stockflow a question or describe a task…',
            ),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Conversations', exact: true })
            .click();

        await expect(
            page.getByRole('complementary', {
                name: 'Conversations',
                exact: true,
            }),
        ).toBeVisible();
    });
});
