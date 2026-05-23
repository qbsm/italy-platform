<?php

// Проектная конфигурация italycommunity.ru.

return [
    // slug в URL => page_id (файл data/json/{lang}/pages/{page_id}.json)
    'route_map' => [
        'restaurants' => 'restaurants-list',
    ],

    // Параметризация коллекций.
    'collections' => [
        'restaurants' => [
            'nav_slug'     => 'restaurants',
            'list_page_id' => 'restaurants-list',
            'template'     => 'pages/restaurant.twig',
            'item_key'     => 'restaurant',
            'data_dir'     => 'restaurants',
            'slugs_source' => 'items',
            'slugs_page'   => 'restaurants',
            'og_type'      => 'website',
            'extras_key'   => 'restaurant',
        ],
    ],

    // page_id страниц для sitemap.xml (без 404)
    'sitemap_pages' => [
        'index',
        'restaurants-list',
        'contacts',
        'policy',
        'agree',
    ],

    // Внешние интеграции (флаги включения)
    'integrations' => [],
];
