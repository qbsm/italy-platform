# Цели по метрикам производительности

Целевые значения для Lighthouse / Web Vitals:

| Метрика | Цель | Примечание |
|---------|------|------------|
| **FCP** (First Contentful Paint) | ≤ 1,8 с | Первая отрисовка контента |
| **LCP** (Largest Contentful Paint) | ≤ 2,5 с | Загрузка основного контента (часто изображение) |
| **CLS** (Cumulative Layout Shift) | < 0,1 | Стабильность верстки (width/height у img, резервирование места) |
| **INP** (Interaction to Next Paint) | ≤ 200 мс | Отзывчивость на действия пользователя |

Проверка: Lighthouse в Chrome DevTools (режим Navigation), вкладка Performance. При необходимости — мониторинг в production (например, RUM).
