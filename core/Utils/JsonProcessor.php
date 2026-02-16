<?php
namespace App\Utils;

class JsonProcessor
{
    /**
     * Рекурсивно проходит по структуре данных и приводит пути вида '/data/' и 'data/' к абсолютным URL на основе $baseUrl.
     * Мутирует данные по ссылке (совместимо с текущей функцией из index.php).
     *
     * @param mixed  $data
     * @param string $baseUrl
     */
    public static function processJsonPaths(&$data, string $baseUrl): void
    {
        $baseUrl = rtrim($baseUrl, '/') . '/';
        $baseUrlData = $baseUrl . 'data/';

        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => &$value) {
                if (is_array($value) || is_object($value)) {
                    self::processJsonPaths($value, $baseUrl);
                } elseif (is_string($value)) {
                    if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
                        continue;
                    }

                    if (strpos($value, '/data/') === 0) {
                        $value = $baseUrlData . ltrim(substr($value, strlen('/data/')), '/');
                    } elseif (strpos($value, 'data/') === 0) {
                        $value = $baseUrl . $value;
                    }
                }
            }
            unset($value);
        }
    }

    /**
     * Загружает JSON-файл в массив с безопасной обработкой ошибок.
     * @return array|null
     */
    public static function loadJsonFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }

    /**
     * Простая валидация JSON-данных (хук для расширения в будущем).
     */
    public static function validateJsonData($data, string $path): bool
    {
        return is_array($data) || is_object($data);
    }
}

