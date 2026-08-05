<?php
declare(strict_types=1);

/** Narrow allowlist for club e-mail templates. */
function safeEmailHtml(string $html): string
{
    $html = strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><a>');
    return (string)preg_replace_callback(
        '~<\s*(/)?\s*([a-z0-9]+)([^>]*)>~i',
        static function (array $match): string {
            $closing = ($match[1] ?? '') === '/';
            $tag = strtolower((string)$match[2]);
            $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'ul', 'ol', 'li', 'a'];
            if (!in_array($tag, $allowed, true)) return '';
            if ($closing) return $tag === 'br' ? '' : "</{$tag}>";
            if ($tag !== 'a') return "<{$tag}>";

            $attributes = (string)($match[3] ?? '');
            if (preg_match('~href\s*=\s*(["\'])(.*?)\1~is', $attributes, $hrefMatch) !== 1) return '<a>';
            $href = trim(html_entity_decode((string)$hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $compact = (string)preg_replace('/[\x00-\x20]+/', '', $href);
            if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $schemeMatch) === 1
                && !in_array(strtolower((string)$schemeMatch[1]), ['http', 'https', 'mailto'], true)
            ) return '<a>';
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" rel="noopener noreferrer">';
        },
        $html
    );
}
