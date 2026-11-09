<?php

declare(strict_types=1);

require_once __DIR__ . '/sports_activity.php';

/** Only the three displayed totals; never a partial sports-actual.v1 snapshot. */
final class SportsActivityOverviewRead
{
    public static function totals(PDO $pdo, int $personId, string $from, string $to): array
    {
        sportsActivityDate($from);
        sportsActivityDate($to);
        if ($from > $to || $personId < 1) {
            throw new InvalidArgumentException('Neplatné období nebo osoba.');
        }
        $training = [];
        if (is_file(__DIR__ . '/training_closure.php')) {
            require_once __DIR__ . '/training_closure.php';
        }
        if (function_exists('trainingClosedActivitiesForPerson') && memberChargeReadTableExists($pdo, 'training_closures')) {
            // The canonical reader resolves current revisions, voids and actual participants.
            foreach (trainingClosedActivitiesForPerson($pdo, $personId, $from, $to) as $row) {
                $training[] = ['date' => $row['actual_date'], 'activity_id' => (int) $row['training_id'],
                    'duration_minutes' => $row['duration_minutes'] === null ? null : (int) $row['duration_minutes']];
            }
        }
        // Preserve the canonical sum order and PHP integer/null conversion, even for legacy durations.
        usort($training, static fn ($a, $b) => [$a['date'], $a['activity_id']] <=> [$b['date'], $b['activity_id']]);
        $races = 0;
        if (memberChargeReadTableExists($pdo, 'sports_event_participation')) {
            foreach (sportsEventCurrent($pdo, $personId) as $row) {
                if (!in_array($row['stage'], ['attended', 'started'], true) || $row['actual_date'] < $from || $row['actual_date'] > $to) {
                    continue;
                }
                // Keep canonical corrupt-snapshot rejection; result details are not displayed here.
                json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                if ($row['stage'] === 'started') {
                    $races++;
                }
            }
        }
        return ['training_count' => count($training), 'training_minutes' => array_sum(array_column($training, 'duration_minutes')),
            'race_count' => $races];
    }
}
