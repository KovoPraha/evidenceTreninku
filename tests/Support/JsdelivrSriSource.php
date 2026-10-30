<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpParser\Node\Scalar\String_;

/** Fold only adjacent literal strings; never execute application source. @return list<int> */
function jsdelivrSriMissingLines(string $source): array
{
    $tokens = token_get_all($source);
    $normalized = '';
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            $normalized .= is_array($token) ? $token[1] : $token;
            continue;
        }
        $value = String_::fromString($token[1])->value;
        $last = $i;
        while (true) {
            $next = $last + 1;
            while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if (($tokens[$next] ?? null) !== '.') {
                break;
            }
            $next++;
            while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if (!is_array($tokens[$next] ?? null) || $tokens[$next][0] !== T_CONSTANT_ENCAPSED_STRING) {
                break;
            }
            $value .= String_::fromString($tokens[$next][1])->value;
            $last = $next;
        }
        $normalized .= $last === $i ? $token[1] : var_export($value, true);
        $i = $last;
    }
    preg_match_all('~<(?:script|link)\b[^>]*cdn\.jsdelivr\.net[^>]*>~is', $normalized, $matches, PREG_OFFSET_CAPTURE);
    $missing = [];
    foreach ($matches[0] as [$tag, $offset]) {
        if (!str_contains($tag, 'integrity="sha384-') || !str_contains($tag, 'crossorigin="anonymous"')) {
            $missing[] = substr_count(substr($normalized, 0, $offset), "\n") + 1;
        }
    }
    return $missing;
}
