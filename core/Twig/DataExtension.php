<?php

namespace App\Twig;

use App\Utils\JsonProcessor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataExtension extends AbstractExtension
{
    private string $baseDir;
    private string $baseUrl;
    private array $cache = [];

    public function __construct(string $baseDir, string $baseUrl)
    {
        $this->baseDir = rtrim($baseDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('load_json', [$this, 'loadJson']),
        ];
    }

    /**
     * @param string $relativePath Путь относительно корня проекта, например "data/json/ru/restaurants/italy-bolshoy.json"
     */
    public function loadJson(string $relativePath): ?array
    {
        $relativePath = ltrim($relativePath, '/');

        if (isset($this->cache[$relativePath])) {
            return $this->cache[$relativePath];
        }

        $fullPath = $this->baseDir . '/' . $relativePath;

        if (!is_file($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('DataExtension: Ошибка JSON в ' . $fullPath . ': ' . json_last_error_msg());
            return null;
        }

        JsonProcessor::processJsonPaths($data, $this->baseUrl);
        $this->cache[$relativePath] = $data;

        return $data;
    }
}
