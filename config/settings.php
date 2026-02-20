<?php

$projectRoot = dirname(__DIR__);
$debugValue = (string) (getenv('APP_DEBUG') ?: '1');
$isDebug = in_array(strtolower($debugValue), ['1', 'true', 'yes', 'on'], true);

return [
    'project_root' => $projectRoot,
    'default_lang' => (string) (getenv('APP_DEFAULT_LANG') ?: 'ru'),
    'route_map' => [
        'restaurants' => 'restaurants-list',
    ],
    'twig' => [
        'cache' => false,
        'debug' => $isDebug,
        'auto_reload' => true,
    ],
    'paths' => [
        'templates' => $projectRoot . '/templates',
        'json_base' => $projectRoot . '/data/json',
        'json_global' => $projectRoot . '/data/json/global.json',
        'json_pages_dir' => $projectRoot . '/data/json/{lang}/pages',
        'redirects' => $projectRoot . '/config/redirects.json',
        'cache' => $projectRoot . '/cache',
        'logs' => $projectRoot . '/logs',
    ],
];
