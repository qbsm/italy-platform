# Быстрая справка по изображениям

## Добавление нового проекта

### 1. Структура папок
```
data/img/portfolio/{project-slug}/
  ├── h/raw/     # Горизонтальные оригиналы
  └── v/raw/     # Вертикальные оригиналы (опционально)
```

### 2. Генерация версий
```bash
node scripts/resize_portfolio_images.js --project {project-slug}
```

### 3. Обновление JSON
```bash
python3 scripts/update_portfolio_json.py
```

## Использование компонента Picture

### В шаблонах

```twig
{# Cover изображение (половина экрана) #}
{% include 'components/picture.twig' with {
  image: item,
  alt: item.alt|default('Image'),
  class: 'gallery__cover img-responsive',
  sizes_preset: 'half',
  loading: 'lazy'
} %}

{# Thumbnail (четверть экрана) #}
{% include 'components/picture.twig' with {
  image: item,
  alt: item.alt|default('Thumbnail'),
  class: 'gallery__thumb img-responsive',
  sizes_preset: 'quarter',
  loading: 'lazy'
} %}

{# Вертикальное cover (мобильные) #}
{% include 'components/picture.twig' with {
  image: item,
  alt: item.alt|default('Mobile image'),
  class: 'gallery__cover img-responsive',
  sizes_preset: 'half-mobile',
  loading: 'lazy'
} %}

{# Кастомный sizes #}
{% include 'components/picture.twig' with {
  image: item,
  alt: item.alt,
  sizes: '(max-width: 768px) 100vw, 50vw'
} %}
```

## Пресеты sizes

| Пресет | Использование | Загружаемые версии |
|--------|---------------|-------------------|
| `full` | Полноэкранные слайдеры | 1280px (1x), raw (2x) |
| `half` | Cover галереи (horizontal) | 640px (1x), 1280px (2x) |
| `half-mobile` | Cover галереи (vertical) | 640px (≤639px 2x), 1280px (>639px 2x) |
| `quarter` | Thumbnails | 640px (1x и 2x) |

## Команды

```bash
# Ресайз всех проектов
node scripts/resize_portfolio_images.js

# Ресайз одного проекта
node scripts/resize_portfolio_images.js --project gk1

# Принудительная переконвертация
node scripts/resize_portfolio_images.js --project gk1 --force

# Обновление JSON
python3 scripts/update_portfolio_json.py
```

## Проверка оптимизации

1. Открыть DevTools → Network → Img
2. Обновить страницу
3. Проверить размеры загружаемых файлов:
   - Thumbnails: `640/` версии (~50-150 KB)
   - Covers: `1280/` версии (~150-400 KB)
   - Большие экраны: `raw/` версии (~300-800 KB)

## Типичные sizes

```twig
{# Полная ширина на всех устройствах #}
sizes: '100vw'

{# Половина на десктопе, полная на мобильных #}
sizes: '(max-width: 991px) 100vw, 50vw'

{# Фиксированные размеры #}
sizes: '(max-width: 639px) 320px, 640px'

{# Четверть экрана на мобильных #}
sizes: '25vw'
```

## Исключенные проекты

В `scripts/resize_portfolio_images.js`:
```javascript
const projectsToExclude = ['1919', 'okla'];
```

Эти проекты не обрабатываются автоматически.








