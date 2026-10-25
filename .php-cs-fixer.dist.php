<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true])
    ->setUsingCache(false)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__)->exclude('vendor'));
