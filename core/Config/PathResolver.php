<?php
namespace App\Config;

class PathResolver
{
    public function resolvePaths(string $baseDir, string $baseUrl): array
    {
        return [
            'base_dir' => $baseDir,
            'base_url' => rtrim($baseUrl, '/') . '/',
        ];
    }

    public function resolveJsonPath(string $template, string $lang): string
    {
        return str_replace('{lang}', $lang, $template);
    }
}

