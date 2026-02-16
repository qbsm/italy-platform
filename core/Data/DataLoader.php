<?php
namespace App\Data;

use App\Utils\JsonProcessor;

class DataLoader
{
    public static function loadJson(string $path, string $baseUrl): ?array
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
        if ($data !== null) {
            JsonProcessor::processJsonPaths($data, $baseUrl);
        }
        return $data;
    }
}

