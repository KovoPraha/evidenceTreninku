<?php

declare(strict_types=1);

/** Permanent post-cutover barrier: legacy imports cannot refill the cleared history. */
function memberEvidenceAssertLegacyWriteAllowed(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $query = $pdo->query($mysql
        ? "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_evidence_cutover_runs'"
        : "SELECT 1 FROM sqlite_master WHERE type='table' AND name='member_evidence_cutover_runs'");
    if ($query->fetchColumn() && $pdo->query('SELECT 1 FROM member_evidence_cutover_runs LIMIT 1')->fetchColumn()) {
        throw new RuntimeException('Přechod evidence je dokončen. Import historie, sezonní obnova a zakládání sezon jsou ukončeny.');
    }
}
