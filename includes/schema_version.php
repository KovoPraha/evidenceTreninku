<?php

/**
 * Cílová verze databázového schématu.
 *
 * Je oddělená od samotné migrace, aby ji mohl bezpečně přečíst také
 * produkční deploy checker bez spuštění změn v databázi.
 */
const SCHEMA_VERSION = '2.20.2';
