# Инструкция по созданию JSON для ресторана

## Расположение файлов

- JSON данных: `data/json/ru/restaurants/{slug}.json`
- Изображения: `data/img/restaurants/{slug}/`
  - `icons/icon-color-1.svg` — светлая иконка
  - `icons/icon-color-2.svg` — тёмная иконка
  - `logos/logo-color-1.svg` — светлый логотип
  - `logos/logo-color-2.svg` — тёмный логотип
  - `covers/raw/` — фотографии ресторана (jpg/png), перенесены из старого проекта

## Эталонная структура JSON

```json
{
  "slug": "restaurant-slug",
  "visible": true,
  "subtitle": "Краткое описание в одно предложение.",
  "restaurant": {
    "@type": "Restaurant",
    "name": "Название ресторана",
    "telephone": {
      "title": "+7 (XXX) XXX-XX-XX",
      "href": "tel:+7XXXXXXXXXX"
    },
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Улица, дом, дополнение",
      "addressLocality": "Город",
      "addressRegion": "Город или область",
      "addressCountry": "RU"
    },
    "openingHours": [
      { "days": "Пн-Чт", "hours": "09:00–23:00" },
      { "days": "Пт-Сб", "hours": "09:00–00:00" },
      { "days": "Вс", "hours": "10:00–23:00" }
    ],
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": "59.966069",
      "longitude": "30.299584"
    },
    "hasMap": "https://yandex.ru/maps/-/XXXXXX",
    "servesCuisine": [
      "Итальянская кухня"
    ],
    "priceRange": "₽₽",
    "menuLink": "https://..."
  },
  "desc": "Развёрнутое описание ресторана. Без HTML-тегов.",
  "loyalty": true,
  "tags": [
    "тег1",
    "тег2"
  ],
  "covers": [
    {
      "src": "data/img/restaurants/{slug}/covers/raw/1.jpg",
      "alt": "Название ресторана"
    },
    {
      "src": "data/img/restaurants/{slug}/covers/raw/2.jpg",
      "alt": "Название ресторана"
    }
  ],
  "logos": [
    {
      "src": "data/img/restaurants/{slug}/logos/logo-color-1.svg",
      "alt": "Название белый лого"
    },
    {
      "src": "data/img/restaurants/{slug}/logos/logo-color-2.svg",
      "alt": "Название черный лого"
    }
  ],
  "icons": [
    {
      "src": "data/img/restaurants/{slug}/icons/icon-color-1.svg",
      "alt": "Название белая иконка"
    },
    {
      "src": "data/img/restaurants/{slug}/icons/icon-color-2.svg",
      "alt": "Название черная иконка"
    }
  ]
}
```

## Правила адаптации из старого формата (italyco.rest)

При переносе данных из `/Users/danich/Sites/italyco.rest/project/data/content/index.json` соблюдай следующие правила:

### Маппинг полей

| Старый формат | Новый формат |
|---|---|
| `slug` | `slug` (без изменений) |
| `visible` | `visible` (без изменений) |
| `title` | НЕ переносить (название берётся из `restaurant.name`) |
| `subtitle` | `subtitle` (без изменений) |
| `phone.title` | `restaurant.telephone.title` |
| `phone.code` | `restaurant.telephone.href` (добавить префикс `tel:`) |
| `address` | `restaurant.address.streetAddress` (убрать HTML-теги `<nobr>`) |
| `city` | `restaurant.address.addressLocality` и `restaurant.address.addressRegion` |
| `worktime` | `restaurant.openingHours` (парсить в массив объектов) |
| `quote` | `desc` (убрать HTML-теги `<br>`) |
| `menuLink` | `restaurant.menuLink` |
| `showLoyalty` | `loyalty` |
| `conception` | НЕ переносить |
| `franchise` | НЕ переносить |
| `bookingPoint` | НЕ переносить |
| `description` | НЕ переносить (слишком длинный, хранить отдельно при необходимости) |
| `images` | НЕ переносить (пути изображений отличаются) |
| `menuImages` | НЕ переносить |
| `recipients` | НЕ переносить |
| `social` | НЕ переносить |

### Правила преобразования

1. **slug** — использовать как есть, он же определяет путь к изображениям: `data/img/restaurants/{slug}/`

2. **telephone.href** — из `phone.code` (например `+78129006333`) сделать `tel:+78129006333`

3. **address.streetAddress** — убрать все HTML-теги (`<nobr>`, `<br>` и т.д.), оставить чистый текст

4. **openingHours** — из HTML-строки с классами `restinfo__worktime-days` и `restinfo__worktime-hours` извлечь дни и часы, разбить на массив объектов:
   - `"Ежедневно 09:00 — 23:00"` → `[{ "days": "Пн-Вс", "hours": "09:00–23:00" }]`
   - `"Вс-Чт 09:00-23:00 Пт-Сб 09:00-00:00"` → `[{ "days": "Вс-Чт", "hours": "09:00–23:00" }, { "days": "Пт-Сб", "hours": "09:00–00:00" }]`
   - `"Круглосуточно"` → `[{ "days": "Пн-Вс", "hours": "Круглосуточно" }]`
   - Тире в часах: использовать `–` (длинное тире), не `-`

5. **desc** — брать из поля `quote`, убрать `<br>` и прочие HTML-теги, оставить чистый текст

6. **geo** — координаты в старом формате отсутствуют, заполнять вручную или искать по адресу

7. **hasMap** — короткая ссылка Яндекс Карт, заполнять вручную

8. **servesCuisine** — определять по описанию и концепции:
   - Italy, Italiani, Salone → `["Итальянская кухня"]`
   - HITCH → `["Гриль", "Мясная кухня"]`
   - Goose Goose → `["Итальянская кухня", "Пьемонтская кухня"]`
   - Bist → `["Итальянская кухня", "Мясные специалитеты"]`
   - JOLI → `["Французская кухня", "Итальянская кухня"]`
   - Ателье, Juan → `["Испанская кухня", "Мексиканская кухня"]`
   - Jam Café → `["Французская кухня"]`
   - Бар «Медведь» → `["Коктейльный бар"]`
   - Oasis Gourmet → `["Европейская кухня", "Азиатская кухня"]`

9. **priceRange** — `"₽₽"` для большинства, `"₽₽₽"` для Goose Goose

10. **tags** — формировать по описанию ресторана, типичные теги:
    - `"завтраки"` — если в описании упоминаются завтраки
    - `"рады детям"` — если есть детская комната
    - `"итальянская кухня"`, `"французская кухня"` и т.д.
    - `"с красивым видом"` — если упоминается вид/панорама
    - `"на каждый день"` — семейные рестораны
    - `"можно с животными"` — если указано
    - `"авторские коктейли"` — если акцент на бар
    - `"летняя терраса"` — если есть

11. **covers** — фотографии ресторана лежат в `data/img/restaurants/{slug}/covers/raw/`. Перечислить все реально существующие файлы. Путь строится как `data/img/restaurants/{slug}/covers/raw/{filename}`. `alt` — название ресторана.

12. **logos, icons** — пути всегда строятся по шаблону `data/img/restaurants/{slug}/...`, файлы должны существовать

## Маппинг директорий изображений (старый → новый slug)

| Старая директория (italyco.rest) | Новый slug (italy-platform) |
|---|---|
| `atelier` | `atelier` |
| `bear` | `bear` |
| `bruxx-spb` | `bist` |
| `goose-goose` | `goose-goose` |
| `hitch-moskovskiy` | `hitch` |
| `italiani-nevskiy` | `italiani` |
| `italy-bolshaya-morskaya` | `italy-bolshaya-morskaya` |
| `italy-bolshoy` | `italy-bolshoy` |
| `italy-moskovskiy` | `italy-moskovskiy` |
| `italy-vilenskiy` | `italy-vilenskiy` |
| `jam-cafe` | `jam` |
| `joli` | `joli-grand-bistrot` |
| `juan` | `juan-cantina-espanola` |
| `oasis-gourmet` | `oasis-gourmet` |
| `salone` | `salone-pasta-bar` |
| `salone-moscow` | `salone-pasta-bar-moscow` |

Изображения хранятся в `covers/raw/` — это исходные файлы, перенесённые из старого проекта. При заполнении `covers` в JSON перечислять реально существующие файлы из этой директории.

## После создания JSON

Не забудь добавить ресторан в `data/json/ru/pages/restaurants-list.json` в секцию `restaurants.data.items`.
