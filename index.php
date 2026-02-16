<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Bootstrap\Application;
use App\Config\ConfigManager;

$config = ConfigManager::build(__DIR__);
(new Application($config))->run();
exit;
