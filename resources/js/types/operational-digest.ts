export type OperationalDigestStatus = 'pending' | 'queued' | 'sent' | 'failed';

export type OperationalDigestSummary = {
    id: number;
    date: string;
    status: OperationalDigestStatus;
    activity_count: number;
    attempt_count: number;
    last_error: string | null;
    queued_at: string | null;
    sent_at: string | null;
};

export type OperationalDigestDetail = {
    title: string;
    body: string;
    actor: string | null;
    url: string;
};

export type OperationalDigestSection = {
    key: string;
    name: string;
    is_warehouse: boolean;
    activity_count: number;
    paragraphs: string[];
    details: OperationalDigestDetail[];
};

export type OperationalDigestSnapshot = {
    date: string;
    title: string;
    intro: string;
    period_start: string;
    period_end: string;
    activity_count: number;
    sections: OperationalDigestSection[];
};

export type OperationalDigest = OperationalDigestSummary & {
    snapshot: OperationalDigestSnapshot;
};
