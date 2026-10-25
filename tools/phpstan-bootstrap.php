<?php

declare(strict_types=1);

// Analysis only: discover procedural symbols in includes/ through scanDirectories.
// Never require init.php/db.php/config.php: they start sessions and connect to data.
require_once dirname(__DIR__) . '/vendor/autoload.php';
