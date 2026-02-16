<?php
namespace App\SEO;

use App\Utils\JsonProcessor;

class SeoDataLoader
{
    /**
     * Загружает SEO-данные для страницы из файла seo/<pageId>.json
     */
    public static function loadSeoData(string $langCode, string $pageId, array $config, string $baseUrl): ?array
    {
        if ($pageId === '') {
            $pageId = 'index';
        }

        $seoBaseDir = rtrim($config['dirs']['json'] ?? '', '/') . '/' . $langCode . '/seo';
        $seoFilePath = $seoBaseDir . '/' . $pageId . '.json';

        if ($seoFilePath && is_file($seoFilePath)) {
            $seoJsonContent = @file_get_contents($seoFilePath);
            $seoData = $seoJsonContent !== false ? json_decode($seoJsonContent, true) : null;
            if ($seoData === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log('SEO: Ошибка JSON в ' . $seoFilePath . ': ' . json_last_error_msg());
                return null;
            }
            JsonProcessor::processJsonPaths($seoData, $baseUrl);
            return $seoData;
        }

        return null;
    }
}
