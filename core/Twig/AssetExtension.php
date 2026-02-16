<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use RuntimeException;

class AssetExtension extends AbstractExtension
{
    private string $baseDir;
    private string $baseUrl;
    private string $manifestPath;
    private string $cssManifestPath;
    private ?array $manifestCache = null;
    private ?array $cssManifestCache = null;

    public function __construct(string $baseDir, string $baseUrl = '')
    {
        $this->baseDir = $baseDir;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';

        $this->manifestPath = $this->baseDir . '/assets/js/build/asset-manifest.json';
        $this->cssManifestPath = $this->baseDir . '/assets/css/build/css-manifest.json';

        $this->loadManifests();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('assetUrl', [$this, 'getAssetUrl']),
            new TwigFunction('asset_manifest', [$this, 'getAssetManifest']),
            new TwigFunction('css_manifest', [$this, 'getCssManifest']),
        ];
    }

    public function getAssetUrl(string $assetName, string $manifestType = 'js', bool $safe = false): ?string
    {
        $manifest = null;
        $manifestFilePath = '';

        if ($manifestType === 'js') {
            $manifest = $this->getAssetManifest();
            $manifestFilePath = $this->manifestPath;
        } elseif ($manifestType === 'css') {
            $manifest = $this->getCssManifest();
            $manifestFilePath = $this->cssManifestPath;
        } else {
            if ($safe) {
                error_log("AssetExtension: Неизвестный тип манифеста: '{$manifestType}'. Используйте 'js' или 'css'.");
                return null;
            }
            throw new RuntimeException("Неизвестный тип манифеста: '{$manifestType}'. Используйте 'js' или 'css'.");
        }

        if ($manifest === null) {
            if ($safe) {
                error_log("AssetExtension: Манифест типа '{$manifestType}' не найден или не удалось прочитать по пути: {$manifestFilePath}");
                return null;
            }
            throw new RuntimeException("Манифест типа '{$manifestType}' не найден или не удалось прочитать по пути: {$manifestFilePath}");
        }

        $trimmedAssetName = ltrim($assetName, '/');

        if (!isset($manifest[$assetName]) && !isset($manifest[$trimmedAssetName])) {
            if ($safe) {
                error_log("AssetExtension: Ассет '{$assetName}' не найден в манифесте типа '{$manifestType}'. Доступные ключи: " . implode(', ', array_keys($manifest)));
                return null;
            }
            throw new RuntimeException("Ассет '{$assetName}' не найден в манифесте типа '{$manifestType}' ({$manifestFilePath}). Доступные ключи: " . implode(', ', array_keys($manifest)));
        }

        $key = isset($manifest[$assetName]) ? $assetName : $trimmedAssetName;
        $hashedPath = $manifest[$key];
        $trimmedHashedPath = ltrim($hashedPath, '/');

        return $this->baseUrl . $trimmedHashedPath;
    }

    public function getAssetManifest(): ?array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        if (!file_exists($this->manifestPath)) {
            error_log("AssetExtension: Манифест JS не найден по пути: " . $this->manifestPath);
            $this->manifestCache = null;
            return null;
        }

        $manifestJson = @file_get_contents($this->manifestPath);
        if ($manifestJson === false) {
            error_log("AssetExtension: Не удалось прочитать манифест JS по пути: " . $this->manifestPath);
            $this->manifestCache = null;
            return null;
        }

        $manifest = json_decode($manifestJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AssetExtension: Ошибка декодирования JSON в манифесте JS ({$this->manifestPath}): " . json_last_error_msg());
            $this->manifestCache = null;
            return null;
        }
        if (!is_array($manifest)) {
            error_log("AssetExtension: Декодированный JSON манифеста JS не является массивом ({$this->manifestPath})");
            $this->manifestCache = null;
            return null;
        }

        $this->manifestCache = $manifest;
        return $this->manifestCache;
    }

    public function getCssManifest(): ?array
    {
        if ($this->cssManifestCache !== null) {
            return $this->cssManifestCache;
        }

        if (!file_exists($this->cssManifestPath)) {
            error_log("AssetExtension: Манифест CSS не найден по пути: " . $this->cssManifestPath);
            $this->cssManifestCache = null;
            return null;
        }

        $manifestJson = @file_get_contents($this->cssManifestPath);
        if ($manifestJson === false) {
            error_log("AssetExtension: Не удалось прочитать манифест CSS по пути: " . $this->cssManifestPath);
            $this->cssManifestCache = null;
            return null;
        }

        $manifest = json_decode($manifestJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AssetExtension: Ошибка декодирования JSON в манифесте CSS ({$this->cssManifestPath}): " . json_last_error_msg());
            $this->cssManifestCache = null;
            return null;
        }
        if (!is_array($manifest)) {
            error_log("AssetExtension: Декодированный JSON манифеста CSS не является массивом ({$this->cssManifestPath})");
            $this->cssManifestCache = null;
            return null;
        }

        $this->cssManifestCache = $manifest;
        return $this->cssManifestCache;
    }

    private function loadManifests(): void
    {
        if (file_exists($this->manifestPath)) {
            $manifestContent = file_get_contents($this->manifestPath);
            $manifestData = json_decode($manifestContent, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifestData)) {
                error_log("AssetExtension: Проблема с манифестом JS ({$this->manifestPath})");
                $this->manifestCache = [];
            } else {
                $this->manifestCache = $manifestData;
            }
        } else {
            $this->manifestCache = [];
        }

        if (file_exists($this->cssManifestPath)) {
            $cssManifestContent = file_get_contents($this->cssManifestPath);
            $cssManifestData = json_decode($cssManifestContent, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($cssManifestData)) {
                error_log("AssetExtension: Проблема с манифестом CSS ({$this->cssManifestPath})");
                $this->cssManifestCache = [];
            } else {
                $this->cssManifestCache = $cssManifestData;
            }
        } else {
            $this->cssManifestCache = [];
        }
    }
}

