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
        'paths' => ['/api/send', '/api/widget-rescue'],
        'max_requests' => 10,
        'window_seconds' => 60,
    ],
    // Токен формы выдаётся браузеру по запросу, а не вместе с HTML: страница, скачанная
    // роботом, не даёт возможности отправить заявку. min_age — сколько секунд между выдачей
    // токена и отправкой считаем нижней границей для живого человека.
    'form_token' => [
        'min_age' => 3,
        'max_age' => 7200,
        'secret_file' => $cacheDir . '/form-token-secret',
    ],
    'cors' => [
        'allowed_origins' => [], // например ['https://example.com'] или ['*'] для любого
        'allowed_methods' => ['GET', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],
        'allow_credentials' => false,
    ],
    'mail' => [
        // Пусто — флага в .env нет, поведение прежнее: канал включён, если задан адрес.
        'enable' => (string) (getenv('MAIL_ENABLE') ?: ''),
        'dsn' => (string) (getenv('MAIL_DSN') ?: 'sendmail://default'),
        'to' => (string) (getenv('MAIL_TO') ?: ''),
        'from' => (string) (getenv('MAIL_FROM') ?: 'noreply@localhost'),
        'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: ''),
        'subject_prefix' => (string) (getenv('MAIL_SUBJECT_PREFIX') ?: ''),
    ],
    'calltouch' => [
        'enable' => (bool) filter_var((string) (getenv('CALLTOUCH_ENABLE') ?: ''), FILTER_VALIDATE_BOOL),
        'route_key' => (string) (getenv('CALLTOUCH_ROUTE_KEY') ?: ''),
        'token' => (string) (getenv('CALLTOUCH_TOKEN') ?: ''),
        'site_id' => (string) (getenv('CALLTOUCH_SITE_ID') ?: ''),
        'timeout' => (int) (getenv('CALLTOUCH_TIMEOUT') ?: 10),
    ],
    'telegram' => [
        'enable' => (bool) filter_var((string) (getenv('TELEGRAM_ENABLE') ?: ''), FILTER_VALIDATE_BOOL),
        'bot_token' => (string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''),
        'chat_id' => (string) (getenv('TELEGRAM_CHAT_ID') ?: ''),
        'timeout' => (int) (getenv('TELEGRAM_TIMEOUT') ?: 10),
    ],
    'google_sheets' => [
        'enable' => (bool) filter_var((string) (getenv('SHEETS_ENABLE') ?: ''), FILTER_VALIDATE_BOOL),
        'spreadsheet_id' => (string) (getenv('SHEETS_SPREADSHEET_ID') ?: ''),
        'sheet_name' => (string) (getenv('SHEETS_SHEET_NAME') ?: 'Заявки'),
        'credentials_path' => (string) (getenv('SHEETS_CREDENTIALS_PATH') ?: 'config/secrets/google-service-account.json'),
        'timeout' => (int) (getenv('SHEETS_TIMEOUT') ?: 10),
    ],
    // Интернет-эквайринг Альфа-Банка (платформа RBS, REST). Включается флагом PAYMENT_ENABLED,
    // учётные данные магазина (логин с суффиксом -api и пароль либо token) — только из окружения.
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
    // Telegram-алерты об ошибках оплаты/отправки почты в группу «Итали» (Обновление сайта italy&co.)
    'alerts' => [
        'token' => (string) (getenv('TELEGRAM_ALERT_BOT_TOKEN') ?: ''),
        'chat_id' => (string) (getenv('TELEGRAM_ALERT_CHAT_ID') ?: ''),
        'proxy' => (string) (getenv('TELEGRAM_ALERT_PROXY') ?: ''),
        'site' => (string) (getenv('TELEGRAM_ALERT_SITE') ?: 'italycommunity.ru'),
    ],
    // Резервный сбор заявок (rescue-канал): дублирует заявку в наш сервис, который сначала её
    // сохраняет, а потом раздаёт по каналам с повторами — упавший канал не теряет лид.
    // Подтверждение отправителя — по домену: заявку шлёт бэкенд, значит с адреса, на который
    // домен резолвится. Секрета в .env нет; ключ нужен только хостингам вне нашего периметра.
    'rescue' => [
        'enable' => filter_var((string) (getenv('RESCUE_ENABLE') ?: 'false'), FILTER_VALIDATE_BOOLEAN),
        'url' => (string) (getenv('RESCUE_URL') ?: 'https://api.ismart.pro/v1/rescue'),
        'site' => (string) (getenv('RESCUE_SITE') ?: ''),
        'key' => (string) (getenv('RESCUE_KEY') ?: ''),
        'timeout' => (int) (getenv('RESCUE_TIMEOUT') ?: 10),
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
