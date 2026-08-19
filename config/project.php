<?php

declare(strict_types=1);

/**
 * Проектная конфигурация italycommunity: маршруты коллекций, сами коллекции, состав sitemap.
 * До синка ядра значения жили прямо в settings.php — ядро платформы читает их отсюда.
 */
return [
    'route_map' => [
        'restaurants' => 'restaurants-list',
        'events' => 'events-list',
    ],
    'collections' => [
        'restaurants' => [
            'data_dir' => 'restaurants',          // data/json/{lang}/restaurants/{slug}.json
            'item_key' => 'restaurant',           // ключ внутри entity (валидируется на existence)
            'nav_slug' => 'restaurants',          // префикс URL и breadcrumb
            'list_page_id' => 'restaurants-list', // pages/restaurants-list.json
            'slugs_source' => 'items',
            'template' => 'pages/restaurant.twig',
            'extras_key' => 'restaurant',         // ключ в template ($restaurant)
            'og_type' => 'website',
            'entity_url_pattern' => '/restaurants/{slug}',
            'site_name' => 'Экосистема итали',
            'prod_base_url' => 'https://italycommunity.ru',
            'fallback_og_image' => '/data/img/seo/og.jpg?v=4',
            'list_title' => 'Рестораны',
        ],
        'events' => [
            'data_dir' => 'events',               // data/json/{lang}/events/{slug}.json
            'item_key' => 'event',                // ключ внутри entity (валидируется на existence)
            'nav_slug' => 'events',               // префикс URL и breadcrumb; список slug'ов — pages/events.json
            'list_page_id' => 'events-list',      // pages/events-list.json
            'slugs_source' => 'items',
            'template' => 'pages/event.twig',
            'extras_key' => 'event',              // ключ в template ($event)
            'og_type' => 'event',
            'entity_url_pattern' => '/events/{slug}',
            'site_name' => 'Экосистема итали',
            'prod_base_url' => 'https://italycommunity.ru',
            'fallback_og_image' => '/data/img/seo/og.jpg?v=4',
            'list_title' => 'Афиша',
            // Афиша временно скрыта из выдачи: роутинг живой, из карты сайта исключена
            'sitemap' => false,
        ],
    ],
    'sitemap_pages' => [
        'index',
        'about',
        'contacts',
        'policy',
        'agree',
        'restaurants-list',
    ],
];
