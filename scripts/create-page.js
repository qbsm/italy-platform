const fs = require('fs');
const path = require('path');

// Получаем аргументы командной строки
const args = process.argv.slice(2);
const page = args[0];
const baseTemplate = args[1] || 'layout.twig';

if (!page) {
  console.error('Укажите имя страницы в качестве аргумента');
  process.exit(1);
}

const twigPath = path.join('templates', 'pages', `${page}.twig`);
const content = `{% extends '${baseTemplate}' %}

{% block content %}
  {# Используем глобальную переменную sections #}
  {% if sections is defined %}
    {% for section in sections %}
      {# Включаем шаблон, передавая данные из section.data #}
      {% include 'sections/' ~ section.name ~ '.twig' ignore missing with {'data': section.data} %}
    {% endfor %}
  {% else %}
    <h1>Данные для страницы не найдены или неверный формат секций</h1>
  {% endif %}
{% endblock %}`;

let isNewFile = false;

if (!fs.existsSync(twigPath)) {
  try {
    fs.writeFileSync(twigPath, content);
    console.log(`Создан файл ${twigPath}`);
    isNewFile = true;
  } catch (err) {
    console.error(`Ошибка при создании файла: ${err.message}`);
    process.exit(1);
  }
} else {
  console.log(`Файл ${twigPath} уже существует`);
}

// Если был создан новый файл, создаем JS и CSS файлы и добавляем импорты
if (isNewFile) {
  // Создаем JS файл
  const jsDir = path.join('assets', 'js', 'pages');
  const jsPath = path.join(jsDir, `${page}.js`);
  
  if (!fs.existsSync(jsDir)) {
    fs.mkdirSync(jsDir, { recursive: true });
  }
  
  const jsContent = `// JavaScript для страницы ${page}
document.addEventListener('DOMContentLoaded', function() {
  console.log('Страница ${page} загружена');
  // Код для страницы ${page}
});
`;

  try {
    fs.writeFileSync(jsPath, jsContent);
    console.log(`Создан файл ${jsPath}`);
    
    // Добавляем импорт в main.js
    const mainJsPath = path.join('assets', 'js', 'main.js');
    if (fs.existsSync(mainJsPath)) {
      let mainJsContent = fs.readFileSync(mainJsPath, 'utf8');
      if (!mainJsContent.includes(`./pages/${page}.js`)) {
        // Находим место, куда вставить импорт
        const importSection = '// --- Pages ---';
        if (mainJsContent.includes(importSection)) {
          mainJsContent = mainJsContent.replace(
            importSection,
            `${importSection}\nimport './pages/${page}.js';`
          );
          fs.writeFileSync(mainJsPath, mainJsContent);
          console.log(`Добавлен импорт в main.js для ${page}.js`);
        }
      }
    }
  } catch (err) {
    console.error(`Ошибка при создании JS файла: ${err.message}`);
  }
  
  // Создаем CSS файл
  const cssDir = path.join('assets', 'css', 'pages');
  const cssPath = path.join(cssDir, `${page}.css`);
  
  if (!fs.existsSync(cssDir)) {
    fs.mkdirSync(cssDir, { recursive: true });
  }
  
  const cssContent = `.${page} {
  /* Стили для ${page} */
}

.${page} .container {
  /* Стили для контейнера в ${page} */
}
`;

  try {
    fs.writeFileSync(cssPath, cssContent);
    console.log(`Создан файл ${cssPath}`);
    
    // Добавляем импорт в main.css
    const mainCssPath = path.join('assets', 'css', 'main.css');
    if (fs.existsSync(mainCssPath)) {
      const mainCssContent = fs.readFileSync(mainCssPath, 'utf8');
      if (!mainCssContent.includes(`pages/${page}.css`)) {
        fs.appendFileSync(mainCssPath, `@import \"pages/${page}.css\";\n`);
        console.log(`Добавлен импорт в main.css для ${page}.css`);
      }
    }
  } catch (err) {
    console.error(`Ошибка при создании CSS файла: ${err.message}`);
  }
  
  console.log('Файлы JS и CSS успешно созданы и подключены');
} 

// Создаем отдельный JSON файл для страницы в директории data/json/ru/pages/
const pagesDir = path.join('data', 'json', 'ru', 'pages');
const jsonPath = path.join(pagesDir, `${page}.json`);

// Проверяем существование директории
if (!fs.existsSync(pagesDir)) {
  fs.mkdirSync(pagesDir, { recursive: true });
  console.log(`Создана директория ${pagesDir}`);
}

// Проверяем, существует ли уже файл JSON для этой страницы
if (!fs.existsSync(jsonPath)) {
  try {
    // Обновленная структура JSON с вложенными секциями
    const pageJsonData = {
      "name": page,
      "sections": [ // Теперь массив объектов
        { "name": "header", "data": {} },
        { "name": "promo", "data": {} }, // Пример секции с пустыми данными
        { "name": "form-container", "data": {} },
        { "name": "footer", "data": {} }
      ]
    };
    
    // Записываем данные в файл
    fs.writeFileSync(jsonPath, JSON.stringify(pageJsonData, null, 2));
    console.log(`Создан файл JSON для страницы: ${jsonPath}`);
  } catch (err) {
    console.error(`Ошибка при создании файла JSON: ${err.message}`);
  }
} else {
  console.log(`Файл JSON для страницы ${page} уже существует: ${jsonPath}`);
}

// Создаем SEO файл для страницы в директории data/json/ru/seo/
const seoDir = path.join('data', 'json', 'ru', 'seo');
const seoPath = path.join(seoDir, `${page}.json`);

// Проверяем существование директории
if (!fs.existsSync(seoDir)) {
  fs.mkdirSync(seoDir, { recursive: true });
  console.log(`Создана директория ${seoDir}`);
}

// Проверяем, существует ли уже SEO файл для этой страницы
if (!fs.existsSync(seoPath)) {
  try {
    // Создаем базовый SEO файл с пустой структурой
    const seoData = {
      "name": page,
      "title": "",
      "meta": [
        {
          "name": "description",
          "content": ""
        },
        {
          "property": "og:url",
          "content": `/${page}/`
        },
        {
          "property": "og:type",
          "content": "website"
        },
        {
          "property": "og:title",
          "content": ""
        },
        {
          "property": "og:description",
          "content": ""
        },
        {
          "property": "og:site_name",
          "content": ""
        },
        {
          "property": "og:image",
          "content": ""
        }
      ]
    };
    
    // Записываем SEO данные в файл
    fs.writeFileSync(seoPath, JSON.stringify(seoData, null, 2));
    console.log(`Создан SEO файл для страницы: ${seoPath}`);
  } catch (err) {
    console.error(`Ошибка при создании SEO файла: ${err.message}`);
  }
} else {
  console.log(`SEO файл для страницы ${page} уже существует: ${seoPath}`);
} 