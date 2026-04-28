const fs = require('fs');
const path = require('path');
const nodeCrypto = require('node:crypto');
const postcss = require('postcss');
const postcssConfig = require('../../postcss.config');

// Пути к файлам
const cssSrcDir = path.resolve(__dirname, '../../assets/css');
const inputFile = path.resolve(cssSrcDir, 'main.css');
const outputDir = path.resolve(__dirname, '../../assets/css/build');
const manifestPath = path.resolve(outputDir, 'css-manifest.json');
const inputHashPath = path.resolve(outputDir, '.input-hash');

// Рекурсивный сбор всех .css файлов в src
function collectCssFiles(dir, acc = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'build') continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      collectCssFiles(full, acc);
    } else if (entry.isFile() && entry.name.endsWith('.css')) {
      acc.push(full);
    }
  }
  return acc;
}

// Хеш входных файлов (mtime+size) — быстрая проверка изменений
function computeInputHash() {
  const files = collectCssFiles(cssSrcDir).sort();
  const fingerprint = files
    .map((f) => {
      const s = fs.statSync(f);
      return `${path.relative(cssSrcDir, f)}:${s.size}:${s.mtimeMs}`;
    })
    .join('\n');
  // Учитываем NODE_ENV — prod и dev дают разный output
  const env = process.env.NODE_ENV || 'development';
  return nodeCrypto.createHash('md5').update(`${env}\n${fingerprint}`).digest('hex');
}

// Создаем директорию, если не существует
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

// Получаем текущий активный файл из манифеста
function getCurrentActiveFile() {
  if (fs.existsSync(manifestPath)) {
    try {
      const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
      if (manifest && manifest['main.css']) {
        const filePath = manifest['main.css'];
        return path.basename(filePath);
      }
    } catch (error) {
      console.log('Ошибка при чтении текущего манифеста:', error.message);
    }
  }
  return null;
}

// Удаляем старые файлы CSS, кроме текущего активного
function cleanOldFiles(currentFile, newFile) {
  // Не удаляем текущий активный файл, пока не убедимся, что новый файл доступен
  const cssFiles = fs
    .readdirSync(outputDir)
    .filter((file) => /^main\.[a-f0-9]+\.css$/.test(file))
    .filter((file) => file !== currentFile && file !== newFile)
    .map((file) => path.join(outputDir, file));

  cssFiles.forEach((file) => {
    fs.unlinkSync(file);
    console.log(`Удален старый CSS файл: ${path.basename(file)}`);
  });
}

// Функция для создания хеша содержимого
function generateHash(content) {
  return nodeCrypto.createHash('md5').update(content).digest('hex').slice(0, 8);
}

// Функция для обработки CSS с PostCSS
async function processCss() {
  try {
    // Получаем текущий активный файл прежде чем удалить что-либо
    const currentActiveFile = getCurrentActiveFile();

    // Short-circuit: если входные файлы не менялись и манифест/файл существуют — пропускаем PostCSS
    const inputHash = computeInputHash();
    if (
      currentActiveFile &&
      fs.existsSync(path.join(outputDir, currentActiveFile)) &&
      fs.existsSync(inputHashPath) &&
      fs.readFileSync(inputHashPath, 'utf8') === inputHash
    ) {
      console.log(`CSS не менялся, пропускаем сборку (${currentActiveFile})`);
      return;
    }

    // Читаем исходный CSS файл
    const css = fs.readFileSync(inputFile, 'utf8');

    // Создаем экземпляр PostCSS с плагинами из конфига
    const processor = postcss(postcssConfig.plugins);

    // Обрабатываем CSS (source maps только в development)
    const isProduction = process.env.NODE_ENV === 'production';
    const result = await processor.process(css, {
      from: inputFile,
      to: path.join(outputDir, 'main.css'),
      map: isProduction ? false : { inline: true },
    });

    let outputCss = result.css;

    // В production минифицируем через lightningcss — на порядок быстрее cssnano
    if (isProduction) {
      const { transform: lightningTransform } = require('lightningcss');
      const minified = lightningTransform({
        filename: 'main.css',
        code: Buffer.from(outputCss),
        minify: true,
        sourceMap: false,
      });
      outputCss = minified.code.toString('utf8');
    }

    // Генерируем хеш обработанного CSS
    const hash = generateHash(outputCss);

    // Формируем имя файла с хешем
    const outputFileName = `main.${hash}.css`;
    const outputFilePath = path.join(outputDir, outputFileName);

    // Проверяем, не существует ли уже файл с таким хешем (кэширование)
    if (fs.existsSync(outputFilePath)) {
      console.log(`Файл ${outputFileName} уже существует, используем его`);
    } else {
      // Записываем обработанный CSS в файл
      fs.writeFileSync(outputFilePath, outputCss);
      console.log(`CSS обработан и сохранен: ${outputFilePath}`);
    }

    // Проверяем, что файл доступен для чтения
    if (!fs.existsSync(outputFilePath)) {
      throw new Error(`Не удалось найти созданный файл: ${outputFileName}`);
    }

    // Создаем манифест
    const manifest = {
      'main.css': `assets/css/build/${outputFileName}`,
    };

    // Записываем манифест в файл
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    console.log(`Создан манифест: ${manifestPath}`);
    console.log('Содержимое манифеста:', manifest);

    // Сохраняем хеш входов для следующего запуска
    fs.writeFileSync(inputHashPath, inputHash);

    // Теперь безопасно удаляем старые файлы, но только после успешного обновления манифеста
    cleanOldFiles(currentActiveFile, outputFileName);
  } catch (error) {
    console.error('Ошибка при обработке CSS:', error);
    process.exit(1);
  }
}

// Запускаем обработку CSS
processCss();
