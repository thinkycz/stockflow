<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class NoticeboardContentSanitizer
{
    /**
     * Sanitize rich text and derive its searchable plain-text form.
     *
     * @return array{html: string, text: string}
     */
    public function sanitize(string $html): array
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('span', ['data-text-size'])
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(20_000);

        $sanitized = (new HtmlSanitizer($config))->sanitize($html);
        $sanitized = (string) \preg_replace_callback(
            '/<span(?:\\s+data-text-size="([^"]*)")?>/',
            static fn(array $matches): string => \in_array($matches[1] ?? '', ['small', 'normal', 'large'], true)
                ? '<span data-text-size="' . $matches[1] . '">'
                : '<span>',
            $sanitized,
        );
        $text = \mb_trim((string) \preg_replace(
            '/\\s+/u',
            ' ',
            \html_entity_decode(\strip_tags($sanitized), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
        ));

        if ($text === '') {
            throw new InvalidArgumentException('Noticeboard content must contain visible text.');
        }

        return ['html' => $sanitized, 'text' => $text];
    }
}
