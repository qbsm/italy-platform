<?php

$projectRoot = dirname(__DIR__);

// APP_ENV: production | development — разделение окружений (кэш Twig, уровень логов)
$appEnv = (string) (getenv('APP_ENV') ?: 'development');
$isProduction = $appEnv === 'production';

$debugValue = (string) (getenv('APP_DEBUG') ?: ($isProduction ? '0' : '1'));
$isDebug = in_array(strtolower($debugValue), ['1', 'true', 'yes', 'on'], true);

$cacheDir = $projectRoot . '/cache';

// Единый источник языков — data/json/global.json → lang (code, title, direction)
$jsonGlobalPath = $projectRoot . '/data/json/global.json';
$available_langs = ['ru', 'en'];
$default_lang = (string) (getenv('APP_DEFAULT_LANG') ?: 'ru');
if (is_readable($jsonGlobalPath)) {
    $global = json_decode((string) file_get_contents($jsonGlobalPath), true);
    if (isset($global['lang']) && is_array($global['lang'])) {
        $available_langs = array_values(array_filter(array_map(
            static function ($item) {
                return is_array($item) && isset($item['code']) ? (string) $item['code'] : null;
            },
            $global['lang']
        )));
        if ($available_langs === []) {
            $available_langs = ['ru', 'en'];
        }
        if (!getenv('APP_DEFAULT_LANG') && isset($global['lang'][0]['code'])) {
            $default_lang = (string) $global['lang'][0]['code'];
        }
    }
}
$envDefaultLang = getenv('APP_DEFAULT_LANG');
if ($envDefaultLang !== false && $envDefaultLang !== '') {
    $default_lang = (string) $envDefaultLang;
}

// Ключи и ширины для адаптивных изображений (picture.twig, tools/build) — единый источник
$imageSizesPath = __DIR__ . '/image-sizes.json';
$image_sizes = [
    'keys' => ['800', '1600', 'raw'],
    'widths' => ['800' => 800, '1600' => 1600, 'raw' => null],
];
if (is_readable($imageSizesPath)) {
    $imageSizesData = json_decode((string) file_get_contents($imageSizesPath), true);
    if (is_array($imageSizesData)) {
        if (isset($imageSizesData['keys']) && is_array($imageSizesData['keys'])) {
            $image_sizes['keys'] = array_values($imageSizesData['keys']);
        }
        if (isset($imageSizesData['widths']) && is_array($imageSizesData['widths'])) {
            $image_sizes['widths'] = $imageSizesData['widths'];
        }
    }
}

return [
    'project_root' => $projectRoot,
    'env' => $appEnv,
    'debug' => $isDebug,
    'default_lang' => $default_lang,
    'available_langs' => $available_langs,
    'yandex_metric_id' => (int) (getenv('YANDEX_METRIC_ID') ?: 0),
    // slug в URL => page_id (файл в data/json/{lang}/pages/{page_id}.json)
    'route_map' => [
        'restaurants' => 'restaurants-list',
        'events' => 'events-list',
    ],
    // Конфигурация коллекций — generic loader (loadEntity/loadEntitySlugs)
    // и per-collection SEO (seo_builder реализует SeoBuilderInterface)
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
            'fallback_og_image' => '/data/img/seo/og.jpg?v=3',
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
            'fallback_og_image' => '/data/img/seo/og.jpg?v=3',
            'list_title' => 'Афиша',
        ],
    ],
    // page_id страниц для sitemap.xml (без 404). Задаётся под проект.
    'sitemap_pages' => [
        'index',
        'about',
        'contacts',
        'policy',
        'agree',
        'restaurants-list',
        'events-list',
    ],
    // Rate limiting для POST /api/send (по IP, файловое хранилище в cache/rate_limit)
    'rate_limit_api_send' => [
        'max_requests' => 10,
        'window_seconds' => 60,
    ],
    'cors' => [
        'allowed_origins' => [], // например ['https://example.com'] или ['*'] для любого
        'allowed_methods' => ['GET', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],
        'allow_credentials' => false,
    ],
    'mail' => [
        'dsn' => (string) (getenv('MAILER_DSN') ?: 'sendmail://default'),
        'to' => (string) (getenv('MAIL_TO') ?: ''),
        'from' => (string) (getenv('MAIL_FROM') ?: 'noreply@localhost'),
        'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: ''),
        'subject_prefix' => (string) (getenv('MAIL_SUBJECT_PREFIX') ?: ''),
    ],
    // Эквайринг Русский Стандарт (RSB ecomm2) — перенос механизма оплаты билетов с tasteproject.
    // Включается флагом PAYMENT_ENABLED. Сертификат/ключ — вне docroot (var/rsb, не в git).
    'payment' => (static function () use ($appEnv, $projectRoot): array {
        $payEnv = strtolower((string) (getenv('PAYMENT_ENV') ?: ($appEnv === 'production' ? 'prod' : 'test')));
        $isProdGate = $payEnv === 'prod';
        return [
            'enabled' => in_array(strtolower((string) (getenv('PAYMENT_ENABLED') ?: '0')), ['1', 'true', 'yes', 'on'], true),
            'env' => $payEnv,
            'gateway' => 'rsb',
            'merchant_url' => $isProdGate
                ? 'https://securepay.rsb.ru:9443/ecomm2/MerchantHandler'
                : 'https://testsecurepay.rsb.ru:9443/ecomm2/MerchantHandler',
            'client_url' => $isProdGate
                ? 'https://securepay.rsb.ru/ecomm2/ClientHandler'
                : 'https://testsecurepay.rsb.ru/ecomm2/ClientHandler',
            'cert' => $projectRoot . '/var/rsb/9295933758.pem',
            'key' => $projectRoot . '/var/rsb/9295933758.key',
            'ca' => $projectRoot . '/var/rsb/chain-ecomm-ca-root-ca.crt',
            'currency' => '643',
            'description' => (string) (getenv('PAYMENT_DESCRIPTION') ?: 'Покупка билета — Экосистема итали'),
            // ТСП «Чек онлайн» (ОФД). ВНИМАНИЕ: значение унаследовано от tasteproject
            // (ООО «Сервис и Развитие»). Для оператора ТЭД нужен собственный Group/мерчант.
            'ofd_group' => (string) (getenv('PAYMENT_OFD_GROUP') ?: ($isProdGate ? '5b3e92af-4a17-4ce6-a171-f069a7005484' : 'testgroup')),
            'ofd_tax_id' => (int) (getenv('PAYMENT_OFD_TAX_ID') ?: 4), // 4 = Без налога
            'orders_dir' => $projectRoot . '/var/orders',
            'timeout' => 30,
        ];
    })(),
    // Telegram-алерты об ошибках оплаты/отправки почты в группу «Итали» (Обновление сайта italy&co.)
    'alerts' => [
        'token' => (string) (getenv('TG_ALERT_BOT_TOKEN') ?: ''),
        'chat_id' => (string) (getenv('TG_ALERT_CHAT_ID') ?: ''),
        'proxy' => (string) (getenv('TG_ALERT_PROXY') ?: ''),
        'site' => (string) (getenv('TG_ALERT_SITE') ?: 'italycommunity.ru'),
    ],
    'errors' => require __DIR__ . '/errors.php',
    'twig' => [
        'cache' => $isProduction ? $cacheDir . '/twig' : false,
        'debug' => $isDebug,
        'auto_reload' => !$isProduction,
    ],
    'paths' => [
        'templates' => $projectRoot . '/templates',
        'json_base' => $projectRoot . '/data/json',
        'json_global' => $projectRoot . '/data/json/global.json',
        'json_pages_dir' => $projectRoot . '/data/json/{lang}/pages',
        'redirects' => $projectRoot . '/config/redirects.json',
        'cache' => $cacheDir,
        'logs' => $projectRoot . '/logs',
    ],
    'image_sizes' => $image_sizes,
];
