<?php
namespace App\Routing;

class UrlParser
{
    /**
     * Вычисляет относительный путь, убирая basePath из начала requestPath
     */
    public function parseRequestPath(string $requestPath, string $basePath): string
    {
        $basePath = rtrim($basePath, '/') . '/';
        if (strpos($requestPath, $basePath) === 0) {
            return ltrim(substr($requestPath, strlen($basePath)), '/');
        }
        return ltrim($requestPath, '/');
    }

    /**
     * Разбивает путь на сегменты и очищает пустые элементы
     * @return string[]
     */
    public function extractSegments(string $relativePath): array
    {
        $segments = explode('/', $relativePath);
        $segments = array_filter($segments, static fn($v) => $v !== '');
        return array_values($segments);
    }
}

