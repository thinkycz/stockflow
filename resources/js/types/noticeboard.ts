export type NoticeboardCard = {
    id: number;
    body_html: string;
    label: 'information' | 'important' | 'task' | 'event';
    color: 'yellow' | 'pink' | 'blue' | 'green' | 'purple';
    size: 'small' | 'medium' | 'large';
    image_url: string | null;
    expires_on: string | null;
    display_on: string | null;
    confirmation: { worker_name: string; confirmed_at: string } | null;
    version: number;
};

export type NoticeboardConfirmation = {
    id: number;
    date: string;
    worker: { id: number; name: string };
    items: Array<
        Pick<
            NoticeboardCard,
            'id' | 'body_html' | 'label' | 'color' | 'size' | 'image_url'
        >
    >;
};
