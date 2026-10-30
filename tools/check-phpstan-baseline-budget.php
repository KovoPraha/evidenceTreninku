<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Parse only the reviewed generated format. Unknown formats fail closed. @return array<string,int> */
function baselineBudgetEntries(string $source): array
{
    $source = str_replace("\r\n", "\n", $source);
    if (trim($source) === "parameters:\n\tignoreErrors: []") {
        return [];
    }
    $header = "parameters:\n\tignoreErrors:\n";
    if (!str_starts_with($source, $header)) {
        throw new RuntimeException('Unrecognized baseline header.');
    }
    $remaining = substr($source, strlen($header));
    $pattern = "~\\G\\s*\\t\\t-\\n\\t\\t\\tmessage: ('[^\\n]+')\\n"
        . "\\t\\t\\tidentifier: ([A-Za-z0-9_.]+)\\n\\t\\t\\tcount: ([1-9][0-9]*)\\n"
        . "\\t\\t\\tpath: ([A-Za-z0-9_./-]+)\\n~";
    $offset = 0;
    $entries = [];
    while (preg_match($pattern, $remaining, $match, 0, $offset) === 1) {
        $key = $match[4] . "\t" . $match[2] . "\t" . $match[1];
        if (isset($entries[$key])) {
            throw new RuntimeException('Duplicate baseline finding.');
        }
        $entries[$key] = (int) $match[3];
        $offset += strlen($match[0]);
    }
    if (trim(substr($remaining, $offset)) !== '') {
        throw new RuntimeException('Unrecognized baseline entry; budget cannot be verified.');
    }
    return $entries;
}

/** @param array<string,int> $before @param array<string,int> $after */
function baselineBudgetAssert(array $before, array $after): void
{
    foreach ($after as $key => $count) {
        if ($count > ($before[$key] ?? 0)) {
            throw new RuntimeException('New or increased PHPStan suppression: ' . explode("\t", $key)[0]);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $base = $argv[1] ?? 'origin/main';
    $root = dirname(__DIR__);
    $process = proc_open(
        ['git', 'show', $base . ':phpstan-baseline.neon'],
        [1 => ['pipe', 'w'], 2 => STDERR],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot inspect baseline reference.');
    }
    $old = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Baseline reference unavailable.');
    }
    $before = baselineBudgetEntries((string) $old);
    $after = baselineBudgetEntries((string) file_get_contents($root . '/phpstan-baseline.neon'));
    baselineBudgetAssert($before, $after);
    $domains = [];
    foreach ($after as $key => $count) {
        $path = explode("\t", $key)[0];
        $domain = str_contains($path, '/') ? explode('/', $path)[0] : 'entrypoints';
        $domains[$domain] = ($domains[$domain] ?? 0) + $count;
    }
    ksort($domains);
    echo json_encode(['status' => 'pass', 'base' => $base,
        'before' => ['entries' => count($before), 'findings' => array_sum($before)],
        'after' => ['entries' => count($after), 'findings' => array_sum($after)],
        'findings_by_directory' => $domains], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
}
