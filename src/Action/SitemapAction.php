<?php

declare(strict_types=1);

namespace App\Action;

use App\Service\DataLoaderService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Генерация sitemap.xml с учётом мультиязычности и hreflang.
 * Статические страницы берутся из settings['sitemap_pages'] (массив page_id),
 * страницы коллекций (рестораны) — из settings['collections'] через DataLoaderService.
 * URL без хвостового слеша — под canonical (TrailingSlashMiddleware режет слеш).
 */
final class SitemapAction
{
    /** @var array<string, mixed> */
    private array $settings;

    private DataLoaderService $dataLoader;

    /** @param array<string, mixed> $settings */
    public function __construct(array $settings, DataLoaderService $dataLoader)
    {
        $this->settings = $settings;
        $this->dataLoader = $dataLoader;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $path = $uri->getPath();
        if ($path !== '' && $path !== '/') {
            $base .= rtrim(dirname($path), '/');
        }
        $base = rtrim($base, '/');

        $langs = (array) ($this->settings['available_langs'] ?? ['ru', 'en']);
        $defaultLang = (string) ($this->settings['default_lang'] ?? 'ru');
        $routeMap = (array) ($this->settings['route_map'] ?? []);

        $sitemapPages = (array) ($this->settings['sitemap_pages'] ?? []);
        $urls = $this->buildUrls($base, $langs, $defaultLang, $routeMap, $sitemapPages);

        $collections = (array) ($this->settings['collections'] ?? []);
        $jsonBase = (string) ($this->settings['paths']['json_base'] ?? '');
        if ($collections !== [] && $jsonBase !== '') {
            $urls = array_merge($urls, $this->buildCollectionUrls($base, $langs, $defaultLang, $jsonBase, $collections));
        }

        $xml = $this->renderSitemap($base, $urls);

        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withStatus(200);
    }

    /**
     * @param array<int, string> $langs
     * @param array<string, string> $routeMap slug => page_id
     * @param array<int, string> $sitemapPages page_id для включения в sitemap
     * @return array<int, array{loc: string, alternates: array<string, string>}>
     */
    private function buildUrls(string $base, array $langs, string $defaultLang, array $routeMap, array $sitemapPages): array
    {
        $reverseMap = array_flip($routeMap);
        $urls = [];

        foreach ($sitemapPages as $pageId) {
            $pathSegment = $this->pageIdToPathSegment($pageId, $reverseMap);
            foreach ($this->urlsForSegment($base, $langs, $defaultLang, $pathSegment) as $u) {
                $urls[] = $u;
            }
        }

        return $urls;
    }

    /**
     * Страницы коллекций (рестораны): slug'и берутся из страницы-списка через DataLoaderService.
     *
     * @param array<int, string> $langs
     * @param array<string, mixed> $collections
     * @return array<int, array{loc: string, alternates: array<string, string>}>
     */
    private function buildCollectionUrls(string $base, array $langs, string $defaultLang, string $jsonBase, array $collections): array
    {
        $urls = [];

        foreach ($collections as $collConfig) {
            if (!is_array($collConfig)) {
                continue;
            }
            $pattern = (string) ($collConfig['entity_url_pattern'] ?? '');
            if ($pattern === '') {
                continue;
            }
            $slugs = $this->dataLoader->loadEntitySlugs($jsonBase, $defaultLang, $collConfig) ?? [];
            foreach ($slugs as $slug) {
                $segment = trim(str_replace('{slug}', $slug, $pattern), '/');
                foreach ($this->urlsForSegment($base, $langs, $defaultLang, $segment) as $u) {
                    $urls[] = $u;
                }
            }
        }

        return $urls;
    }

    /**
     * Строит loc + alternates для одного path-сегмента по всем языкам.
     * Без хвостового слеша (кроме главной). Alternates печатаются только при мультиязычности.
     *
     * @param array<int, string> $langs
     * @return array<int, array{loc: string, alternates: array<string, string>}>
     */
    private function urlsForSegment(string $base, array $langs, string $defaultLang, string $pathSegment): array
    {
        $multilang = count($langs) > 1;
        $urls = [];

        foreach ($langs as $lang) {
            $loc = $base . $this->pathForLang($lang, $defaultLang, $pathSegment);

            $alternates = [];
            if ($multilang) {
                foreach ($langs as $altLang) {
                    $alternates[$altLang] = $base . $this->pathForLang($altLang, $defaultLang, $pathSegment);
                }
            }

            $urls[] = ['loc' => $loc, 'alternates' => $alternates];
        }

        return $urls;
    }

    private function pathForLang(string $lang, string $defaultLang, string $pathSegment): string
    {
        if ($lang === $defaultLang) {
            return $pathSegment === '' ? '/' : '/' . $pathSegment;
        }
        return $pathSegment === '' ? '/' . $lang : '/' . $lang . '/' . $pathSegment;
    }

    /** @param array<string, string> $reverseMap */
    private function pageIdToPathSegment(string $pageId, array $reverseMap): string
    {
        if ($pageId === 'index') {
            return '';
        }
        return (string) ($reverseMap[$pageId] ?? $pageId);
    }

    /**
     * @param array<int, array{loc: string, alternates: array<string, string>}> $urls
     */
    private function renderSitemap(string $base, array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $u) {
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            foreach ($u['alternates'] as $hreflang => $href) {
                $out .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . '"/>' . "\n";
            }
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }
}
