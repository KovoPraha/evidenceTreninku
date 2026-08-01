<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/kis_parity_contract.php';

const KIS_PARITY_MAX_INPUT_BYTES = 5 * 1024 * 1024;

/** @return never */
function kisParityCliUsage(string $message, bool $json): void
{
    if ($json) {
        echo json_encode(
            ['status' => 'usage_error', 'error' => $message],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } else {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Usage: php bin/kis-parity-dry-run.php --input <local.json> [--json]' . PHP_EOL);
    }
    exit(64);
}

/** @return never */
function kisParityCliValidation(string $message, bool $json): void
{
    if ($json) {
        echo json_encode(
            ['status' => 'blocked', 'error_type' => 'validation', 'error' => $message],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } else {
        fwrite(STDERR, 'Validation blocker: ' . $message . PHP_EOL);
    }
    exit(2);
}

$inputPath = null;
$args = array_slice($argv, 1);
$jsonOutput = in_array('--json', $args, true);
for ($i = 0, $count = count($args); $i < $count; $i++) {
    $arg = $args[$i];
    if ($arg === '--json') {
        $jsonOutput = true;
        continue;
    }
    if ($arg === '--help') {
        echo 'Usage: php bin/kis-parity-dry-run.php --input <local.json> [--json]' . PHP_EOL;
        exit(0);
    }
    if ($arg === '--input') {
        if ($inputPath !== null || !isset($args[$i + 1]) || str_starts_with($args[$i + 1], '--')) {
            kisParityCliUsage('The --input option requires exactly one path.', $jsonOutput);
        }
        $inputPath = $args[++$i];
        continue;
    }
    if (str_starts_with($arg, '--input=')) {
        if ($inputPath !== null) {
            kisParityCliUsage('The --input option may be used only once.', $jsonOutput);
        }
        $inputPath = substr($arg, strlen('--input='));
        if ($inputPath === '') {
            kisParityCliUsage('The --input option requires exactly one path.', $jsonOutput);
        }
        continue;
    }
    kisParityCliUsage('Unknown command-line option.', $jsonOutput);
}

if ($inputPath === null) {
    kisParityCliUsage('The --input option is required.', $jsonOutput);
}
if (str_contains($inputPath, "\0") || str_contains($inputPath, '://')) {
    kisParityCliUsage('Only local regular input files are allowed.', $jsonOutput);
}
if (is_link($inputPath)) {
    kisParityCliUsage('Symbolic-link input paths are not allowed.', $jsonOutput);
}

$resolvedPath = realpath($inputPath);
if ($resolvedPath === false || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
    kisParityCliUsage('The input file is not a readable local regular file.', $jsonOutput);
}
$size = filesize($resolvedPath);
if ($size === false || $size > KIS_PARITY_MAX_INPUT_BYTES) {
    kisParityCliUsage('The input file exceeds the 5 MiB limit.', $jsonOutput);
}

$contents = file_get_contents($resolvedPath);
if ($contents === false) {
    kisParityCliUsage('The input file could not be read.', $jsonOutput);
}
try {
    $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new InvalidArgumentException('The JSON root must be an object.');
    }
    $report = kisParityContractEvaluate($decoded);
} catch (JsonException|InvalidArgumentException $e) {
    kisParityCliValidation($e->getMessage(), $jsonOutput);
}

if ($jsonOutput) {
    echo json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} else {
    echo 'KIS parity v1: ' . strtoupper((string)$report['status']) . PHP_EOL;
    echo 'Run: ' . $report['run_ref'] . PHP_EOL;
    echo 'Rows: ' . $report['summary']['total_rows'] . PHP_EOL;
    foreach ($report['summary']['counts'] as $category => $count) {
        echo '  ' . $category . ': ' . $count . PHP_EOL;
    }
    echo 'Blocker rows: ' . $report['summary']['blocker_rows'] . PHP_EOL;
    echo 'Missing in run: ' . $report['summary']['missing_in_run']['count']
        . ' (informational only; archive never)' . PHP_EOL;
    echo 'Dry run only: no apply action exists.' . PHP_EOL;
}

exit($report['status'] === 'valid' ? 0 : 2);
