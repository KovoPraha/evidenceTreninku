<?php

/**
 * Cílová verze databázového schématu.
 *
 * Je oddělená od samotné migrace, aby ji mohl bezpečně přečíst také
 * produkční deploy checker bez spuštění změn v databázi.
 */
/**
 * Posledni verze obsluhovana puvodni monolitickou auto-migraci.
 *
 * Tato hodnota je zamerne nemenne zakladni schema (baseline). Budouci zmeny
 * patri do cislovanych souboru v migrations/ a do evidence_schema_migrations.
 */
const LEGACY_SCHEMA_VERSION = '2.20.2';

/**
 * Zpetne kompatibilni alias pro starsi casti aplikace.
 * Nezvysovat kvuli novym cislovanym migracim.
 */
const SCHEMA_VERSION = LEGACY_SCHEMA_VERSION;

/**
 * @return 'missing'|'pending'|'current'|'ahead'|'invalid'
 */
function evidence_legacy_schema_state(string $version): string
{
    $version = trim($version);
    if ($version === '') {
        return 'missing';
    }

    if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
        return 'invalid';
    }

    $comparison = version_compare($version, LEGACY_SCHEMA_VERSION);
    if ($comparison < 0) {
        return 'pending';
    }
    if ($comparison > 0) {
        return 'ahead';
    }

    return 'current';
}
