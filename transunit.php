<?php

require_once 'vendor/autoload.php'; // Assuming you have installed nikic/php-parser via Composer

$args = $argv;
array_shift($args);
$args[0] = getcwd().'/'.$args[0];

\Transunit\Transunit::run(...$args);
