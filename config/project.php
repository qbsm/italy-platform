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

    // Эквайринг Альфа-Банка — специфика деплоймента: ядро о нём не знает, настройки
    // доезжают в settings через passthrough project.php.
    'payment' => (static function () use ($appEnv, $projectRoot): array {
        $payEnv = strtolower((string) (getenv('PAYMENT_ENV') ?: ($appEnv === 'production' ? 'prod' : 'test')));
        $isProdGate = $payEnv === 'prod';
        $baseUrl = rtrim((string) (getenv('PAYMENT_RETURN_BASE') ?: (getenv('APP_BASE_URL') ?: 'https://italycommunity.ru')), '/');
        return [
            'enabled' => in_array(strtolower((string) (getenv('PAYMENT_ENABLED') ?: '0')), ['1', 'true', 'yes', 'on'], true),
            'env' => $payEnv,
            'gateway' => 'alfa',
            'api_url' => $isProdGate
                ? 'https://pay.alfabank.ru/payment/rest'
                : 'https://alfa.rbsuat.com/payment/rest',
            'username' => (string) (getenv('PAYMENT_USERNAME') ?: ''),
            'password' => (string) (getenv('PAYMENT_PASSWORD') ?: ''),
            'token' => (string) (getenv('PAYMENT_TOKEN') ?: ''),
            // Общий ключ контрольной суммы callback-уведомлений (ЛК банка → Callback-уведомления).
            // Пока пуст, /pay/callback не принимает ничего: оплата подтверждается только возвратом.
            'callback_token' => (string) (getenv('PAYMENT_CALLBACK_TOKEN') ?: ''),
            'base_url' => $baseUrl,
            // ISO 4217; в шлюзе Альфы рубль — 810
            'currency' => (string) (getenv('PAYMENT_CURRENCY') ?: '810'),
            'description' => (string) (getenv('PAYMENT_DESCRIPTION') ?: 'Покупка билета — Экосистема итали'),
            'item_name' => (string) (getenv('PAYMENT_ITEM_NAME') ?: 'Электронный билет'),
            'session_timeout' => (int) (getenv('PAYMENT_SESSION_TIMEOUT') ?: 1200),
            // Корзина чека (54-ФЗ) уходит в orderBundle. Включать только когда у магазина
            // включена фискализация на стороне банка — иначе банк отклонит регистрацию.
            'fiscal' => [
                'enabled' => in_array(strtolower((string) (getenv('PAYMENT_FISCAL_ENABLED') ?: '0')), ['1', 'true', 'yes', 'on'], true),
                'tax_type' => (int) (getenv('PAYMENT_FISCAL_TAX_TYPE') ?: 0), // 0 = без НДС
                'measure' => (string) (getenv('PAYMENT_FISCAL_MEASURE') ?: 'шт'),
            ],
            'orders_dir' => $projectRoot . '/var/orders',
            'timeout' => 30,
        ];
    })(),

];
