<?php

$appRoot = dirname(__DIR__, 2);
$autoloadPaths = [
    $appRoot . '/vendor/autoload.php',
    dirname($appRoot, 2) . '/autoload.php'
];

foreach ($autoloadPaths as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        return;
    }
}

throw new \RuntimeException('Composer autoloader not found. Install the app or host dependencies before running PHPUnit.');