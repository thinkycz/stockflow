<?php

declare(strict_types=1);

use App\Domain\Noticeboard\NoticeboardContentSanitizer;

\test('noticeboard content sanitizer preserves supported formatting and removes executable markup', function (): void {
    $content = (new NoticeboardContentSanitizer())->sanitize(
        '<p>Hello <strong>team</strong><script>alert(1)</script>'
        . '<span data-text-size="large" style="position:fixed">today</span>'
        . '<a href="javascript:alert(1)">bad</a></p>',
    );

    \expect($content['html'])
        ->toContain('<strong>team</strong>')
        ->toContain('data-text-size="large"')
        ->not->toContain('<script')
        ->not->toContain('position:fixed')
        ->not->toContain('javascript:')
        ->and($content['text'])->toBe('Hello teamtodaybad');
});

\test('noticeboard content sanitizer rejects content without visible text', function (): void {
    \expect(fn(): array => (new NoticeboardContentSanitizer())->sanitize('<p><br></p>'))
        ->toThrow(InvalidArgumentException::class);
});
