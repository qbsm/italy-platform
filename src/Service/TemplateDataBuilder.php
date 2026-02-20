<?php

namespace App\Service;

final class TemplateDataBuilder
{
    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $global
     * @param array<string,mixed>|null $pageData
     * @param array<string,mixed>|null $seo
     * @param array<string,mixed> $ctx
     * @param array<string,mixed> $extras
     * @return array<string,mixed>
     */
    public function build(
        array $settings,
        array $global,
        ?array $pageData,
        ?array $seo,
        array $ctx,
        array $extras = []
    ): array {
        $templateData = [
            'settings' => $settings,
            'global' => $global,
            'currentLang' => $ctx['current_lang'] ?? null,
            'lang_code' => $ctx['lang_code'] ?? null,
            'page_id' => $ctx['page_id'] ?? null,
            'route_params' => $ctx['route_params'] ?? [],
            'base_url' => $ctx['base_url'] ?? '/',
            'is_lang_in_url' => $ctx['is_lang_in_url'] ?? false,
            'pageData' => $pageData,
            'pageSeoData' => $seo,
            'pageTitle' => $seo['title'] ?? ($pageData['title'] ?? ''),
            'sections' => (isset($pageData['sections']) && is_array($pageData['sections'])) ? $pageData['sections'] : [],
        ];

        foreach ($extras as $key => $value) {
            $templateData[$key] = $value;
        }

        return $templateData;
    }
}
