<?php
namespace App\Utils;

class ErrorHandler
{
    /** @var array|null */
    private static ?array $config = null;

    /**
     * Проброс конфига из Application/Bootstrap (предпочтительно вместо глобальной переменной)
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }
    public static function handleThrowable(\Throwable $e): void
    {
        error_log('[Unhandled] ' . $e::class . ': ' . $e->getMessage() . " in {$e->getFile()}:{$e->getLine()}");
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Internal Server Error</h1>';
        if (self::isDebug()) {
            echo '<pre>' . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
        exit;
    }

    public static function isDebug(): bool
    {
        // Источники: ENV APP_DEBUG и глобальный конфиг (если проброшен)
        $env = getenv('APP_DEBUG');
        if ($env === '1' || $env === 'true') {
            return true;
        }
        if (is_array(self::$config) && isset(self::$config['settings']['debug'])) {
            return (bool)self::$config['settings']['debug'];
        }
        return false;
    }
}

