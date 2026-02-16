<?php
namespace App\Template;

class TemplateDataBuilder
{
    /**
     * Сборка данных для Twig-шаблона
     */
    public function build(
        array $config,
        array $global,
        ?array $pageData,
        ?array $seo,
        array $ctx,
        array $extras = []
    ): array {
        $templateData = [
            'config' => $config,
            'global' => $global,
            'currentLang' => $ctx['current_lang'] ?? null,
            'lang_code' => $ctx['lang_code'] ?? null,
            'page_id' => $ctx['page_id'] ?? null,
            'route_params' => $ctx['route_params'] ?? [],
            'base_url' => $ctx['base_url'] ?? ($config['base_url'] ?? '/'),
            'is_lang_in_url' => $ctx['is_lang_in_url'] ?? false,
            'pageData' => $pageData,
            'pageSeoData' => $seo,
            'pageTitle' => isset($seo['title']) ? $seo['title'] : ($pageData['title'] ?? ''),
        ];

        // Секции для удобства в шаблоне
        $templateData['sections'] = (isset($pageData['sections']) && is_array($pageData['sections']))
            ? $pageData['sections']
            : [];

        // Дополнительные данные
        foreach ($extras as $key => $value) {
            $templateData[$key] = $value;
        }

        return $templateData;
    }
}
