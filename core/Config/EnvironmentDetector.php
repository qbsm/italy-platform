<?php
namespace App\Config;

class EnvironmentDetector
{
    public function detectBaseUrl(): string
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/';
        $basePath = rtrim($scriptDir, '/');
        return $https . $host . $basePath . '/';
    }

    public function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod') === 'prod';
    }

    public function isDevelopment(): bool
    {
        return !$this->isProduction();
    }
}

