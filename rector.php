<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/contao',
        __DIR__.'/tests',
    ])
    ->withPhpSets();
