<?php
declare(strict_types=1);

/** @return array{sha:string,run_id:string,deployed_at:string,uat_approved:bool} */
function deploymentReleaseInfo(?string $applicationRoot = null): array
{
    $empty = ['sha' => '', 'run_id' => '', 'deployed_at' => '', 'uat_approved' => false];
    $applicationRoot ??= dirname(__DIR__);
    $path = rtrim($applicationRoot, '/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'deployment.json';
    if (!is_file($path) || !is_readable($path)) return $empty;
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) > 8192) return $empty;
    try {
        $value = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $empty;
    }
    if (!is_array($value)) return $empty;
    $sha = strtolower(trim((string)($value['sha'] ?? '')));
    $runId = trim((string)($value['run_id'] ?? ''));
    $deployedAt = trim((string)($value['deployed_at'] ?? ''));
    if (preg_match('/^[a-f0-9]{40}$/D', $sha) !== 1
        || preg_match('/^[0-9]+$/D', $runId) !== 1
        || DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $deployedAt) === false
    ) {
        return $empty;
    }
    return [
        'sha' => $sha,
        'run_id' => $runId,
        'deployed_at' => $deployedAt,
        'uat_approved' => ($value['uat_approved'] ?? false) === true,
    ];
}
