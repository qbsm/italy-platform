# Core — Ядро PHP-платформы

Пространство имён: `App\` (PSR-4 autoload из `composer.json` указывает на `core/`).

---

## Точка входа

```php
// index.php
$config = ConfigManager::build(__DIR__);
(new Application($config))->run();
```

`ConfigManager` формирует массив конфигурации, который далее прокидывается во все модули.

---

## Жизненный цикл запроса

```
index.php
  │
  ├─ ConfigManager::build()          загрузка .env, формирование конфига
  │
  └─ Application::run()
       │
       ├─ 1. Redirector                проверка redirects.json → 301/302 и exit
       │
       ├─ 2. UrlParser                 вычисление относительного пути, разбиение на сегменты
       │
       ├─ 3. loadGlobal()              чтение data/json/global.json (языки, общие данные)
       │
       ├─ 4. LanguageDetector          определение языка из первого сегмента URL
       │
       ├─ 5. Router                    page_id = первый оставшийся сегмент (или "index")
       │
       ├─ 6. PageDataLoader            data/json/{lang}/pages/{page_id}.json → если нет → 404
       │
       ├─ 7. SeoDataLoader             data/json/{lang}/seo/{page_id}.json
       │
       ├─ 8. TemplateEngine            инициализация Twig + расширения
       │
       ├─ 9. SeoProcessor              рендер Twig-плейсхолдеров в SEO-полях
       │
       ├─ 10. TemplateResolver         pages/{page_id}.twig → фолбэк на pages/404.twig
       │
       ├─ 11. TemplateDataBuilder      сборка единого массива данных для шаблона
       │
       └─ 12. Twig::render()           вывод HTML и exit
```

---

## Модули

### Bootstrap

| Файл | Класс | Описание |
|---|---|---|
| `Bootstrap/Application.php` | `Application` | Оркестратор запроса. Принимает конфиг, последовательно вызывает все этапы (см. схему выше). Обрабатывает `Throwable` через `ErrorHandler`. |
| `Bootstrap/ServiceContainer.php` | `ServiceContainer` | Минимальный DI-контейнер: `register(name, service)` / `get(name)`. Бросает `RuntimeException` при обращении к незарегистрированному сервису. |

### Config

| Файл | Класс | Описание |
|---|---|---|
| `Config/ConfigManager.php` | `ConfigManager` | Статический класс. `build($projectRoot)` — загружает `.env`, определяет `base_url` по `$_SERVER`, формирует и возвращает массив конфигурации (пути, URL-ы, настройки Twig, язык). Также предоставляет геттеры: `getPaths()`, `getUrls()`, `getSettings()`, `getJsonPaths()`. |
| `Config/EnvironmentDetector.php` | `EnvironmentDetector` | Определяет `baseUrl` из `$_SERVER` и окружение (`prod`/`dev`) через переменную `APP_ENV`. |
| `Config/PathResolver.php` | `PathResolver` | Вспомогательные методы: `resolvePaths(baseDir, baseUrl)` и `resolveJsonPath(template, lang)` — подстановка `{lang}` в шаблон пути. |

#### Структура конфига (`ConfigManager::build()`)

```php
[
    'base_dir'  => '/abs/path',
    'base_url'  => 'https://example.com/',
    'dirs'      => [
        'data'      => '.../data',
        'json'      => '.../data/json',
        'images'    => '.../data/img',
        'templates' => '.../templates',
        'assets'    => '.../assets',
        'cache'     => '.../cache',
        'src'       => '.../core',
        'config'    => '.../core/Config',
        'vendor'    => '.../vendor',
        'logs'      => '.../logs',
    ],
    'urls'      => [
        'base'   => 'https://example.com/',
        'assets' => '.../assets/',
        'data'   => '.../data/',
        'images' => '.../data/img/',
        'css'    => '.../assets/css/build/',
        'js'     => '.../assets/js/build/',
    ],
    'json'      => [
        'global'    => '.../data/json/global.json',
        'pages_dir' => '.../data/json/{lang}/pages',
    ],
    'settings'  => [
        'default_lang'    => 'ru',          // APP_DEFAULT_LANG
        'available_langs' => ['ru'],
        'twig_cache'      => false,
        'twig_cache_dir'  => '.../cache',
        'debug'           => true,          // APP_DEBUG
    ],
]
```

#### Поддерживаемые переменные `.env`

| Переменная | По умолчанию | Описание |
|---|---|---|
| `APP_DEFAULT_LANG` | `ru` | Язык по умолчанию |
| `APP_DEBUG` | `1` | Режим отладки (вывод стека ошибок) |
| `APP_ENV` | `prod` | Окружение (`prod` / `dev`) |

### Routing

| Файл | Класс | Описание |
|---|---|---|
| `Routing/UrlParser.php` | `UrlParser` | `parseRequestPath($requestPath, $basePath)` — убирает basePath из начала пути. `extractSegments($relativePath)` — разбивает путь на массив непустых сегментов. |
| `Routing/Router.php` | `Router` | `resolveRoute($segments)` — первый сегмент = `page_id`, остальные = `routeParams`. Если сегментов нет, `page_id = 'index'`. |
| `Routing/Redirector.php` | `Redirector` | `getRedirectTarget($requestPath, $baseUrl)` — ищет совпадение в `redirects.json`. Возвращает `['to' => url, 'status' => 301|302]` или `null`. Поддерживает абсолютные URL в `to`. |
| `Routing/redirects.json` | — | JSON-массив правил: `[{"from": "/old", "to": "/new", "status": 301}, ...]`. По умолчанию пустой `[]`. |

#### Маршрутизация URL → page_id

```
/                          → page_id: "index"
/services                  → page_id: "services"
/en/services               → lang: "en", page_id: "services"
/portfolio/my-project      → page_id: "portfolio", routeParams: ["my-project"]
```

### Language

| Файл | Класс | Описание |
|---|---|---|
| `Language/LanguageDetector.php` | `LanguageDetector` | `detect($segments, $supportedLanguages, $defaultCode)` — если первый сегмент совпадает с кодом поддерживаемого языка, извлекает его и устанавливает `is_lang_in_url = true`. Возвращает `['lang_code', 'current_lang', 'is_lang_in_url', 'segments']`. |
| `Language/LanguageService.php` | `LanguageService` | Хранит текущий и дефолтный языковые коды. Геттеры: `getCurrentLanguage()`, `getDefaultLanguage()`. |

Список поддерживаемых языков берётся из `global.json` (поле `lang`).

### Data

| Файл | Класс | Описание |
|---|---|---|
| `Data/DataLoader.php` | `DataLoader` | `loadJson($path, $baseUrl)` — читает JSON-файл, парсит, прогоняет через `JsonProcessor::processJsonPaths()` для подстановки абсолютных URL. Возвращает массив или `null`. |
| `Data/PageDataLoader.php` | `PageDataLoader` | `loadPage($pageJsonDir, $pageId, $baseUrl)` — делегирует в `DataLoader::loadJson()`, формируя путь `{pageJsonDir}/{pageId}.json`. |

#### Структура данных страницы

Файл `data/json/{lang}/pages/{page_id}.json`:

```json
{
    "name": "Название страницы",
    "title": "Заголовок",
    "sections": [
        { "type": "promo", "title": "...", "image": "/data/img/..." },
        { "type": "content", "blocks": [...] }
    ]
}
```

Относительные пути вида `/data/...` и `data/...` автоматически преобразуются в абсолютные URL через `JsonProcessor`.

### SEO

| Файл | Класс | Описание |
|---|---|---|
| `SEO/SeoDataLoader.php` | `SeoDataLoader` | `loadSeoData($langCode, $pageId, $config, $baseUrl)` — загружает `data/json/{lang}/seo/{pageId}.json`. Пути обрабатываются через `JsonProcessor`. |
| `SEO/SeoProcessor.php` | `SeoProcessor` | `processSeoTemplates($seoData, $context, $twig)` — рекурсивно рендерит Twig-шаблоны в строковых значениях SEO-данных. Доступный контекст: `pageData`, `global`, `config`, `currentLang`, `lang_code`, `route_params`, `base_url`, `is_lang_in_url`. |

#### Пример SEO-файла с плейсхолдерами

```json
{
    "title": "{{ pageData.name }} — Ландшафтное бюро",
    "description": "{{ pageData.sections[0].text | default('') }}",
    "og_image": "/data/img/og/default.webp"
}
```

### Template

| Файл | Класс | Описание |
|---|---|---|
| `Template/TemplateEngine.php` | `TemplateEngine` | Инициализирует Twig: `FilesystemLoader` из `templates/`, подключает `DebugExtension` (при `debug`), `AssetExtension`, `UrlExtension`, `StringLoaderExtension`. Устанавливает глобальную переменную `base_url`. Предоставляет `render()`, `templateExists()`, `getTwig()`. |
| `Template/TemplateResolver.php` | `TemplateResolver` | `resolve($twig, $pageId)` — проверяет `pages/{pageId}.twig`. Если не существует, фолбэк на `pages/404.twig`. Возвращает `['path', 'page_id']`. |
| `Template/TemplateDataBuilder.php` | `TemplateDataBuilder` | `build($config, $global, $pageData, $seo, $ctx, $extras)` — собирает единый массив для `twig->render()`. |

#### Переменные, доступные в Twig-шаблонах

| Переменная | Тип | Описание |
|---|---|---|
| `config` | `array` | Полный конфиг приложения |
| `global` | `array` | Данные из `global.json` |
| `currentLang` | `array` | Информация о текущем языке |
| `lang_code` | `string` | Код языка (`ru`, `en` и пр.) |
| `page_id` | `string` | Идентификатор текущей страницы |
| `route_params` | `array` | Дополнительные сегменты URL после `page_id` |
| `base_url` | `string` | Базовый URL сайта с завершающим `/` |
| `is_lang_in_url` | `bool` | Присутствует ли код языка в URL |
| `pageData` | `array\|null` | Данные страницы из JSON |
| `pageSeoData` | `array\|null` | SEO-данные (после обработки плейсхолдеров) |
| `pageTitle` | `string` | Заголовок: из SEO `title`, иначе из `pageData.title` |
| `sections` | `array` | Массив секций страницы (shortcut для `pageData.sections`) |

### Twig-расширения

| Файл | Класс | Twig-функции | Описание |
|---|---|---|---|
| `Twig/AssetExtension.php` | `AssetExtension` | `assetUrl(name, type?, safe?)`, `asset_manifest()`, `css_manifest()` | Работа с манифестами ассетов (`asset-manifest.json`, `css-manifest.json`). `assetUrl('main.js')` → URL с хешем. Параметр `safe=true` подавляет исключения, возвращая `null`. |
| `Twig/UrlExtension.php` | `UrlExtension` | `url(path?)` | Строит абсолютный URL от `base_url`. Пропускает абсолютные ссылки, якоря, `tel:`, `mailto:`. Для путей без расширения автоматически добавляет trailing slash. `url(null)` → `#`. |

#### Примеры использования в шаблонах

```twig
{# Подключение ассетов #}
<script src="{{ assetUrl('main.js') }}"></script>
<link rel="stylesheet" href="{{ assetUrl('main.css', 'css') }}">

{# Генерация ссылок #}
<a href="{{ url('services') }}">Услуги</a>         {# → https://example.com/services/ #}
<a href="{{ url('catalog.pdf') }}">Каталог</a>      {# → https://example.com/catalog.pdf #}
```

### Logging

| Файл | Класс | Описание |
|---|---|---|
| `Logging/Logger.php` | `Logger` | `writeToLog($filename, $message, $logDirectory)` — записывает строку с таймстампом в указанный файл. Создаёт директорию при необходимости. `initLogsSystem($logDir)` — создаёт папку логов и `.htaccess` для защиты от веб-доступа. |
| `Logging/RequestLogger.php` | `RequestLogger` | Обёртка над `Logger`. `logError($message, $context)` → пишет в `errors.txt`. `logInfo($message)` → пишет в `app.txt`. При создании автоматически вызывает `initLogsSystem()`. |

Логи сохраняются в директорию `logs/` (по конфигу `dirs.logs`).

### Utils

| Файл | Класс | Описание |
|---|---|---|
| `Utils/JsonProcessor.php` | `JsonProcessor` | `processJsonPaths(&$data, $baseUrl)` — рекурсивно обходит массив/объект и преобразует относительные пути `/data/...` и `data/...` в абсолютные URL. Пропускает уже абсолютные (`http://`, `https://`). Также: `loadJsonFile($path)`, `validateJsonData($data, $path)`. |
| `Utils/ErrorHandler.php` | `ErrorHandler` | `handleThrowable($e)` — логирует исключение, отдаёт HTTP 500. В режиме `debug` выводит стек-трейс. `isDebug()` — проверяет `APP_DEBUG` из env и конфига. Конфиг устанавливается через `setConfig()`. |

---

## Структура файлов данных

```
data/
├── json/
│   ├── global.json                 Глобальные данные (языки, навигация и пр.)
│   └── {lang}/
│       ├── pages/
│       │   ├── index.json          Данные главной страницы
│       │   ├── services.json       Данные страницы услуг
│       │   └── ...
│       └── seo/
│           ├── index.json          SEO-данные главной
│           ├── services.json       SEO-данные услуг
│           └── ...
└── img/                            Изображения
```

---

## Зависимости

- **PHP 8.1+** (`str_starts_with`, `str_ends_with`, типизация свойств)
- **Twig 3.x** — шаблонизатор (+ `StringLoaderExtension`, `DebugExtension`)
- **Composer** — автозагрузка PSR-4
