<?php
declare(strict_types=1);

// Executed only from data/.kis-deploy on the production host. Never print
// credentials or include a database dump in the workflow log.
require __DIR__ . '/../../kis.kovopraha.cz/config.php';

if (!defined('DB_NAME') || !is_string(DB_NAME)
    || preg_match('/^kovoprahacz(?:0[1-9]|10)?$/D', DB_NAME) !== 1
) {
    exit(2);
}

echo DB_NAME;
