# Инструкция по созданию SEO-метаданных для страниц

## Расположение файлов
SEO файлы находятся в директории: `data/json/ru/seo/`

Структура папок:
- `data/json/ru/seo/` - основные страницы сайта
- `data/json/ru/seo/blog/` - статьи блога

## Типы SEO файлов

### 1. SEO для обычных страниц
Файлы создаются с именем, соответствующим slug страницы, например:
- `avtorskiy-nadzor.json`
- `landshaftnoe-proektirovanie.json`
- `team.json`
- `contacts.json`

### 2. SEO для статей блога
Файлы создаются в подпапке `blog/` с именем статьи:
- `blog/avtorskiy-nadzor-landshaft.json`
- `blog/generalnyy-plan-uchastka.json`

## Структура SEO файла

### Базовая структура
```json
{
  "name": "page-slug",
  "title": "SEO заголовок страницы",
  "h1": "H1 заголовок страницы",
  "meta": [
    {
      "name": "description",
      "content": "SEO описание страницы"
    },
    {
      "property": "og:url",
      "content": "https://example.com/page-url/"
    },
    {
      "property": "og:type",
      "content": "website"
    },
    {
      "property": "og:title",
      "content": "SEO заголовок страницы"
    },
    {
      "property": "og:description",
      "content": "SEO описание страницы"
    },
    {
      "property": "og:site_name",
      "content": "Студия"
    },
    {
      "property": "og:image",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    },
    {
      "property": "og:image:secure_url",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    }
  ]
}
```

### Описание полей

#### Основные поля
- **name** - идентификатор страницы, должен совпадать со slug страницы
- **title** - SEO заголовок, отображается в поисковой выдаче и во вкладке браузера
- **h1** - заголовок H1 на странице, обычно совпадает с основным заголовком из JSON страницы
- **meta** - массив мета-тегов для страницы

#### Обязательные мета-теги
- **description** - краткое описание страницы для поисковых систем (до 160 символов)
- **og:url** - полный URL страницы
- **og:type** - тип контента (`website` для страниц, `article` для статей)
- **og:title** - заголовок для социальных сетей
- **og:description** - описание для социальных сетей
- **og:site_name** - название сайта (всегда "Студия")
- **og:image** - изображение для социальных сетей
- **og:image:secure_url** - защищённый URL изображения

## Примеры для разных типов страниц

### 1. Обычная страница услуг
```json
{
  "name": "avtorskiy-nadzor",
  "title": "Авторский надзор за реализацией проекта | Студия",
  "h1": "Авторский надзор",
  "meta": [
    {
      "name": "description",
      "content": "Профессиональный авторский надзор за реализацией ландшафтного проекта. Сохраняем концепцию и качество от идеи до финальной посадки."
    },
    {
      "property": "og:url",
      "content": "https://example.com/avtorskiy-nadzor/"
    },
    {
      "property": "og:type",
      "content": "website"
    },
    {
      "property": "og:title",
      "content": "Авторский надзор за реализацией проекта | Company"
    },
    {
      "property": "og:description",
      "content": "Профессиональный авторский надзор за реализацией ландшафтного проекта. Сохраняем концепцию и качество от идеи до финальной посадки."
    },
    {
      "property": "og:site_name",
      "content": "Студия"
    },
    {
      "property": "og:image",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    },
    {
      "property": "og:image:secure_url",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    }
  ]
}
```

### 2. Статья блога
```json
{
  "name": "blog/avtorskiy-nadzor-landshaft",
  "title": "Авторский надзор в ландшафтном дизайне: этапы и принципы | Company",
  "h1": "Авторский надзор в ландшафтном дизайне",
  "meta": [
    {
      "name": "description",
      "content": "Подробное руководство по авторскому надзору в ландшафтном дизайне. Этапы работы, принципы сопровождения и контроля качества."
    },
    {
      "property": "og:url",
      "content": "https://example.com/blog/avtorskiy-nadzor-landshaft/"
    },
    {
      "property": "og:type",
      "content": "article"
    },
    {
      "property": "og:title",
      "content": "Авторский надзор в ландшафтном дизайне | Company"
    },
    {
      "property": "og:description",
      "content": "Подробное руководство по авторскому надзору в ландшафтном дизайне. Этапы работы, принципы сопровождения и контроля качества."
    },
    {
      "property": "og:site_name",
      "content": "Студия"
    },
    {
      "property": "og:image",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    },
    {
      "property": "og:image:secure_url",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    }
  ]
}
```

### 3. Каталожная страница
```json
{
  "name": "chastnye-sady",
  "title": "Частные сады - портфолио проектов ландшафтного дизайна | Company",
  "h1": "Частные сады",
  "meta": [
    {
      "name": "description",
      "content": "Портфолио частных садов студии Company. Скандинавские сады, авторские проекты ландшафтного дизайна для загородных домов."
    },
    {
      "property": "og:url",
      "content": "https://example.com/chastnye-sady/"
    },
    {
      "property": "og:type",
      "content": "website"
    },
    {
      "property": "og:title",
      "content": "Частные сады - портфолио проектов | Company"
    },
    {
      "property": "og:description",
      "content": "Портфолио частных садов студии Company. Скандинавские сады, авторские проекты ландшафтного дизайна для загородных домов."
    },
    {
      "property": "og:site_name",
      "content": "Студия"
    },
    {
      "property": "og:image",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    },
    {
      "property": "og:image:secure_url",
      "content": "https://example.com/data/img/seo/og.webp?v=1"
    }
  ]
}
```

## Правила заполнения

### SEO заголовки (title)
- Длина: 50-60 символов
- Включать ключевые слова
- Заканчивать названием студии: "| Company" или "| Студия"
- Быть уникальными для каждой страницы

### SEO описания (description)
- Длина: 120-160 символов
- Краткое и понятное описание содержимого страницы
- Включать основные ключевые слова
- Призывать к действию (где уместно)

### URL структуры
- **Главная**: `https://example.com/`
- **Услуги**: `https://example.com/service-name/`
- **Блог**: `https://example.com/blog/article-name/`
- **Каталоги**: `https://example.com/category-name/`

### Open Graph изображения
- **По умолчанию**: `https://example.com/data/img/seo/og.webp?v=1`
- **Для статей блога**: изображение обложки статьи
- Размер: желательно 1200x630px
- Формат: WebP или JPG

### Специальные правила

#### Для блога
- **name** должен быть в формате `"blog/article-slug"`
- **og:type** всегда `"article"`
- **h1** должен совпадать с заголовком статьи

#### Для услуг
- **og:type** всегда `"website"`
- **description** должно описывать суть услуги

## Соответствие с контентом

### Связь с JSON страницы
- **h1** из SEO должен совпадать с `heading.title` из promo секции страницы
- **og:image** для портфолио должен совпадать с `cover.src` из JSON проекта
- **name** должен совпадать с `name` или `slug` страницы

### Проверка качества
- SEO заголовки не должны дублироваться
- Описания должны быть уникальными
- URL должны быть корректными и доступными
- Изображения должны существовать по указанным путям

## Технические требования
- Валидный JSON формат
- Кодировка UTF-8
- Корректное экранирование кавычек
- Обязательная проверка синтаксиса перед сохранением
- Проверка доступности изображений
- Соответствие длины title и description рекомендациям 