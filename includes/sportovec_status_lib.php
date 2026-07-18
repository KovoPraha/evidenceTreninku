<?php
declare(strict_types=1);

function sportovecStatusCompute(array $sportovec): array
{
    $manual = (int)($sportovec['stav_manualni'] ?? 0) === 1;
    $stored = (string)($sportovec['stav_clenstvi'] ?? 'cekajici');
    $kisActive = (int)($sportovec['kis_aktivni'] ?? 0) === 1;
    $paid = (int)($sportovec['kis_platebne_aktivni'] ?? 0) === 1;
    $debt = (float)($sportovec['kis_neuhrazeno'] ?? 0);
    $groups = trim((string)($sportovec['skupiny'] ?? '')) !== '';

    if ($manual) {
        return sportovecStatusMeta($stored, 'Ruční stav má přednost', true);
    }
    if ($stored === 'archiv') {
        return sportovecStatusMeta('archiv', 'Archivovaný člen', false);
    }
    if ($kisActive && ($paid || $debt <= 0.009) && $groups) {
        return sportovecStatusMeta('aktivni', 'Aktivní v KIS, bez dluhu a má skupinu', false);
    }
    if (!$kisActive && !$paid && $debt <= 0.009) {
        return sportovecStatusMeta('cekajici', 'Čeká na potvrzení aktivity nebo platby', false);
    }
    if (!$kisActive && $debt > 0.009) {
        return sportovecStatusMeta('neaktivni', 'Není aktivní v KIS a má otevřené platby', false);
    }
    return sportovecStatusMeta('cekajici', 'Neúplný stav, zkontrolujte KIS a skupiny', false);
}

function sportovecStatusMeta(string $status, string $reason, bool $manual): array
{
    $map = [
        'aktivni' => ['label' => 'Aktivní', 'class' => 'bg-success'],
        'cekajici' => ['label' => 'Čekající', 'class' => 'bg-warning text-dark'],
        'neaktivni' => ['label' => 'Neaktivní', 'class' => 'bg-secondary'],
        'archiv' => ['label' => 'Archiv', 'class' => 'bg-dark'],
    ];
    $meta = $map[$status] ?? $map['cekajici'];
    $meta['status'] = $status;
    $meta['reason'] = $reason;
    $meta['manual'] = $manual;
    return $meta;
}

function sportovecStatusUpdate(PDO $pdo, int $sportovecId, array $sportovec): void
{
    if ((int)($sportovec['stav_manualni'] ?? 0) === 1) {
        return;
    }
    $meta = sportovecStatusCompute($sportovec);
    $stmt = $pdo->prepare("
        UPDATE sportovci
        SET stav_clenstvi = :stav,
            stav_duvod = :duvod,
            stav_aktualizovan = CURRENT_TIMESTAMP
        WHERE id = :id
          AND COALESCE(stav_manualni, 0) = 0
        LIMIT 1
    ");
    $stmt->execute([
        ':stav' => $meta['status'],
        ':duvod' => $meta['reason'],
        ':id' => $sportovecId,
    ]);
}

function sportovecStatusBadge(array $sportovec): string
{
    $meta = sportovecStatusCompute($sportovec);
    $title = htmlspecialchars($meta['reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label = htmlspecialchars($meta['label'] . ($meta['manual'] ? ' ručně' : ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<span class="badge ' . $meta['class'] . '" title="' . $title . '">' . $label . '</span>';
}
