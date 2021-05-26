<?php

ini_set('display_errors', 'stderr');

$basePath = getcwd() . '/../';
if (!is_file($autoload_file = $basePath . '/vendor/autoload.php')) {
    fwrite(STDERR, "Composer autoload file was not found. Did you install the project's dependencies?" . PHP_EOL);
    exit(10);
}

require_once $autoload_file;

return $basePath;
