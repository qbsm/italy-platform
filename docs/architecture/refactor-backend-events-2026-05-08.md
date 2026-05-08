# Рефакторинг бекенда под архитектуру kumho-tires (события + collections + Mailer)

Ветка: `refactor/backend-slim-events` (commit `31318f4`).
Дата: 2026-05-08.
Статус: развёрнуто на staging (`https://italycommunity.ru.ismart.pro/`), в `main` не слито.

## Что сделано

### Новые файлы

| Файл                                           | Роль                                                                                                                                                              |
| ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `src/Event/PageLoaded.php`                     | Readonly-событие после загрузки pageData (pageId, langCode, pageData, status)                                                                                     |
| `src/Event/EntityResolved.php`                 | Readonly-событие после успешного `loadEntity` (entityType, slug, entity, config)                                                                                  |
| `src/Event/SeoBuilt.php`                       | Readonly-событие после финализации seoData (pageId, seoData, isEntity)                                                                                            |
| `src/Service/SeoBuilderInterface.php`          | Контракт `build($entity, $baseUrl, $langCode, $config, $global): array`                                                                                           |
| `src/Service/RestaurantSeoBuilder.php`         | Реализация для ресторанов: og + Schema.org/Restaurant + FAQPage. Перенесена из `PageAction::buildSeoForRestaurant/buildRestaurantJsonLd/buildRestaurantFaqJsonLd` |
| `src/Service/MailService.php`                  | Symfony Mailer + html/text шаблон письма + вложения (`sendFormSubmission`)                                                                                        |
| `src/Middleware/RequestDurationMiddleware.php` | `X-Response-Time` хедер + JSON-лог `request_completed` (request_id, method, path, status, duration_ms)                                                            |

### Обновлённые файлы

- **`src/Action/PageAction.php`** — generic под `settings.collections`. Добавлен поиск сущности (Кейс A — direct slug `/article/`, Кейс B — prefixed `/restaurants/{slug}/`), диспатч событий, выбор `seo_builder` через DI-контейнер. Логика ресторана из него убрана.
- **`src/Service/DataLoaderService.php`** — добавлены generic `loadEntitySlugs`/`loadEntity` на основе `data_dir`/`item_key`/`nav_slug` коллекции. Старые `loadRestaurant*` удалены.
- **`src/Action/ApiSendAction.php`** — переехал на kumho-вариант с `MailService->sendFormSubmission()`. Валидация: `email + name + policy` (italy-схема, не kumho `phone+email+policy`).
- **`config/settings.php`** — секции `collections.restaurants` (data_dir, item_key, nav_slug, list_page_id, template, extras_key, og_type, `entity_url_pattern: /restaurants/{slug}/`, `seo_builder: RestaurantSeoBuilder::class`, `prod_base_url`, `site_name`, `fallback_og_image`, `list_title`) и `mail` (DSN/to/from/from_name/subject_prefix через ENV).
- **`config/container.php`** — `EventDispatcherInterface` через `League\Event\EventDispatcher`; `MailerInterface` (DSN из settings); `MailService`; `RestaurantSeoBuilder`; `RequestDurationMiddleware` (autowire); `PageAction` принимает диспетчер и контейнер. Logger переключён со `StreamHandler` на `RotatingFileHandler` + `JsonFormatter` (ротация 14 дней).
- **`config/middleware.php`** — `RequestDurationMiddleware` первым в стеке.
- **`composer.json`** — добавлены `league/event ^3.0`, `psr/event-dispatcher ^1.0`, `symfony/mailer ^8.0`.

### Архитектурные решения

1. **События readonly + SEO через service, а не listener.** Лучшее отделение от kumho — в league/event события неизменяемые. Поэтому SEO для сущностей строится через `SeoBuilderInterface` (FQCN в `collections.{key}.seo_builder`), который PageAction достаёт из контейнера и вызывает. События (PageLoaded/EntityResolved/SeoBuilt) остаются для observability/логов и расширений в будущем.
2. **Generic-коллекции.** Все entity-страницы (рестораны, потенциальные new/projects/etc.) ходят через одну ветку кода. Конфиг коллекции описывает всё нужное: где лежит JSON, какой ключ внутри валидирует существование, как формируется URL, какой builder для SEO.
3. **Photoroom не переносится** — kumho-специфичная интеграция, в italy не нужна.

## Деплой

`composer install --no-dev --optimize-autoloader` обязателен (изменился список зависимостей). Ветка `refactor/backend-slim-events` развёрнута на staging командой:

```bash
ssh root@ismart.pro 'cd /var/www/ismart/italycommunity.ru.ismart.pro \
  && git fetch origin refactor/backend-slim-events \
  && git checkout refactor/backend-slim-events \
  && git pull --ff-only origin refactor/backend-slim-events \
  && composer install --no-dev --no-interaction --optimize-autoloader \
  && rm -rf cache/twig \
  && chown -R promo:www-data .'
```

Smoke-тесты на staging (статус + наличие `X-Response-Time`):

| Маршрут                     | Статус | Заметка                                           |
| --------------------------- | ------ | ------------------------------------------------- |
| `/`                         | 200    |                                                   |
| `/restaurants/`             | 200    |                                                   |
| `/restaurants/bist/`        | 200    | og:image=`covers/raw/1.jpg`, X-Response-Time=64ms |
| `/restaurants/joli-moscow/` | 200    | og:image fallback (covers пуст)                   |
| `/contacts/`                | 200    |                                                   |
| `/sitemap.xml`              | 200    |                                                   |
| `/no-such-page/`            | 404    |                                                   |

phpstan чист.

## Открытые вопросы

1. **Mail DSN на проде/staging.** В `settings.mail.dsn` дефолт `sendmail://default`, но реальный SMTP не сконфигурирован (`MAIL_TO`/`MAIL_FROM` не заданы). Форма callback примет данные и вернёт 200, но `MailService` залогирует warning и письмо не уйдёт. Нужно: задать ENV `MAILER_DSN`, `MAIL_TO`, `MAIL_FROM` в `.env` на сервере (или nginx fastcgi_param).
2. **Кейс B и `routeParams.length > 1`.** Сейчас при `/restaurants/bist/extras/` (две и более вложенные сегменты после list-роута) PageAction молча отдаёт список ресторанов с 200 — потому что `count($routeParams) === 1` не выполнено и цикл break'ится. Поведение унаследовано от kumho. Стоит добавить явный 404 для `count > 1`.
3. **Кейс A vs route_map.** Если у ресторана slug совпадёт со slug'ом в `route_map` (например `restaurants` ↔ ресторан с slug `restaurants`), приоритет получит `route_map` и ресторан не отдастся. Сейчас риска нет — `route_map` маленький — но нужно держать в голове при добавлении.
4. **`prod_base_url` хардкодом в settings.** RestaurantSeoBuilder при `og:image` использует `https://italycommunity.ru` — корректно для прода, но значит og картинки на staging указывают на прод-урл (это сознательное решение для shareable-снэпов). Если когда-то прод-домен поменяется, придётся править settings.
5. **404 без entity-fallback'а.** При несуществующем pageData PageAction сначала проверяет direct-slug-коллекции (Кейс A), потом 404. Если в italy появится коллекция с короткими slug'ами без префикса — она перехватит запрос. Сейчас единственная коллекция `restaurants` с префиксом, поэтому риска нет. Документировать в момент добавления второй коллекции.
6. **Listener'ы пока никто не подписывает.** `EventDispatcher` зарегистрирован, события диспатчатся, но ни один listener не зарегистрирован. Архитектура готова, но без потребителей. Первый logical use case — `RequestLifecycleListener` для метрик/трейсинга или `SeoAuditListener` для логирования рендеренных страниц без og:image.
7. **`buildEntityBreadcrumb` в `PageAction`.** Generic-имплементация лежит в action'е, но сильно зависит от collection-конфига (nav_slug, item_key, entity_url_pattern, list_title). При росте проекта стоит вынести в отдельный `BreadcrumbBuilder` сервис (и breadcrumb тоже сделать per-collection, как seo_builder).
8. **`composer install` на сервере выполнен с `--no-dev`** — это снимает phpstan/phpunit/php-cs-fixer. Если деплоить через `npm run check`, оно упадёт на сервере. Деплой-команда (см. `docs/architecture/...` или память) уже корректно избегает запуска dev-проверок.
9. **PageAction получает `ContainerInterface`.** Это service locator (антипаттерн). Альтернатива: registry интерфейс `SeoBuilderRegistry` с `get(string $type): ?SeoBuilderInterface`, который собирается компилером DI. Сделано просто — оптимизация на следующий проход.

## Откат

```bash
ssh root@ismart.pro 'cd /var/www/ismart/italycommunity.ru.ismart.pro \
  && git checkout main && git pull --ff-only origin main \
  && composer install --no-dev --no-interaction --optimize-autoloader \
  && rm -rf cache/twig && chown -R promo:www-data .'
```

`composer install` приведёт зависимости к состоянию `main` (без league/event и symfony/mailer).
