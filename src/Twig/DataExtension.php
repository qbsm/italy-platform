<?php

namespace App\Twig;

use App\Support\JsonProcessor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataExtension extends AbstractExtension
{
    private string $baseDir;
    private string $baseUrl;
    /** @var array<string, array<string,mixed>|null> */
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

    public function loadJson(string $relativePath): ?array
    {
        $relativePath = ltrim($relativePath, '/');

        if (array_key_exists($relativePath, $this->cache)) {
            return $this->cache[$relativePath];
        }

        $fullPath = $this->baseDir . '/' . $relativePath;
        if (!is_file($fullPath)) {
            $this->cache[$relativePath] = null;
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            $this->cache[$relativePath] = null;
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->cache[$relativePath] = null;
            return null;
        }

        JsonProcessor::processJsonPaths($data, $this->baseUrl);
        $this->cache[$relativePath] = $data;

        return $data;
    }
}
