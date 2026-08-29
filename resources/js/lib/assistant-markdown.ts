import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';

const markdown = new MarkdownIt({
    breaks: true,
    html: false,
    linkify: true,
    typographer: false,
});

markdown.renderer.rules.image = () => '';
markdown.renderer.rules.link_open = (
    tokens,
    index,
    options,
    _environment,
    renderer,
) => {
    const token = tokens[index];

    if (token === undefined) {
        return '';
    }

    token.attrSet('target', '_blank');
    token.attrSet('rel', 'noopener noreferrer');

    return renderer.renderToken(tokens, index, options);
};

export function renderAssistantMarkdown(source: string): string {
    return DOMPurify.sanitize(markdown.render(source), {
        ALLOWED_TAGS: [
            'a',
            'blockquote',
            'br',
            'code',
            'em',
            'h1',
            'h2',
            'h3',
            'h4',
            'hr',
            'li',
            'ol',
            'p',
            'pre',
            's',
            'strong',
            'table',
            'tbody',
            'td',
            'th',
            'thead',
            'tr',
            'ul',
        ],
        ALLOWED_ATTR: ['href', 'rel', 'target'],
        ALLOW_DATA_ATTR: false,
        FORBID_TAGS: ['iframe', 'img', 'script', 'style'],
    });
}
