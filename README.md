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
- **jQuery** — для совместимости с legacy-компонентами
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

### Ядро приложения (`core/`)

Централизованное ядро с модульной архитектурой (подробности в `core/README.md`).

Жизненный цикл запроса:

```
index.php → ConfigManager::build() → Application::run()
  ├─ Redirector           проверка redirects.json → 301/302
  ├─ UrlParser            разбор URL на сегменты
  ├─ loadGlobal()         чтение global.json
  ├─ LanguageDetector     определение языка из URL
  ├─ Router               page_id из сегмента (или "index")
  ├─ PageDataLoader       data/json/{lang}/pages/{page_id}.json
  ├─ SeoDataLoader        data/json/{lang}/seo/{page_id}.json
  ├─ TemplateEngine       инициализация Twig + расширения
  ├─ SeoProcessor         рендер Twig-плейсхолдеров в SEO-полях
  ├─ TemplateResolver     pages/{page_id}.twig → фолбэк на 404.twig
  ├─ TemplateDataBuilder  сборка данных для шаблона
  └─ Twig::render()       вывод HTML
```

Модули ядра:

- `Bootstrap` — `Application` (оркестратор запроса), `ServiceContainer` (DI-контейнер)
- `Config` — `ConfigManager`, `EnvironmentDetector`, `PathResolver`
- `Routing` — `Router`, `Redirector`, `UrlParser`, `redirects.json`
- `Language` — `LanguageDetector`, `LanguageService`
- `Data` — `DataLoader`, `PageDataLoader`
- `SEO` — `SeoDataLoader`, `SeoProcessor`
- `Template` — `TemplateEngine`, `TemplateResolver`, `TemplateDataBuilder`
- `Twig` — расширения: `AssetExtension` (манифесты ассетов), `UrlExtension` (генерация URL)
- `Logging` — `Logger`, `RequestLogger`
- `Utils` — `JsonProcessor` (обработка путей в JSON), `ErrorHandler`

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

```
templates/
├── layout.twig                    # Базовый шаблон с мета-тегами и структурой
├── pages/                         # Шаблоны страниц
│   ├── index.twig                 # Главная страница
│   ├── contacts.twig              # Контакты
│   ├── policy.twig                # Политика конфиденциальности
│   ├── agree.twig                 # Пользовательское соглашение
│   └── 404.twig                   # Страница ошибки 404
├── sections/                      # Переиспользуемые секции
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
bash scripts/test-htaccess.sh

# Исправление прав доступа
npm run fix-permissions
```

## Структура файлов

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
├── core/                          # Ядро PHP-приложения (см. core/README.md)
│   ├── Bootstrap/                 # Application, ServiceContainer
│   ├── Config/                    # ConfigManager, EnvironmentDetector, PathResolver
│   ├── Routing/                   # Router, Redirector, UrlParser, redirects.json
│   ├── Language/                  # LanguageDetector, LanguageService
│   ├── Data/                      # DataLoader, PageDataLoader
│   ├── SEO/                       # SeoDataLoader, SeoProcessor
│   ├── Template/                  # TemplateEngine, TemplateResolver, TemplateDataBuilder
│   ├── Twig/                      # AssetExtension, UrlExtension
│   ├── Logging/                   # Logger, RequestLogger
│   └── Utils/                     # JsonProcessor, ErrorHandler
├── data/                          # Данные и медиа
│   ├── json/                      # JSON-данные (см. выше)
│   └── img/                       # Изображения (ui, favicons, seo, контент)
├── dev/                           # Документация для разработки
│   ├── instructions/              # Инструкции по добавлению страниц, SEO и т.д.
│   └── docs/                      # Техническая документация
├── scripts/                       # Скрипты автоматизации
│   ├── create-component.js        # Генератор компонента (twig + css + js)
│   ├── create-section.js          # Генератор секции
│   ├── create-page.js             # Генератор страницы
│   ├── css-hash.js                # Сборка и хеширование CSS
│   ├── clean-assets.js            # Очистка устаревших файлов сборки
│   ├── validate-json.js           # Валидация JSON-файлов
│   ├── generate-favicons.js       # Генерация фавиконок
│   ├── test-htaccess.sh           # Тест правил .htaccess
│   └── fix-permissions.sh         # Исправление прав доступа
├── templates/                     # Twig-шаблоны (см. выше)
├── .env.example                   # Пример переменных окружения
├── .htaccess                      # Правила Apache (mod_rewrite)
├── index.php                      # Точка входа → core/Bootstrap/Application
├── robots.txt                     # Правила для поисковых роботов
├── package.json                   # Node.js зависимости и скрипты
├── composer.json                  # PHP зависимости (Twig 3.x)
├── webpack.config.js              # Конфигурация Webpack 5
└── postcss.config.js              # Конфигурация PostCSS
```

## Переменные окружения

| Переменная | По умолчанию | Описание |
|---|---|---|
| `APP_DEFAULT_LANG` | `ru` | Язык по умолчанию |
| `APP_DEBUG` | `1` | Режим отладки |
| `APP_ENV` | `prod` | Окружение (`prod` / `dev`) |

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
5. Настройка веб-сервера (DocumentRoot на корень проекта)

## Производительность и оптимизация

### Кеширование
- **Хеширование файлов**: Автоматические content-хеши для CSS и JS
- **Twig-кеш**: Компиляция шаблонов в `/cache` (настраивается через конфиг)
- **HTTP-кеширование**: Настройки в `.htaccess` для статических ресурсов

### Логирование
- **Логи приложения**: `logs/app.txt` — информационные сообщения
- **Логи ошибок**: `logs/errors.txt` — ошибки с контекстом
- Директория `logs/` создаётся автоматически при первом запуске

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

---

Платформа построена с учётом лучших практик разработки, производительности и SEO-оптимизации. Модульная архитектура обеспечивает лёгкость поддержки и расширения функциональности.
