<?php
declare(strict_types=1);

/** All outstanding past plans of this trainer, matching the planner's default scope. */
function trainingPlannerTrainerBacklog(PDO $pdo, int $trainerId, string $today): array
{
    if ($trainerId < 1) throw new InvalidArgumentException('Trainer is required.');
    $statement = $pdo->prepare(
        "SELECT COUNT(*) AS total, MIN(datum) AS oldest_date FROM planovane_treninky "
        . "WHERE trener_id=? AND stav='planovany' AND datum<?"
    );
    $statement->execute([$trainerId, $today]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return ['count' => (int)$row['total'], 'oldest_date' => $row['oldest_date'] ?: null];
}

/** The caller supplies the already authorized and filtered overdue plans. */
function trainingPlannerOldestPendingDate(array $plans): ?string
{
    $dates = array_column($plans, 'datum');
    return $dates === [] ? null : min($dates);
}

/** Plain text only; callers must HTML-escape the result. Decode before truncating. */
function trainingPlannerAnnouncementExcerpt(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr(strtok($text, "\n") ?: '', 0, 160, 'UTF-8');
}
