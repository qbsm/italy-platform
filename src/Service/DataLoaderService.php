<?php

namespace App\Service;

use App\Support\JsonProcessor;

final class DataLoaderService
{
    /**
     * Загружает global.json — глобальные данные сайта (навигация, контакты, языки).
     *
     * @return array<string,mixed>
     */
    public function loadGlobal(string $globalPath, string $baseUrl): array
    {
        return $this->loadJson($globalPath, $baseUrl) ?? [];
    }

    /**
     * Загружает данные страницы по page_id.
     *
     * @return array<string,mixed>|null
     */
    public function loadPage(string $pagesDir, string $pageId, string $baseUrl): ?array
    {
        $path = rtrim($pagesDir, '/') . '/' . $pageId . '.json';
        return $this->loadJson($path, $baseUrl);
    }

    /**
     * Загружает SEO-данные страницы (title, meta, json_ld).
     *
     * @return array<string,mixed>|null
     */
    public function loadSeo(string $jsonBaseDir, string $langCode, string $pageId, string $baseUrl): ?array
    {
        $seoPath = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/seo/' . $pageId . '.json';
        return $this->loadJson($seoPath, $baseUrl);
    }

    /**
     * Загружает список slug'ов коллекции из страницы-списка.
     * Ищет в $data[$slugsSource] (по умолчанию items), fallback — в sections[name=$navSlug].data.items.
     *
     * @param array<string,mixed> $collectionConfig nav_slug, slugs_source
     * @return array<int,string>|null
     */
    public function loadEntitySlugs(string $jsonBaseDir, string $langCode, array $collectionConfig): ?array
    {
        $navSlug = (string) ($collectionConfig['nav_slug'] ?? '');
        $slugsSource = (string) ($collectionConfig['slugs_source'] ?? 'items');

        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/pages/' . $navSlug . '.json';
        $data = $this->loadJson($path, '');
        if (!is_array($data)) {
            return null;
        }

        $rawItems = [];
        if (isset($data[$slugsSource]) && is_array($data[$slugsSource])) {
            $rawItems = $data[$slugsSource];
        }

        if ($rawItems === [] && isset($data['sections']) && is_array($data['sections'])) {
            foreach ($data['sections'] as $section) {
                if (
                    is_array($section)
                    && ($section['name'] ?? '') === $navSlug
                    && isset($section['data']['items'])
                    && is_array($section['data']['items'])
                ) {
                    $rawItems = $section['data']['items'];
                    break;
                }
            }
        }

        $slugs = [];
        foreach ($rawItems as $item) {
            if (is_string($item) && $item !== '') {
                $slugs[] = $item;
                continue;
            }
            if (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
                $slugs[] = $item['slug'];
            }
        }

        return $slugs === [] ? null : array_values(array_unique($slugs));
    }

    /**
     * Загружает данные одной сущности коллекции.
     * Проверяет наличие item_key (если задан) и visible !== false. Устанавливает $data['slug'].
     *
     * @param array<string,mixed> $collectionConfig data_dir, item_key
     * @return array<string,mixed>|null
     */
    public function loadEntity(string $jsonBaseDir, string $langCode, string $slug, string $baseUrl, array $collectionConfig): ?array
    {
        $dataDir = (string) ($collectionConfig['data_dir'] ?? '');
        $itemKey = (string) ($collectionConfig['item_key'] ?? '');

        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/' . $dataDir . '/' . $slug . '.json';
        $data = $this->loadJson($path, $baseUrl);
        if ($data === null) {
            return null;
        }
        if ($itemKey !== '' && empty($data[$itemKey])) {
            return null;
        }
        if (isset($data['visible']) && $data['visible'] === false) {
            return null;
        }

        $data['slug'] = $slug;
        return $data;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadJson(string $path, string $baseUrl): ?array
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

        JsonProcessor::processJsonPaths($data, $baseUrl);
        return $data;
    }
}
