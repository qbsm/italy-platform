<?php
namespace App\Config;

final class ConfigManager
{
    public static function getPaths(array $config): array
    {
        return $config['dirs'] ?? [];
    }

    public static function getUrls(array $config): array
    {
        return $config['urls'] ?? [];
    }

    public static function getSettings(array $config): array
    {
        return $config['settings'] ?? [];
    }

    public static function getJsonPaths(array $config): array
    {
        return $config['json'] ?? [];
    }

    /**
     * Строит конфигурацию проекта
     */
    public static function build(string $projectRoot): array
    {
        $baseDir = realpath($projectRoot) ?: $projectRoot;

        // Подгружаем .env (если есть)
        self::loadDotEnv($baseDir);

        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '';
        $basePath = rtrim($scriptDir, '/');
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . $host . $basePath . '/';

        $settings = self::defaultSettings($baseDir);

        return [
            'base_dir' => $baseDir,
            'base_url' => $baseUrl,
            'dirs' => [
                'data' => $baseDir . '/data',
                'json' => $baseDir . '/data/json',
                'images' => $baseDir . '/data/img',
                'templates' => $baseDir . '/templates',
                'assets' => $baseDir . '/assets',
                'cache' => $baseDir . '/cache',
                'src' => $baseDir . '/core',
                'config' => $baseDir . '/core/Config',
                'vendor' => $baseDir . '/vendor',
                'logs' => $baseDir . '/logs',
            ],
            'urls' => [
                'base' => $baseUrl,
                'assets' => $baseUrl . 'assets/',
                'data' => $baseUrl . 'data/',
                'images' => $baseUrl . 'data/img/',
                'css' => $baseUrl . 'assets/css/build/',
                'js' => $baseUrl . 'assets/js/build/',
            ],
            'json' => [
                'global' => $baseDir . '/data/json/global.json',
                'pages_dir' => $baseDir . '/data/json/{lang}/pages',
            ],
            'settings' => $settings,
        ];
    }

    private static function defaultSettings(string $baseDir): array
    {
        $get = function (string $key, $default = null) {
            $val = getenv($key);
            return ($val !== false && $val !== null && $val !== '') ? $val : $default;
        };

        return [
            'default_lang' => (string)$get('APP_DEFAULT_LANG', 'ru'),
            'available_langs' => ['ru'],
            'twig_cache' => false,
            'twig_cache_dir' => $baseDir . '/cache',
            'debug' => (string)$get('APP_DEBUG', '1') === '1',
        ];
    }

    /**
     * Простейший загрузчик .env
     */
    private static function loadDotEnv(string $baseDir): void
    {
        $path = rtrim($baseDir, '/') . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            $value = trim(substr($trimmed, $pos + 1));
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (str_ends_with($value, $quote)) {
                    $value = substr($value, 1, -1);
                }
            }
            if ($key === '') {
                continue;
            }
            $current = getenv($key);
            if ($current === false || $current === null || $current === '') {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
