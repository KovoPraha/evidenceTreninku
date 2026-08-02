<?php
require_once __DIR__ . '/session_security.php';

app_session_start();

// Sdílené funkce (roleAtLeast, audit log)
require_once __DIR__ . '/funkce.php';

// Připojení k databázi (db.php je v kořenovém adresáři)
require_once __DIR__ . '/../db.php';