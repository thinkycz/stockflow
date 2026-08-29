import type { AssistantActionApprovalPart } from '@/lib/assistant-chat';

export type BusinessRow = {
    label: string;
    value?: string | null;
};

export type ChoiceOption = {
    id: string;
    label: string;
    description?: string;
};

export type ActionPreview = {
    version: 2;
    kind: 'action_confirmation';
    summary_key: string;
    summary_params?: Record<string, string | number | boolean>;
    business_rows?: BusinessRow[];
};

export type ChoicePreview = {
    version: 1;
    kind: 'choice';
    question: string;
    options: ChoiceOption[];
};

export type ApprovalPreview = ActionPreview | ChoicePreview;

function record(value: unknown): Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function safeSummaryParams(
    ...sources: Record<string, unknown>[]
): Record<string, string | number | boolean> {
    const params: Record<string, string | number | boolean> = {};

    for (const source of sources) {
        for (const [key, value] of Object.entries(source)) {
            if (key === 'id' || key.endsWith('_id')) {
                continue;
            }

            if (
                typeof value === 'string' ||
                typeof value === 'number' ||
                typeof value === 'boolean'
            ) {
                params[key] =
                    typeof value === 'string' ? value.slice(0, 160) : value;
            }

            if (Array.isArray(value)) {
                params[`${key}_count`] = Math.min(value.length, 50);
            }
        }
    }

    return params;
}

function legacyActionPreview(
    part: AssistantActionApprovalPart,
    parsed: Record<string, unknown>,
    hasTranslation: (key: string) => boolean,
): ActionPreview | null {
    const operation = parsed.operation;

    if (
        typeof operation !== 'string' ||
        !/^[a-z][a-z0-9_]{1,80}$/.test(operation)
    ) {
        return null;
    }

    const summaryKey = `assistant.action_summaries.${operation}`;

    if (!hasTranslation(summaryKey)) {
        return null;
    }

    const input = record(part.input);
    const request = record(input.request);
    const sanitized = record(parsed.sanitized_arguments);
    const params = safeSummaryParams(
        {
            ...record(sanitized.values),
            ...record(request.values),
        },
        record(sanitized.context),
        record(request.context),
        request,
    );
    const store = record(parsed.store);

    if (typeof store.name === 'string') {
        params.store = store.name.slice(0, 160);
    }

    return {
        version: 2,
        kind: 'action_confirmation',
        summary_key: summaryKey,
        summary_params: params,
        business_rows: [],
    };
}

export function assistantApprovalPreview(
    part: AssistantActionApprovalPart,
    hasTranslation: (key: string) => boolean,
): ApprovalPreview | null {
    const reason = part.approval?.requestReason;

    if (typeof reason !== 'string' || reason === '') {
        return null;
    }

    try {
        const parsed = record(JSON.parse(reason));

        if (
            parsed.kind === 'choice' &&
            typeof parsed.question === 'string' &&
            Array.isArray(parsed.options)
        ) {
            return {
                version: 1,
                kind: 'choice',
                question: parsed.question,
                options: parsed.options
                    .map(record)
                    .filter(
                        (option) =>
                            typeof option.id === 'string' &&
                            typeof option.label === 'string',
                    )
                    .slice(0, 4) as ChoiceOption[],
            };
        }

        if (
            parsed.kind === 'action_confirmation' &&
            typeof parsed.summary_key === 'string'
        ) {
            return {
                version: 2,
                kind: 'action_confirmation',
                summary_key: parsed.summary_key,
                summary_params: record(parsed.summary_params) as Record<
                    string,
                    string | number | boolean
                >,
                business_rows: Array.isArray(parsed.business_rows)
                    ? (parsed.business_rows.map(record) as BusinessRow[])
                    : [],
            };
        }

        return parsed.version === 1
            ? legacyActionPreview(part, parsed, hasTranslation)
            : null;
    } catch {
        return null;
    }
}
