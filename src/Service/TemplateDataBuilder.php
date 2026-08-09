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
        $sections = (isset($pageData['sections']) && is_array($pageData['sections'])) ? $pageData['sections'] : [];
        $heroPreloadImage = $this->extractHeroPreloadImage($sections);
        $preloadFonts = $this->extractFontPathsFromCss((string) ($settings['project_root'] ?? ''));

        $pageId = $ctx['page_id'] ?? null;
        $pageTitle = $seo['title'] ?? ($pageData['title'] ?? '');
        $templateData = [
            'settings' => $settings,
            'config' => ['settings' => $settings],
            'global' => $global,
            'currentLang' => $ctx['current_lang'] ?? null,
            'lang_code' => $ctx['lang_code'] ?? null,
            'page_id' => $pageId,
            'route_params' => $ctx['route_params'] ?? [],
            'base_url' => $ctx['base_url'] ?? '/',
            'is_lang_in_url' => $ctx['is_lang_in_url'] ?? false,
            'pageData' => $pageData,
            'pageSeoData' => $seo,
            'pageTitle' => $pageTitle,
            'sections' => $sections,
            'hero_preload_image' => $heroPreloadImage,
            'preload_fonts' => $preloadFonts,
            'breadcrumb' => $this->buildBreadcrumb(
                $global,
                (string) $pageId,
                (string) ($ctx['lang_code'] ?? ''),
                $pageTitle,
                (array) ($ctx['route_params'] ?? []),
                (array) ($settings['route_map'] ?? [])
            ),
        ];

        foreach ($extras as $key => $value) {
            $templateData[$key] = $value;
        }

        return $templateData;
    }

    /**
     * @param array<int,array<string,mixed>> $sections
     */
    private function extractHeroPreloadImage(array $sections): ?array
    {
        // sizes должен совпадать с пресетом 'full' в components/picture.twig,
        // иначе браузер предзагрузит не тот вариант, что отрисует <picture>.
        $sizes = '(min-width: 1441px) 1400px, (min-width: 1200px) 1200px, 100vw';

        // Собираем полный набор ширин (800/1600/raw) для imagesrcset preload,
        // чтобы предзагрузка совпадала с srcset <source> и реальный LCP-ресурс
        // (на десктопе — 1600) грузился сразу из <head>, а не открывался поздно.
        $srcset = static function ($node): ?array {
            if (!is_array($node)) {
                return null;
            }
            $out = [];
            foreach (['800', '1600', 'raw'] as $key) {
                if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
                    $out[$key] = $node[$key];
                }
            }
            return $out === [] ? null : $out;
        };

        foreach ($sections as $section) {
            if (isset($section['name']) && $section['name'] !== 'intro') {
                continue;
            }
            $items = $section['data']['slider']['items'] ?? null;
            if (!is_array($items) || $items === []) {
                return null;
            }
            $first = $items[0];
            $img = $first['image'] ?? null;

            if (is_array($img)) {
                $vertical = $srcset($img['vertical'] ?? null);
                $horizontal = $srcset($img['horizontal'] ?? null);
                $desktop = $horizontal ?? $vertical;
                if ($desktop !== null) {
                    return [
                        'sizes' => $sizes,
                        'mobile' => $vertical,
                        'desktop' => $desktop,
                    ];
                }
                $direct = $srcset($img);
                if ($direct !== null) {
                    return [
                        'sizes' => $sizes,
                        'mobile' => null,
                        'desktop' => $direct,
                    ];
                }
                foreach (['raw', 'src'] as $key) {
                    if (isset($img[$key]) && is_string($img[$key]) && $img[$key] !== '') {
                        return ['single' => $img[$key]];
                    }
                }
            }
            if (isset($first['cover']) && is_string($first['cover']) && $first['cover'] !== '') {
                return ['single' => $first['cover']];
            }
            return null;
        }
        return null;
    }

    /**
     * Извлекает пути шрифтов из fonts.css для preload (один источник правды — fonts.css).
     *
     * @return array<int,string>
     */
    private function extractFontPathsFromCss(string $projectRoot): array
    {
        $fontsCss = $projectRoot . '/assets/css/base/fonts.css';
        if (!is_readable($fontsCss)) {
            return [];
        }
        $content = (string) file_get_contents($fontsCss);
        if (preg_match_all("/url\s*\(\s*['\"]?(.+?)['\"]?\s*\)/", $content, $matches) === 0) {
            return [];
        }
        $paths = [];
        foreach ($matches[1] as $path) {
            $path = trim($path, " \t\n\r\0\x0B'\"");
            if ($path === '') {
                continue;
            }
            // В fonts.css пути вида ../../fonts/...; собранный CSS лежит в assets/css/build/ → ../../ = assets/
            $preloadPath = preg_replace('#^\.\./\.\./#', 'assets/', $path);
            if ($preloadPath !== $path || str_contains($path, 'fonts/')) {
                $paths[] = $preloadPath;
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * Строит цепочку хлебных крошек для JSON-LD BreadcrumbList.
     *
     * @param array<string,mixed> $global
     * @param array<string,string> $routeMap slug => page_id
     * @return array<int, array{name: string, url: string}>
     */
    private function buildBreadcrumb(
        array $global,
        string $pageId,
        string $langCode,
        string $pageTitle,
        array $routeParams,
        array $routeMap
    ): array {
        $reverseMap = array_flip($routeMap);
        $homeName = 'Главная';
        $homeUrl = '/';

        if (isset($global['nav'][$langCode]['items']) && is_array($global['nav'][$langCode]['items'])) {
            foreach ($global['nav'][$langCode]['items'] as $item) {
                if (!is_array($item) || !isset($item['href'])) {
                    continue;
                }
                $href = trim((string) $item['href'], '/');
                if ($href === '' || $href === '/') {
                    $homeName = isset($item['title']) ? (string) $item['title'] : $homeName;
                    $homeUrl = '/';
                    break;
                }
            }
        }

        $items = [['name' => $homeName, 'url' => $homeUrl]];

        if ($pageId !== 'index' && $pageId !== '404') {
            $pathSegment = (string) ($reverseMap[$pageId] ?? $pageId);
            $path = $pathSegment;
            if ($routeParams !== []) {
                $path .= '/' . implode('/', $routeParams);
            }
            $items[] = [
                'name' => $pageTitle !== '' ? $pageTitle : $pathSegment,
                'url' => '/' . $path . '/',
            ];
        }

        return $items;
    }
}
