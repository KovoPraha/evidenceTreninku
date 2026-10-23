<?php
declare(strict_types=1);

/** Keep failed input, including intentionally empty selections and the original concurrency version. */
function trainingClosureFormState(?array $failedPost, ?array $current, array $people, array $coaches): array
{
    $form = [
        'version' => (int)($current['version'] ?? 0),
        'actual_started_at' => substr(str_replace(' ', 'T', (string)($current['actual_started_at'] ?? '')), 0, 16),
        'actual_ended_at' => substr(str_replace(' ', 'T', (string)($current['actual_ended_at'] ?? '')), 0, 16),
        'participant_ids' => $people, 'coach_ids' => $coaches,
        'record_source' => 'coach', 'guest_reason' => '', 'reason' => '', 'confirm_action' => false,
    ];
    if ($failedPost === null) return $form;
    foreach (['actual_started_at','actual_ended_at','guest_reason','reason'] as $name) {
        $form[$name] = is_scalar($failedPost[$name] ?? null) ? (string)$failedPost[$name] : '';
    }
    foreach (['participant_ids','coach_ids'] as $name) {
        $form[$name] = array_values(array_unique(array_map('intval', is_array($failedPost[$name] ?? null) ? $failedPost[$name] : [])));
    }
    $form['version'] = (int)($failedPost['version'] ?? 0);
    $form['record_source'] = ($failedPost['record_source'] ?? '') === 'paper' ? 'paper' : 'coach';
    $form['confirm_action'] = ($failedPost['confirm_action'] ?? '') === '1';
    return $form;
}
