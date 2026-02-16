<?php
namespace App\Template;

use Twig\Environment;

class TemplateResolver
{
    /**
     * Возвращает [path, resolvedPageId]
     * Проверяет существование шаблона для pageId и при отсутствии
     * выполняет фолбэк на 404, возвращая скорректированный pageId.
     * @return array{path:string,page_id:string}
     */
    public function resolve(Environment $twig, string $pageId): array
    {
        $templatePath = 'pages/' . $pageId . '.twig';
        if ($twig->getLoader()->exists($templatePath)) {
            return ['path' => $templatePath, 'page_id' => $pageId];
        }
        // Фолбэк на 404
        $fallbackId = '404';
        $fallbackPath = 'pages/404.twig';
        return ['path' => $fallbackPath, 'page_id' => $fallbackId];
    }
}

