<?php

namespace App\Support;

use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Renders Markdown to HTML while neutralising raw HTML and javascript: links.
 *
 * Laravel's `Str::of(...)->markdown()` defaults pass raw HTML straight through
 * and allow `javascript:` href values, which means an operator (or a prompt-
 * injected AI suggestion) could embed `<script>` or `<a href="javascript:...">`
 * inside an incident or schedule message and have it executed in every visitor's
 * browser. We escape HTML and strip unsafe links here, so the only formatting
 * that survives is the markdown subset itself.
 */
final class SafeMarkdown
{
    public static function convert(?string $markdown): string
    {
        if ($markdown === null || $markdown === '') {
            return '';
        }

        static $converter = null;
        if ($converter === null) {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
        }

        return (string) $converter->convert($markdown);
    }
}
