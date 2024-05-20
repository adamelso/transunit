<?php

require_once 'vendor/autoload.php'; // Assuming you have installed nikic/php-parser via Composer

$args = $argv;
array_shift($args);

\Transunit\Transunit::run(...$args);
