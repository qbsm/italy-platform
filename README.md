# iSmart Platform — Многоязычная веб-платформа

## Обзор проекта

Современное многоязычное веб-приложение, построенное на основе модульной архитектуры с использованием PHP, Twig и JSON. Платформа ориентирована на производительность, SEO-оптимизацию и удобство управления контентом через JSON-файлы.

### Ключевые особенности

- **Многоязычность**: Поддержка нескольких языков (сейчас настроены ru, en)
- **Модульная архитектура**: Компонентный подход — страницы, секции и компоненты
- **JSON-управляемый контент**: Весь контент хранится в структурированных JSON-файлах
- **Производительность**: Webpack 5 с code splitting и хешированием, PostCSS с минификацией
- **SEO-оптимизация**: Schema.org микроразметка, Open Graph, динамические мета-теги
- **Адаптивность**: Корректная работа в любых директориях и поддиректориях

## Технологический стек

### Backend

- **PHP 8.5+** — серверная логика и маршрутизация
- **Twig 3.x** — шаблонизатор с наследованием и компонентами
- **Composer** — управление PHP-зависимостями (PSR-4 autoload)

### Frontend

- **Vanilla JavaScript** — нативный JS с модульной структурой
- **PostCSS** — обработка CSS (nested, custom properties, autoprefixer, cssnano)
- **CSS Grid/Flexbox** — современные методы раскладки

### Сборка и оптимизация

- **Webpack 5** — сборка JavaScript с code splitting
- **PostCSS** — обработка CSS с плагинами
- **Хеширование файлов** — `[contenthash:8]` для кеширования на уровне CDN и браузера

### UI библиотеки

- **Swiper** — слайдеры и карусели
- **GLightbox** — галереи изображений и видео
- **jQuery** — используется в формах и части компонентов
- **Inputmask** — маски ввода для форм
- **Animate.css** — CSS-анимации

### Аналитика и маркетинг

- **Яндекс.Метрика** — веб-аналитика
- **Roistat** — сквозная аналитика и CRM-интеграция
- **Top.Mail.Ru** — дополнительная аналитика

Счётчики подключаются в `templates/components/analytics.twig`.

## Архитектура проекта

### Основные принципы

1. **Компонентная архитектура**: Разделение на страницы, секции и компоненты
2. **JSON-управляемый контент**: Данные отделены от логики представления
3. **Модульная система сборки**: Автоматическое разделение кода и оптимизация
4. **Многоязычная структура**: Единая архитектура для всех языков
5. **SEO-первый подход**: Полная оптимизация для поисковых систем

### Ядро приложения (`src/` + Slim 4)

Приложение построено на Slim 4 с PSR-совместимой архитектурой: middleware + action + сервисы.

Жизненный цикл запроса:

```text
public/index.php
  ├─ Dotenv загрузка .env
  ├─ DI-контейнер (config/container.php)
  ├─ Middleware stack (config/middleware.php)
  │   ├─ SecurityHeadersMiddleware (X-Content-Type-Options, HSTS и др.)
  │   ├─ LanguageMiddleware
  │   ├─ RedirectMiddleware (config/redirects.json)
  │   └─ TrailingSlashMiddleware
  ├─ Routes (config/routes.php)
  └─ PageAction
      ├─ DataLoaderService
      ├─ SeoService
      ├─ TemplateDataBuilder
      └─ Twig render
```

Основные модули:

- `src/Action` — HTTP-обработчики (сейчас `PageAction`)
- `src/Middleware` — PSR-15 middleware (security headers, язык, редиректы, trailing slash)
- `src/Service` — бизнес-логика загрузки данных/SEO/сборки шаблонных данных
- `src/Twig` — Twig-расширения (`AssetExtension`, `DataExtension`, `UrlExtension`)
- `src/Support` — вспомогательные утилиты (`JsonProcessor`, `BaseUrlResolver`)
- `config/` — конфигурация контейнера, маршрутов, middleware и runtime-настроек (окружение и настройки: [docs/architecture/config.md](docs/architecture/config.md))

### Структура данных

```
data/json/
├── global.json                    # Глобальные настройки (языки, навигация, контакты, формы)
└── ru/                            # Данные для русского языка
    ├── pages/                     # JSON-файлы страниц
    │   ├── index.json             # Главная страница
    │   ├── contacts.json          # Контакты
    │   ├── policy.json            # Политика конфиденциальности
    │   ├── agree.json             # Пользовательское соглашение
    │   └── 404.json               # Страница ошибки 404
    └── seo/                       # SEO-данные
        ├── index.json             # SEO главной страницы
        ├── contacts.json          # SEO контактов
        ├── policy.json            # SEO политики
        ├── agree.json             # SEO соглашения
        └── 404.json               # SEO страницы 404
```

### Модульная структура шаблонов

Подробнее: [docs/architecture/structure.md](docs/architecture/structure.md).

```
templates/
├── base.twig                    # Базовый шаблон (мета-теги, canonical, скрипты)
├── pages/                         # Единый data-driven шаблон
│   └── page.twig                 # Рендеринг по sections из JSON страницы
├── sections/                      # Секции (header, footer, intro, …)
│   ├── header.twig                # Шапка сайта
│   ├── footer.twig                # Подвал
│   ├── intro.twig                 # Вводная секция
│   ├── content.twig               # Контентная секция
│   ├── contacts.twig              # Секция контактов
│   └── cookie-panel.twig          # Панель cookie-согласия
└── components/                    # Мелкие компоненты
    ├── form-callback.twig         # Форма обратной связи
    ├── slider.twig                # Слайдер
    ├── accordion.twig             # Аккордеон
    ├── heading.twig               # Заголовок
    ├── cover.twig                 # Обложка
    ├── picture.twig               # Изображение
    ├── button.twig                # Кнопка
    ├── features-list.twig         # Список преимуществ
    ├── spoiler.twig               # Спойлер
    ├── mini-table.twig            # Мини-таблица
    ├── blockquote.twig            # Цитата
    ├── custom-list.twig           # Кастомный список
    ├── numbered-list.twig         # Нумерованный список
    ├── burger-icon.twig           # Иконка бургер-меню
    ├── burger-menu.twig           # Мобильное меню
    ├── analytics.twig             # Счётчики аналитики + Schema.org
    ├── favicons.twig              # Фавиконки
    ├── scripts.twig               # Подключение скриптов
    └── styles.twig                # Подключение стилей
```

### Система сборки ресурсов

#### JavaScript (Webpack 5)

- **Автоматическое разделение кода**:
  - `runtime.[hash].js` — Webpack runtime
  - `vendors.[hash].js` — общие библиотеки
  - `ui-vendors.[hash].js` — UI библиотеки (Swiper, GLightbox)
  - `util-vendors.[hash].js` — утилиты (jQuery, Inputmask)
  - `main.[hash].js` — код приложения
- **Хеширование файлов**: `[contenthash:8]` для эффективного кеширования
- **Минификация в production**: Автоматическая оптимизация для продакшена
- **Манифест**: `asset-manifest.json` для маппинга хешированных файлов

#### CSS (PostCSS)

- **Модульная структура**: Отдельные файлы для base, компонентов, секций и страниц
- **Современные возможности**:
  - Custom Properties (CSS-переменные)
  - Nested-селекторы
  - Custom Media Queries
  - Автопрефиксы для кроссбраузерности
  - Минификация через cssnano в production
- **Хеширование**: `main.[hash].css` + `css-manifest.json`

## Основные возможности

### 1. Многоязычность

- **Настроенные языки**: Русский (основной), английский
- **URL-структура**: Без префикса для основного языка (`/contacts/`), с префиксом для остальных (`/en/contacts/`)
- **Переключатель языков**: В шапке сайта
- **Расширяемость**: Для добавления языка — создать папку `data/json/{lang}/` и добавить язык в `global.json`

### 2. Управление контентом

- **JSON-основа**: Весь контент управляется через JSON-файлы
- **Модульные секции**: Страницы состоят из набора секций, каждая с собственными данными
- **Глобальные данные**: Навигация, контакты, формы, cookie-панель — в `global.json`

### 3. SEO и производительность

- **Schema.org микроразметка**: Organization, SiteNavigationElement
- **Мета-теги**: Динамическое формирование title, description, Open Graph
- **SEO с Twig-шаблонами**: Поддержка плейсхолдеров в SEO-данных
- **robots.txt**: Настроенный файл для поисковых роботов

### 4. Формы и аналитика

- **Форма обратной связи**: С валидацией на клиенте, маской телефона и политикой согласия
- **Множественная аналитика**: Яндекс.Метрика, Roistat, Top.Mail.Ru
- **Cookie-панель**: Глобальная панель из `base.twig`, тексты из `global.json`

## Команды разработки

### Установка зависимостей

```bash
# PHP зависимости
composer install

# Node.js зависимости
npm install
```

### Сборка проекта

```bash
# Разработка (отслеживание CSS и JS)
npm run dev

# Тестовая сборка
npm run build:dev

# Продакшн сборка (с оптимизацией)
npm run build
```

### Отдельная сборка

```bash
# Только CSS
npm run build:css
npm run build:css:prod    # production

# Только JS
npm run build:js
npm run build:js:prod     # production

# Очистка устаревших ассетов
npm run clean:assets
```

### Создание компонентов

```bash
# Создание нового компонента
npm run create-component component-name

# Создание новой секции
npm run create-section section-name

# Создание новой страницы
npm run create-page page-name
```

### Служебные команды

```bash
# Генерация фавиконок
npm run generate-favicons

# Проверка валидности всех JSON
npm run validate-json

# Тестирование правил .htaccess
bash tools/ops/test-htaccess.sh

# Исправление прав доступа
npm run fix-permissions

# Проверка актуальности версий зависимостей (в т.ч. jQuery, Swiper, GLightbox, Inputmask)
npm outdated
```

### Стандарт структуры проекта (best practice)

В проекте используется стандартизированная структура:

- `docs/` — документация проекта
  - `docs/guides` — практические инструкции
  - `docs/architecture` — техническая и архитектурная документация
- `tools/` — утилиты автоматизации
  - `tools/build` — скрипты сборки и постобработки
  - `tools/scaffold` — генераторы страниц/секций/компонентов
  - `tools/ops` — эксплуатационные и проверочные скрипты

## Структура файлов (эталонная)

Полное описание: [docs/architecture/structure.md](docs/architecture/structure.md).

```
project/
├── assets/                        # Исходные ресурсы
│   ├── css/
│   │   ├── base/                  # Базовые стили (variables, typography, grid, fonts...)
│   │   ├── components/            # Стили компонентов
│   │   ├── sections/              # Стили секций
│   │   ├── pages/                 # Стили страниц
│   │   ├── main.css               # Основной файл импорта
│   │   └── build/                 # Собранные CSS (main.[hash].css, css-manifest.json)
│   ├── js/
│   │   ├── base/                  # Базовый JavaScript (expose-vendors)
│   │   ├── components/            # JS компонентов
│   │   ├── sections/              # JS секций
│   │   ├── pages/                 # JS страниц
│   │   ├── vendor.js              # Подключение библиотек
│   │   ├── main.js                # Точка входа
│   │   └── build/                 # Собранные JS (runtime, vendors, main + manifest)
│   ├── fonts/                     # Веб-шрифты (.woff2)
│   └── img/                       # Иконки для ассетов
├── src/                           # Ядро PHP-приложения (Slim 4)
│   ├── Action/                    # Action-классы
│   ├── Middleware/                # PSR-15 middleware
│   ├── Service/                   # Бизнес-сервисы
│   ├── Twig/                      # Twig extensions
│   └── Support/                   # Утилиты/хелперы
├── config/                        # settings/container/middleware/routes/redirects
├── public/                        # Публичная точка входа (DocumentRoot)
│   ├── index.php
│   └── .htaccess
├── data/                          # Данные и медиа
│   ├── json/                      # JSON-данные (см. выше)
│   └── img/                       # Изображения (ui, favicons, seo, контент)
├── docs/                          # Документация проекта
│   ├── guides/                    # Инструкции (страницы, SEO и т.д.)
│   └── architecture/              # Техническая документация
├── tools/                         # Проектные утилиты
│   ├── build/                     # Сборка и постобработка ассетов
│   ├── scaffold/                  # Генераторы страниц/секций/компонентов
│   └── ops/                       # Операционные скрипты (валидации, проверки, права)
├── templates/                     # Twig-шаблоны (см. выше)
├── .env.example                   # Пример переменных окружения
├── robots.txt                     # Правила для поисковых роботов
├── package.json                   # Node.js зависимости и скрипты
├── composer.json                  # PHP зависимости (Slim 4, Twig, PHP-DI)
├── webpack.config.js              # Конфигурация Webpack 5
└── postcss.config.js              # Конфигурация PostCSS
```

## Переменные окружения

| Переменная         | По умолчанию | Описание                                                        |
| ------------------ | ------------ | --------------------------------------------------------------- |
| `APP_DEFAULT_LANG` | `ru`         | Язык по умолчанию                                               |
| `APP_DEBUG`        | `1`          | Режим отладки                                                   |
| `APP_ENV`          | `prod`       | Окружение (`prod` / `dev`)                                      |
| `APP_BASE_URL`     | auto         | Базовый URL (опционально, можно переопределить автоопределение) |

## Развертывание

### Требования к серверу

- **PHP 8.5+** с модулями: json, mbstring
- **Apache** с поддержкой mod_rewrite (или Nginx с эквивалентными правилами)
- **Node.js 16+** для сборки ресурсов
- **Composer** для PHP-зависимостей

### Процесс развертывания

1. Клонирование репозитория
2. Копирование `.env.example` → `.env` и настройка переменных
3. Установка зависимостей: `composer install && npm install`
4. Сборка ресурсов: `npm run build`
5. Настройка веб-сервера: **DocumentRoot обязательно указывает на `public/`** (единственная точка входа — `public/index.php`)

### Apache (рекомендуемо для текущего проекта)

Минимальный VirtualHost:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/platform/public

    <Directory /var/www/platform/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Важно: должны быть включены `mod_rewrite` и `AllowOverride All` для `public/.htaccess`.

### Nginx (эквивалент)

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/platform/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
```

## Производительность и оптимизация

### Кеширование

- **Хеширование файлов**: Автоматические content-хеши для CSS и JS
- **Twig-кеш**: Компиляция шаблонов в `/cache` (настраивается через конфиг)
- **HTTP-кеширование**: Настройки в `.htaccess` для статических ресурсов

### Логирование

- **Логи приложения**: `logs/app.log` (Monolog)
- Ошибки PHP/Slim также доступны через стандартный error log веб-сервера

### Оптимизация изображений

- **WebP формат**: Современный формат для всех изображений
- **Lazy loading**: Отложенная загрузка изображений

### JavaScript

- **Code splitting**: Автоматическое разделение на чанки (runtime, vendors, ui-vendors, util-vendors, main)
- **Tree shaking**: Удаление неиспользуемого кода
- **Минификация**: Сжатие в production-режиме

### CSS

- **Модульность**: Компонентный подход — загрузка только необходимых стилей
- **PostCSS-оптимизация**: Автопрефиксы и минификация через cssnano

## Roadmap / Чеклист улучшений

### Требования: Производительность и SEO

Целевые метрики и критерии, которым должен соответствовать проект.

**Производительность (Core Web Vitals и др.):**

| Требование                      | Цель                       | Чеклист                                                                                                                            |
| ------------------------------- | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| First Contentful Paint (FCP)    | ≤ 1.8 с                    | Мониторинг, оптимизация критического пути, preload шрифтов/hero                                                                    |
| Largest Contentful Paint (LCP)  | ≤ 2.5 с                    | Preload hero-изображений, WebP, width/height, серверное сжатие                                                                     |
| Cumulative Layout Shift (CLS)   | < 0.1                      | width/height у всех изображений, резервирование места под контент                                                                  |
| Interaction to Next Paint (INP) | ≤ 200 мс                   | Дебаунс/троттл, лёгкий JS, минификация                                                                                             |
| Изображения                     | WebP, width/height         | Компонент picture: WebP, явные width/height из манифеста                                                                           |
| Lazy loading                    | Ниже первого экрана        | picture.twig: `loading="lazy"` по умолчанию; при добавлении img — см. [images-lazy-loading.md](docs/guides/images-lazy-loading.md) |
| Минификация и сжатие            | CSS/JS minify, gzip/brotli | CSS/JS минифицируются; gzip/brotli — в чеклисте                                                                                    |

**SEO и мета-данные:**

| Требование                              | Чеклист                                               |
| --------------------------------------- | ----------------------------------------------------- |
| Уникальные `title` и `meta description` | Через `pageSeoData.meta`                              |
| Один `<h1>` на странице                 | Аудит шаблонов, конвенция в компонентах               |
| Иерархия заголовков h1 → h2 → h3        | Аудит, документация                                   |
| `alt` у всех изображений                | Проверить все `img`/picture (декоративные — `alt=""`) |
| Канонический URL                        | Реализовано в base.twig                               |
| Open Graph и Twitter Card               | В чеклисте (Фронтенд: шаблоны)                        |
| Favicon                                 | Реализовано (favicons.twig)                           |
| robots.txt                              | Есть; актуализировать пути — в чеклисте               |
| sitemap.xml                             | В чеклисте                                            |

Ниже — детальный чеклист задач для выполнения этих требований. **Задачи по формам (form-callback, api/send, CSRF, rate limiting, контракт API) — на hold.**

---

### Архитектура и ядро

- [x] Миграция на Slim 4 (PSR-7/11/15)
- [x] PHP-DI контейнер с autowiring
- [x] PSR-15 middleware (trailing slash, redirects, language)
- [x] Single Action Controllers (PageAction)
- [x] Сервисный слой (DataLoader, Seo, Language, TemplateDataBuilder)
- [x] `public/` как DocumentRoot (безопасность)
- [x] `vlucas/phpdotenv` вместо кастомного .env loader
- [x] `monolog` вместо кастомного логгера
- [x] `slim/twig-view` вместо ручного TemplateEngine
- [x] Единый production error-handler (структурированный JSON, 500.twig, маскирование данных)
- [x] Correlation ID middleware (X-Request-Id в запросе и ответе)
- [x] Карта доменных ошибок (400/404/409/500 с чистыми сообщениями)
- [x] Body parsing middleware для POST-форм (если понадобится API)
- [x] `APP_ENV` (production/development) — программное разделение окружений (Twig cache, log level)
- [x] PHP-DI autowiring вместо ручного создания простых сервисов в `container.php`

### Безопасность

- [x] HTTPS redirect в `.htaccess`
- [x] `public/` как DocumentRoot (исходники вне веб-корня)
- [x] HTTP security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy)
- [x] HSTS (Strict-Transport-Security) — в production через SecurityHeadersMiddleware
- [x] Content-Security-Policy (хотя бы базовая)
- [x] Блокировка доступа к `.env`, `.git`, `config/`, `src/` через `.htaccess` в корне (защита при ошибке DocumentRoot)
- [x] CORS middleware (подготовка для API / форм)
- [x] Брендированная страница ошибки `500.twig` (ServerErrorHandler)

### Структура проекта

- [x] `core/` -> `src/` (PSR-4 стандарт)
- [x] `dev/` -> `docs/` (guides + architecture)
- [x] `scripts/` -> `tools/` (build / scaffold / ops)
- [x] `config/` отделён от кода (settings, container, middleware, routes, redirects)
- [x] Симлинки `public/assets`, `public/data`, `public/robots.txt`
- [x] Автосоздание симлинков при сборке (`setup-public-links`)
- [x] `.editorconfig` — `[*.php]` с `indent_size = 4` (PSR-12)
- [x] `.gitignore` — симлинки `public/assets`, `public/data`, `public/robots.txt`
- [x] `.env.example` — `APP_ENV`, `APP_BASE_URL`, `YANDEX_METRIC_ID` (опционально)
- [x] `robots.txt` — актуальные Disallow: `/src/`, `/config/`, `/tools/`

### Quality gates и линтинг

- [x] ESLint (flat config, v10) для `tools/`, `webpack.config`, `postcss.config`
- [x] Prettier (форматирование toolchain-файлов)
- [x] `npm run check` (lint + format:check + validate-json)
- [x] Quality gate встроен в `npm run build`
- [x] ESLint для `assets/js/**` (весь клиентский JS)
- [x] Stylelint для `assets/css/**` (весь CSS)
- [x] PHPStan / Psalm для `src/**` (статический анализ PHP)
- [x] PHP-CS-Fixer / ECS для `src/**` (форматирование PHP)
- [x] Pre-commit hook (husky + lint-staged): автопроверка при коммите

### Фронтенд: шаблоны

- [x] Переименовать `layout.twig` → `base.twig` (имя `base` отражает роль базового шаблона)
- [x] Единый `page.twig` вместо 6 идентичных файлов в `pages/` (data-driven рендеринг секций)
- [x] Accessibility: `<button>` вместо `<a href="javascript:void(0)">` в `accordion.twig`
- [x] Accessibility: ARIA-атрибуты (`aria-expanded`, `aria-controls`) для accordion, burger-menu
- [x] Accessibility: `aria-label` для кнопок без текста (burger: открыть/закрыть меню)
- [x] Open Graph теги (`og:title`, `og:description`, `og:image`, `og:url`) в `base.twig` (из pageSeoData.meta)
- [x] Twitter Card разметка (`twitter:card`, `twitter:title`, `twitter:image`) в `base.twig`
- [x] SEO: один `<h1>` на страницу, иерархия h1 → h2 → h3 (аудит шаблонов)
- [x] SEO: `alt` у всех изображений (декоративные — `alt=""`)
- [x] Inline-стили → CSS-классы (`spoiler`, `cookie-panel`, `card-nav`, `card-gradient`, `footer`, `intro`, `slider`)
- [x] Логотип в `intro.twig` — вынести дубликат в компонент
- [x] `<link rel="preload">` для критичных шрифтов в `<head>`
- [x] `<link rel="preload">` для hero-изображений above-the-fold

### Фронтенд: JavaScript

- [x] Убрать отладочные `console.log` из production-кода
- [ ] `form-callback.js` — переписать логику форм с нуля (модульная архитектура, валидация, отправка)
- [x] `accordion.js` — CSS-классы вместо inline-стилей через JS
- [x] Debounce/throttle утилиты (assets/js/base/debounce-throttle.js)
- [x] Единая система инициализации компонентов (вместо множественных `DOMContentLoaded`)
- [x] Проверить актуальность версий vendor-библиотек (jQuery, Swiper, GLightbox, Inputmask)

### Фронтенд: CSS и шрифты

- [x] Заменить связку плагинов на `postcss-preset-env` (custom-media, nested, custom-properties + container-queries)
- [x] Container Queries — использовать для карточек/компонентов (адаптация к размеру контейнера)
- [x] Аудит неиспользуемых начертаний шрифтов (загружаются 100–900)
- [x] Рассмотреть variable fonts для сокращения количества файлов
- [x] `MiniCssExtractPlugin` в webpack для production (вместо `style-loader`)

### Производительность и кэширование

- [x] Webpack 5 с code splitting и content-hash
- [x] PostCSS с custom properties, nesting, autoprefixer, cssnano
- [x] CSS/JS манифесты для cache-busting
- [x] Очистка устаревших ассетов (clean-assets)
- [x] Source maps отключены в production JS (webpack)
- [x] Twig кэш в production (`cache => $projectRoot . '/cache/twig'` при `APP_ENV=production`)
- [x] Cache-Control / Expires для статики в `public/.htaccess` (img, css, js, fonts)
- [x] Gzip/Brotli сжатие (mod_deflate / mod_brotli в `public/.htaccess`)
- [x] PostCSS source maps отключить в production (`tools/build/css-hash.js`)
- [x] Оптимизация изображений при сборке (sharp в `tools/build/build-images.js`, ключи из `config/image-sizes.json`)
- [x] Генерация WebP при сборке по ключам из конфига; в `picture.twig` — явные `width`/`height` у `<img>` (CLS < 0.1)
- [x] Lazy loading для всех изображений ниже первого экрана (аудит вызовов picture/img); поддержка — [docs/guides/images-lazy-loading.md](docs/guides/images-lazy-loading.md)
- [x] Цели по метрикам: FCP ≤ 1.8 с, LCP ≤ 2.5 с, CLS < 0.1, INP ≤ 200 мс ([docs/guides/metrics-goals.md](docs/guides/metrics-goals.md), Lighthouse-аудит, при необходимости — мониторинг)

### Конфигурация и окружение

- [x] Вынести `YANDEX_METRIC_ID` из `base.twig` в `.env` (config.settings.yandex_metric_id)
- [x] Twig-кэш: привязан к APP_ENV в config/settings.php
- [x] Лог-уровень: `DEBUG` для dev, `WARNING` для production (через `APP_ENV`)

### Документация

- [x] README.md обновлён под Slim 4 + новую структуру
- [x] Конфиги Apache и Nginx в README
- [x] Описание env-переменных
- [x] `docs/guides/local-setup.md` — пошаговый запуск с нуля (Valet, Composer, npm)
- [x] `docs/guides/deploy-checklist.md` — чеклист продакшн-выкатки
- [x] Эталонная структура: [docs/architecture/structure.md](docs/architecture/structure.md); ссылки `scripts/` → `tools/`, убраны portfolio из docs

### DevOps / CI

- [ ] GitHub Actions / GitLab CI (lint + check + build + php syntax)
- [ ] Автоматический деплой (staging / production)

### Тестирование

- [x] Валидация JSON (`validate-json`)

**PHP (PHPUnit) — `tests/php/`:**

- [x] Unit: `DataLoaderService` — загрузка JSON, обработка отсутствующих файлов
- [x] Unit: `SeoService` — обработка Twig-шаблонов в SEO-данных
- [x] Unit: `LanguageService` — определение языка из URL
- [x] Unit: `TemplateDataBuilder` — сборка данных для шаблонов
- [x] Unit: `JsonProcessor` — обработка путей в JSON
- [x] Integration: `PageAction` — рендеринг страниц (200, 404)
- [x] Integration: Middleware-цепочка (trailing slash, redirects, language)

**JS (Vitest) — `tests/js/`:**

- [ ] Unit: утилита `url()` из `main.js`
- [ ] Unit: логика форм (новый `form-callback`)
- [ ] Unit: accordion — toggle, show-all, ARIA-состояния
- [ ] Unit: debounce/throttle утилиты

**Smoke-тесты — `tests/smoke/`:**

- [ ] `GET /` → 200, `Content-Type: text/html`
- [ ] `GET /contacts/` → 200
- [ ] `GET /nonexistent/` → 404
- [ ] `GET /en/` → 200 (мультиязычность)
- [ ] Redirect `http → https`
- [ ] Redirect без trailing slash → со слешом

**Инфраструктура:**

- [x] Создать `tests/` со структурой `php/`, `js/`, `smoke/`
- [x] `phpunit.xml` в корне проекта
- [ ] `vitest.config.js` для JS-тестов
- [ ] Интеграция тестов в `npm run build` (vitest --run перед webpack)
- [ ] `npm run test` — запуск PHPUnit + Vitest

### Конфигурация и код (из «Что не учтено»)

- [x] `config.settings.available_langs` — задать в `config/settings.php` (документация: docs/architecture/config.md)
- [ ] Trailing slash для статики в `public/.htaccess` — при необходимости редирект URL без слеша на со слешом для статических файлов
- [ ] Блокировка доступа к `.env`, `config/`, `src/` при ошибочном DocumentRoot (правила в .htaccess корня)

### Безопасность и формы

"- [ ] CSRF-токены для форм (form-callback и др.): токен в форме + проверка на бэкенде"

- [ ] Rate limiting для формы обратной связи и API
- [ ] Валидация и санитизация на бэкенде для api/send; описать контракт в README/docs

### Производительность и мониторинг

- [x] Описать замер FCP/LCP/CLS/INP (Lighthouse, RUM, CI) и реакцию на деградацию — [docs/guides/metrics-goals.md](docs/guides/metrics-goals.md)
- [ ] Health check endpoint (`/health` или `/ping`) для мониторинга
- [ ] Структурированное логирование: зафиксировать поля (request_id, duration и т.д.) и способ поиска

### Фронтенд и доступность

- [ ] Browserslist в проекте (package.json или .browserslistrc) для autoprefixer/полифиллов
- [ ] Skip-link «перейти к контенту» и управление фокусом (модалки, меню)
- [ ] Зафиксировать целевой уровень WCAG (A/AA) в документации

### Данные и контент

- [ ] Описать политику резервного копирования (data/json, медиа, конфиги)
- [ ] Описать подход к версионированию/изменениям контента в JSON (история, откат)

### GEO (оптимизация для LLM)

GEO (Generative Engine Optimization) — оптимизация контента для AI-поисковиков (Google AI Overviews, Bing Chat, Perplexity, ChatGPT Search и др.), чтобы LLM могли находить, понимать и цитировать контент платформы.

**Индексация и доступность для AI-краулеров:**

- [ ] Создать `public/llms.txt` — машиночитаемое описание сайта для LLM-краулеров (структура, назначение, основные страницы, контакты)
- [ ] Создать `public/llms-full.txt` — расширенная версия с детальным описанием всех ресторанов и услуг
- [ ] Обновить `public/robots.txt` — явно разрешить AI-краулеры (GPTBot, Google-Extended, ChatGPT-User, PerplexityBot, ClaudeBot, Applebot-Extended) или ограничить доступ для отдельных ботов по необходимости
- [ ] Генерация `sitemap.xml` — динамическая генерация с учётом мультиязычности и hreflang (для обнаружения страниц как классическими, так и AI-краулерами)

**Расширение структурированных данных (Schema.org JSON-LD):**

- [ ] Генерировать JSON-LD `Restaurant` на страницах ресторанов — данные уже есть в `data/json/{lang}/restaurants/*.json`, нужно рендерить JSON-LD в шаблоне (name, address, geo, openingHours, servesCuisine, priceRange, telephone, menu, hasMap)
- [ ] Добавить JSON-LD `BreadcrumbList` — навигационная цепочка на всех внутренних страницах для понимания иерархии сайта
- [ ] Перевести FAQ-разметку с микроданных на JSON-LD `FAQPage` — LLM лучше парсят JSON-LD, чем microdata; шаблон `accordion.twig` уже содержит Q&A-контент
- [ ] Добавить JSON-LD `WebSite` с `SearchAction` — описание сайта и потенциала навигации
- [ ] Рассмотреть JSON-LD `Menu`/`MenuSection` для ресторанов (при наличии данных о меню)
- [ ] Рассмотреть JSON-LD `Review`/`AggregateRating` для ресторанов (при наличии отзывов)

**Цитируемость контента:**

- [ ] Обеспечить чёткие утверждения-факты в тексте (адреса, часы работы, кухня, цены) — LLM цитируют конкретику, а не общие фразы
- [ ] Добавить структурированные блоки информации — таблицы (часы работы, типы кухни), списки (преимущества, услуги), определения
- [ ] Использовать формат «вопрос — ответ» в контенте (не только в аккордеонах) — AI-поисковики предпочитают Q&A-структуру
- [ ] Обеспечить уникальные, информативные `<title>` и `<meta description>` — LLM используют их как первичный источник для понимания страницы

**Семантическая HTML-разметка:**

- [ ] Проверить иерархию заголовков (`h1`→`h2`→`h3`) на всех страницах — LLM опираются на заголовки для понимания структуры
- [ ] Использовать семантические теги (`<article>`, `<section>`, `<aside>`, `<address>`, `<time>`) в шаблонах секций
- [ ] Добавить атрибуты `lang` к контентным блокам при мультиязычном контенте на одной странице

**Мониторинг и документация:**

- [ ] Описать GEO-стратегию в `docs/guides/geo-strategy.md` — принципы, чеклист, инструменты проверки
- [ ] Проверять видимость в AI-поисковиках — периодически тестировать запросы в Perplexity, Bing Chat, Google AI Overviews по целевым ключевым фразам
- [ ] Валидировать JSON-LD через Google Rich Results Test и Schema.org Validator после каждого изменения разметки

### DevOps и процесс

- [ ] Правила ведения CHANGELOG и семантического версионирования релизов
- [ ] Политика обновления зависимостей (Dependabot/Renovate или ручной процесс)
- [ ] Документировать хранение секретов в CI/CD и на сервере (помимо .env)

### Документация

- [ ] Описать контракт API (api/send и др.): метод, поля, коды ответов
- [ ] JSON Schema или примеры для валидации структуры `data/json` (при росте проекта)

---

## Что не учтено в проекте

Все пункты преобразованы в задачи и добавлены в Roadmap выше (блоки «Конфигурация и код», «Безопасность и формы», «Производительность и мониторинг», «Фронтенд и доступность», «Данные и контент», «GEO (оптимизация для LLM)», «DevOps и процесс», «Документация»).

---

Платформа построена с учётом лучших практик разработки, производительности и SEO-оптимизации. Модульная архитектура обеспечивает лёгкость поддержки и расширения функциональности.
