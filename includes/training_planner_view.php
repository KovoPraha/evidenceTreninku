<?php
declare(strict_types=1);

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
