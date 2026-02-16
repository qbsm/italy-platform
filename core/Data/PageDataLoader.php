<?php
namespace App\Data;

class PageDataLoader
{
    public static function loadPage(string $pageJsonDir, string $pageId, string $baseUrl): ?array
    {
        $path = rtrim($pageJsonDir, '/') . '/' . $pageId . '.json';
        return DataLoader::loadJson($path, $baseUrl);
    }
}
