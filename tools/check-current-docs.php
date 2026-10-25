<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$errors = [];
$state = file($root . '/docs/CURRENT_STATE.md');
if ($state === false || count($state) > 80) {
    $errors[] = 'CURRENT_STATE.md must contain at most 80 lines.';
}
$paths = ['CLAUDE.md', 'docs/CURRENT_STATE.md', 'docs/HANDOFF_CURRENT.md', 'docs/CHANGELOG-PROVOZ.md', 'docs/CLAUDE_HISTORY.md'];
$links = 0;
foreach ($paths as $path) {
    $source = (string) file_get_contents($root . '/' . $path);
    // Ignore examples in fenced code; check actual local Markdown destinations.
    $source = preg_replace('/```.*?```/s', '', $source);
    preg_match_all('/(?<!!)\[[^\]]+\]\(([^\s)]+)(?:\s+"[^"]*")?\)/', $source, $matches);
    foreach ($matches[1] as $link) {
        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|#|/)~i', $link)) {
            continue;
        }
        $target = rawurldecode(explode('#', $link, 2)[0]);
        if ($target === '') {
            continue;
        }
        $links++;
        if (!file_exists(dirname($root . '/' . $path) . '/' . $target)) {
            $errors[] = $path . ': missing ' . $target;
        } elseif (str_contains($link, '#') && str_ends_with($target, '.md')) {
            $fragment = rawurldecode(explode('#', $link, 2)[1]);
            $linked = (string) file_get_contents(dirname($root . '/' . $path) . '/' . $target);
            preg_match_all('/^#{1,6}\s+(.+)$/m', $linked, $headings);
            $found = false;
            foreach ($headings[1] as $heading) {
                $slug = str_replace(' ', '-', preg_replace('/[^\p{L}\p{N}_\- ]/u', '', trim($heading)));
                if (preg_match('~^' . preg_quote($fragment, '~') . '$~iu', $slug)) {
                    $found = true;
                }
            }
            if (!$found) {
                $errors[] = $path . ': missing heading ' . $link;
            }
        }
    }
}
$archive = str_replace("\r\n", "\n", (string) file_get_contents($root . '/docs/CHANGELOG-PROVOZ.md'));
$restored = [];
preg_match_all('/<!-- archive (.+?) -->\n(.*?)\n<!-- \/archive -->/s', $archive, $blocks, PREG_SET_ORDER);
foreach ($blocks as $block) {
    $metadata = json_decode($block[1], true, 512, JSON_THROW_ON_ERROR);
    $restored[$metadata['source']][$metadata['part']] = $block[2];
    if (!hash_equals($metadata['sha256'], hash('sha256', str_replace("\r\n", "\n", $block[2])))) {
        $errors[] = 'Historical content changed: ' . $metadata['source'] . ' part ' . $metadata['part'];
    }
}

$expected = [
    'docs/CURRENT_STATE.md' => 'fc5f22095271f371b3a68912e0494defd65fb717a7c7f706bc126ce7114fe569',
    'docs/HANDOFF_CURRENT.md' => '530de048fde1065389eaf2f6c8c4727b8868f1101e102351abe602fa61234d35',
 ];
foreach ($expected as $source => $digest) {
    $parts = $restored[$source] ?? [];
    ksort($parts);
    if (!hash_equals($digest, hash('sha256', implode('', $parts)))) {
        $errors[] = 'Incomplete historical source: ' . $source;
    }
}

foreach ($errors as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}
echo sprintf("CURRENT_STATE: %d lines; local links: %d; archived blocks: %d; errors: %d\n", count($state), $links, count($blocks), count($errors));
exit($errors === [] ? 0 : 1);
