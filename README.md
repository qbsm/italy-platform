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
- **PHP 8.1+** — серверная логика и маршрутизация
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
  │   ├─ TrailingSlashMiddleware
  │   ├─ RedirectMiddleware (config/redirects.json)
  │   └─ LanguageMiddleware
  ├─ Routes (config/routes.php)
  └─ PageAction
      ├─ DataLoaderService
      ├─ SeoService
      ├─ TemplateDataBuilder
      └─ Twig render
```

Основные модули:

- `src/Action` — HTTP-обработчики (сейчас `PageAction`)
- `src/Middleware` — PSR-15 middleware (URL-нормализация, редиректы, язык)
- `src/Service` — бизнес-логика загрузки данных/SEO/сборки шаблонных данных
- `src/Twig` — Twig-расширения (`AssetExtension`, `DataExtension`, `UrlExtension`)
- `src/Support` — вспомогательные утилиты (`JsonProcessor`, `BaseUrlResolver`)
- `config/` — конфигурация контейнера, маршрутов, middleware и runtime-настроек

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
├── layout.twig                    # Базовый шаблон (мета-теги, canonical, скрипты)
├── pages/                         # Шаблоны страниц (расширяют layout)
│   ├── index.twig
│   ├── contacts.twig
│   ├── policy.twig
│   ├── agree.twig
│   ├── restaurants-list.twig
│   └── 404.twig
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
- **Cookie-панель**: Глобальная панель из `layout.twig`, тексты из `global.json`

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

| Переменная | По умолчанию | Описание |
|---|---|---|
| `APP_DEFAULT_LANG` | `ru` | Язык по умолчанию |
| `APP_DEBUG` | `1` | Режим отладки |
| `APP_ENV` | `prod` | Окружение (`prod` / `dev`) |
| `APP_BASE_URL` | auto | Базовый URL (опционально, можно переопределить автоопределение) |

## Развертывание

### Требования к серверу
- **PHP 8.1+** с модулями: json, mbstring
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
    DocumentRoot /var/www/italy-platform/public

    <Directory /var/www/italy-platform/public>
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
    root /var/www/italy-platform/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
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

| Требование | Цель | Чеклист |
|------------|------|---------|
| First Contentful Paint (FCP) | ≤ 1.8 с | Мониторинг, оптимизация критического пути, preload шрифтов/hero |
| Largest Contentful Paint (LCP) | ≤ 2.5 с | Preload hero-изображений, WebP/AVIF, width/height, серверное сжатие |
| Cumulative Layout Shift (CLS) | < 0.1 | width/height у всех изображений, резервирование места под контент |
| Interaction to Next Paint (INP) | ≤ 200 мс | Дебаунс/троттл, лёгкий JS, минификация |
| Изображения | WebP/AVIF, width/height | Компонент picture: WebP есть; AVIF + явные width/height — в чеклисте |
| Lazy loading | Ниже первого экрана | picture.twig: `loading="lazy"` по умолчанию; проверить все img |
| Минификация и сжатие | CSS/JS minify, gzip/brotli | CSS/JS минифицируются; gzip/brotli — в чеклисте |

**SEO и мета-данные:**

| Требование | Чеклист |
|------------|---------|
| Уникальные `title` и `meta description` | Через `pageSeoData.meta` |
| Один `<h1>` на странице | Аудит шаблонов, конвенция в компонентах |
| Иерархия заголовков h1 → h2 → h3 | Аудит, документация |
| `alt` у всех изображений | Проверить все `img`/picture (декоративные — `alt=""`) |
| Канонический URL | Реализовано в layout.twig |
| Open Graph и Twitter Card | В чеклисте (Фронтенд: шаблоны) |
| Favicon | Реализовано (favicons.twig) |
| robots.txt | Есть; актуализировать пути — в чеклисте |
| sitemap.xml | В чеклисте |

Ниже — детальный чеклист задач для выполнения этих требований.

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
- [ ] Единый production error-handler (структурированный JSON, 500.twig, маскирование данных)
- [ ] Correlation ID middleware (X-Request-Id в логах и ответах)
- [ ] Карта доменных ошибок (400/404/409/500 с чистыми сообщениями)
- [ ] Body parsing middleware для POST-форм (если понадобится API)
- [ ] `APP_ENV` (production/development) — программное разделение окружений
- [ ] PHP-DI autowiring вместо ручного создания простых сервисов в `container.php`

### Безопасность

- [x] HTTPS redirect в `.htaccess`
- [x] `public/` как DocumentRoot (исходники вне веб-корня)
- [ ] HTTP security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy)
- [ ] HSTS (Strict-Transport-Security) — раз HTTPS уже форсируется
- [ ] Content-Security-Policy (хотя бы базовая)
- [ ] Блокировка доступа к `.env`, `.git`, `composer.*` через `.htaccess` (защита при ошибке DocumentRoot)
- [ ] CORS middleware (подготовка для API / форм)
- [ ] Брендированная страница ошибки `500.twig` вместо Slim default

### Структура проекта

- [x] `core/` -> `src/` (PSR-4 стандарт)
- [x] `dev/` -> `docs/` (guides + architecture)
- [x] `scripts/` -> `tools/` (build / scaffold / ops)
- [x] `config/` отделён от кода (settings, container, middleware, routes, redirects)
- [x] Симлинки `public/assets`, `public/data`, `public/robots.txt`
- [x] Автосоздание симлинков при сборке (`setup-public-links`)
- [ ] `.editorconfig` — добавить `[*.php]` с `indent_size = 4` (PSR-12)
- [ ] `.gitignore` — добавить `public/assets`, `public/data`, `public/robots.txt` (симлинки)
- [ ] `.env.example` — добавить `APP_ENV`, `APP_BASE_URL`
- [ ] `robots.txt` — убрать устаревшие `/dev/`, `/scripts/`, `/core/`, добавить `/src/`, `/config/`, `/tools/`

### Quality gates и линтинг

- [x] ESLint (flat config, v10) для `tools/`, `webpack.config`, `postcss.config`
- [x] Prettier (форматирование toolchain-файлов)
- [x] `npm run check` (lint + format:check + validate-json)
- [x] Quality gate встроен в `npm run build`
- [ ] ESLint для `assets/js/**` (весь клиентский JS)
- [ ] Stylelint для `assets/css/**` (весь CSS)
- [ ] PHPStan / Psalm для `src/**` (статический анализ PHP)
- [ ] PHP-CS-Fixer / ECS для `src/**` (форматирование PHP)
- [ ] Pre-commit hook (husky + lint-staged): автопроверка при коммите

### Фронтенд: шаблоны

- [ ] Переименовать `layout.twig` → `base.twig` (имя `base` точнее отражает роль базового шаблона)
- [ ] Единый `page.twig` вместо 6 идентичных файлов в `pages/` (data-driven рендеринг секций)
- [ ] Accessibility: `<button>` вместо `<a href="javascript:void(0)">` в `accordion.twig`
- [ ] Accessibility: ARIA-атрибуты (`aria-expanded`, `aria-controls`) для accordion, burger-menu
- [ ] Accessibility: `aria-label` для кнопок без текста
- [ ] Open Graph теги (`og:title`, `og:description`, `og:image`, `og:url`) в `layout.twig`
- [ ] Twitter Card разметка (`twitter:card`, `twitter:title`, `twitter:image`) в `layout.twig`
- [ ] SEO: один `<h1>` на страницу, иерархия h1 → h2 → h3 (аудит шаблонов)
- [ ] SEO: `alt` у всех изображений (декоративные — `alt=""`)
- [ ] Inline-стили → CSS-классы (`spoiler`, `cookie-panel`, `card-nav`, `card-gradient`, `footer`, `intro`, `slider`)
- [ ] Логотип в `intro.twig` — вынести дубликат в компонент
- [ ] `<link rel="preload">` для критичных шрифтов в `<head>`
- [ ] `<link rel="preload">` для hero-изображений above-the-fold

### Фронтенд: JavaScript

- [ ] Убрать `console.log` из production-кода (или обернуть в `APP_DEBUG`)
- [ ] `form-callback.js` — переписать логику форм с нуля (модульная архитектура, валидация, отправка)
- [ ] `accordion.js` — CSS-классы вместо inline-стилей через JS
- [ ] Debounce/throttle утилиты для scroll/resize обработчиков
- [ ] Единая система инициализации компонентов (вместо множественных `DOMContentLoaded`)
- [ ] Проверить актуальность версий vendor-библиотек (jQuery, Swiper, GLightbox, Inputmask)

### Фронтенд: CSS и шрифты

- [x] Заменить связку плагинов на `postcss-preset-env` (custom-media, nested, custom-properties + container-queries)
- [ ] Container Queries — использовать для карточек/компонентов (адаптация к размеру контейнера)
- [ ] Аудит неиспользуемых начертаний шрифтов (загружаются 100–900)
- [ ] Рассмотреть variable fonts для сокращения количества файлов
- [ ] `MiniCssExtractPlugin` в webpack для production (вместо `style-loader`)

### Производительность и кэширование

- [x] Webpack 5 с code splitting и content-hash
- [x] PostCSS с custom properties, nesting, autoprefixer, cssnano
- [x] CSS/JS манифесты для cache-busting
- [x] Очистка устаревших ассетов (clean-assets)
- [x] Source maps отключены в production JS (webpack)
- [ ] Twig кэш в production (`cache => $projectRoot . '/cache/twig'` при `APP_ENV=production`)
- [ ] Cache-Control / Expires для статики в `.htaccess` (img, css, js, fonts)
- [ ] Gzip/Brotli сжатие (mod_deflate / mod_brotli в `.htaccess`)
- [ ] PostCSS source maps отключить в production (`css-hash.js`)
- [ ] Оптимизация изображений при сборке (sharp/imagemin в `tools/build`)
- [ ] Генерация WebP/AVIF при сборке; в `picture.twig` — явные `width`/`height` у `<img>` (CLS < 0.1)
- [ ] Lazy loading для всех изображений ниже первого экрана (аудит вызовов picture/img)
- [ ] Цели по метрикам: FCP ≤ 1.8 с, LCP ≤ 2.5 с, CLS < 0.1, INP ≤ 200 мс (Lighthouse-аудит, при необходимости — мониторинг)

### Конфигурация и окружение

- [ ] Вынести `YANDEX_METRIC_ID` из `layout.twig` в `.env` / `global.json`
- [ ] Twig-кэш: привязать к `APP_ENV` вместо хардкода `false`
- [ ] Лог-уровень: `DEBUG` для dev, `WARNING` для production (через `APP_ENV`)

### Документация

- [x] README.md обновлён под Slim 4 + новую структуру
- [x] Конфиги Apache и Nginx в README
- [x] Описание env-переменных
- [ ] `docs/guides/local-setup.md` — пошаговый запуск с нуля (Valet, Composer, npm)
- [ ] `docs/guides/deploy-checklist.md` — чеклист продакшн-выкатки
- [x] Эталонная структура: [docs/architecture/structure.md](docs/architecture/structure.md); ссылки `scripts/` → `tools/`, убраны portfolio из docs

### DevOps / CI

- [ ] GitHub Actions / GitLab CI (lint + check + build + php syntax)
- [ ] Автоматический деплой (staging / production)

### Тестирование

- [x] Валидация JSON (`validate-json`)

**PHP (PHPUnit) — `tests/php/`:**
- [ ] Unit: `DataLoaderService` — загрузка JSON, обработка отсутствующих файлов
- [ ] Unit: `SeoService` — обработка Twig-шаблонов в SEO-данных
- [ ] Unit: `LanguageService` — определение языка из URL
- [ ] Unit: `TemplateDataBuilder` — сборка данных для шаблонов
- [ ] Unit: `JsonProcessor` — обработка путей в JSON
- [ ] Integration: `PageAction` — рендеринг страниц (200, 404)
- [ ] Integration: Middleware-цепочка (trailing slash, redirects, language)

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
- [ ] Создать `tests/` со структурой `php/`, `js/`, `smoke/`
- [ ] `phpunit.xml` в корне проекта
- [ ] `vitest.config.js` для JS-тестов
- [ ] Интеграция тестов в `npm run build` (vitest --run перед webpack)
- [ ] `npm run test` — запуск PHPUnit + Vitest

---

Платформа построена с учётом лучших практик разработки, производительности и SEO-оптимизации. Модульная архитектура обеспечивает лёгкость поддержки и расширения функциональности.
