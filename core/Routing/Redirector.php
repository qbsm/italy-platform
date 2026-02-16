<?php
namespace App\Routing;

class Redirector
{
    /**
     * Возвращает абсолютный URL редиректа или null, если правила не совпали
     */
    /**
     * @return array{to:string,status:int}|null
     */
    public function getRedirectTarget(string $requestPath, string $baseUrl): ?array
    {
        $path = rtrim($requestPath, '/');
        if ($path === '') { $path = '/'; }

        $map = $this->loadMap();
        foreach ($map as $rule) {
            if (!isset($rule['from'], $rule['to'])) { continue; }
            $from = rtrim((string)$rule['from'], '/');
            if ($from === '') { $from = '/'; }
            if ($path === $from) {
                $to = (string)$rule['to'];
                $status = (int)($rule['status'] ?? 301);
                // Абсолютный URL
                if (strpos($to, 'http://') === 0 || strpos($to, 'https://') === 0) {
                    return ['to' => $to, 'status' => $status];
                }
                return ['to' => rtrim($baseUrl, '/') . '/' . ltrim($to, '/'), 'status' => $status];
            }
        }
        return null;
    }

    /**
     * Загрузка JSON-карты редиректов
     * @return array<int,array{from:string,to:string,status?:int}>
     */
    private function loadMap(): array
    {
        $file = __DIR__ . '/redirects.json';
        if (!is_file($file)) { return []; }
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
}

