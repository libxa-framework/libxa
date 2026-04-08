<?php

require 'vendor/autoload.php';

use Libxa\Config\Config;

$config = new Config(__DIR__ . '/src/config');
echo "Config class loaded successfully: " . Config::class . "\n";
