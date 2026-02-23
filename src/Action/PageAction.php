<?php

namespace App\Action;

use App\Service\DataLoaderService;
use App\Service\SeoService;
use App\Service\TemplateDataBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Twig\Environment;

final class PageAction
{
    /** @var array<string,mixed> */
    private array $settings;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        Twig $twig,
        DataLoaderService $dataLoader,
        SeoService $seoService,
        TemplateDataBuilder $templateDataBuilder,
        array $settings
    ) {
        $this->twig = $twig;
        $this->dataLoader = $dataLoader;
        $this->seoService = $seoService;
        $this->templateDataBuilder = $templateDataBuilder;
        $this->settings = $settings;
    }

    private Twig $twig;
    private DataLoaderService $dataLoader;
    private SeoService $seoService;
    private TemplateDataBuilder $templateDataBuilder;

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csrfToken = $this->ensureCsrfToken();
        $segments = $request->getAttribute('segments', []);
        $baseUrl = (string) $request->getAttribute('base_url', '/');
        $global = $request->getAttribute('global', []);
        $langCode = (string) $request->getAttribute('lang_code', $this->settings['default_lang'] ?? 'ru');
        $currentLang = $request->getAttribute('current_lang', ['code' => $langCode]);
        $isLangInUrl = (bool) $request->getAttribute('is_lang_in_url', false);

        $pageId = 'index';
        $routeParams = [];
        if (!empty($segments)) {
            $slug = (string) $segments[0];
            $routeMap = (array) ($this->settings['route_map'] ?? []);
            $pageId = (string) ($routeMap[$slug] ?? $slug);
            $routeParams = array_slice($segments, 1);
        }

        $pageDirTemplate = (string) ($this->settings['paths']['json_pages_dir'] ?? '');
        $pageJsonDir = str_replace('{lang}', $langCode, $pageDirTemplate);
        $pageData = $this->dataLoader->loadPage($pageJsonDir, $pageId, $baseUrl);

        $status = 200;
        $restaurant = null;
        $restaurantBreadcrumb = null;

        if ($pageData === null) {
            $slug = (string) ($segments[0] ?? '');
            $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
            $slugs = $this->dataLoader->loadRestaurantSlugs($jsonBaseDir, $langCode);
            if ($slug !== '' && $slugs !== null && in_array($slug, $slugs, true)) {
                $restaurant = $this->dataLoader->loadRestaurant($jsonBaseDir, $langCode, $slug, $baseUrl);
            }
            if ($restaurant !== null) {
                $pageId = $slug;
                $routeParams = [];
                $pageData = ['name' => $slug, 'sections' => []];
            } else {
                $status = 404;
                $pageId = '404';
                $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            }
        }

        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
        $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, $pageId, $baseUrl);

        if ($restaurant !== null) {
            $seoData = $this->buildSeoForRestaurant($restaurant, $baseUrl, $langCode, $global);
            $restaurantBreadcrumb = $this->buildRestaurantBreadcrumb($global, $langCode, $restaurant);
        }

        if ($seoData !== null) {
            $twigEnv = $this->twig->getEnvironment();
            $seoData = $this->seoService->processTemplates($seoData, [
                'pageData' => $pageData,
                'global' => $global,
                'settings' => $this->settings,
                'currentLang' => $currentLang,
                'lang_code' => $langCode,
                'route_params' => $routeParams,
                'base_url' => $baseUrl,
                'is_lang_in_url' => $isLangInUrl,
            ], $twigEnv);
        } else {
            $seoData = ['title' => '', 'meta' => [], 'json_ld' => null];
        }

        $template = $restaurant !== null ? 'pages/restaurant.twig' : 'pages/page.twig';

        $extras = [];
        if ($restaurant !== null) {
            $extras['restaurant'] = $restaurant;
            $extras['breadcrumb'] = $restaurantBreadcrumb;
        }

        $data = $this->templateDataBuilder->build(
            $this->settings,
            is_array($global) ? $global : [],
            $pageData,
            $seoData,
            [
                'current_lang' => is_array($currentLang) ? $currentLang : ['code' => $langCode],
                'lang_code' => $langCode,
                'page_id' => $pageId,
                'route_params' => $routeParams,
                'base_url' => $baseUrl,
                'is_lang_in_url' => $isLangInUrl,
                'csrf_token' => $csrfToken,
            ],
            $extras
        );

        return $this->twig->render($response->withStatus($status), $template, $data);
    }

    /**
     * @param array<string,mixed> $restaurant
     * @param array<string,mixed> $global
     */
    private function buildSeoForRestaurant(array $restaurant, string $baseUrl, string $langCode, array $global): array
    {
        $r = $restaurant['restaurant'] ?? [];
        $name = (string) ($r['name'] ?? $restaurant['slug'] ?? '');
        $desc = (string) ($restaurant['desc']['short'] ?? $restaurant['desc']['full'] ?? '');

        $jsonLd = $this->buildRestaurantJsonLd($restaurant);
        $jsonLdFaq = $this->buildRestaurantFaqJsonLd($restaurant, $langCode, $global);
        return [
            'title' => $name,
            'meta' => [
                ['name' => 'description', 'content' => $desc],
                ['property' => 'og:type', 'content' => 'website'],
                ['property' => 'og:title', 'content' => $name],
                ['property' => 'og:description', 'content' => $desc],
            ],
            'json_ld' => $jsonLd,
            'json_ld_faq' => $jsonLdFaq,
        ];
    }

    /** @param array<string,mixed> $restaurant */
    private function buildRestaurantJsonLd(array $restaurant): string
    {
        $r = $restaurant['restaurant'] ?? [];
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $r['name'] ?? null,
            'telephone' => isset($r['telephone']['title']) ? $r['telephone']['title'] : null,
            'address' => isset($r['address']) ? $r['address'] : null,
            'geo' => $r['geo'] ?? null,
            'url' => $r['url'] ?? null,
            'priceRange' => $r['priceRange'] ?? null,
            'hasMap' => $r['hasMap'] ?? null,
            'menu' => $r['menuLink'] ?? null,
        ];
        if (!empty($r['openingHours']) && is_array($r['openingHours'])) {
            $ld['openingHours'] = array_map(static function ($h) {
                return trim(($h['days'] ?? '') . ' ' . ($h['hours'] ?? ''));
            }, $r['openingHours']);
        }
        if (!empty($r['servesCuisine'])) {
            $ld['servesCuisine'] = $r['servesCuisine'];
        }
        $ld = array_filter($ld, static fn ($v) => $v !== null && $v !== '');
        return (string) json_encode($ld, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string,mixed> $restaurant
     * @param array<string,mixed> $global
     */
    private function buildRestaurantFaqJsonLd(array $restaurant, string $langCode, array $global): ?string
    {
        $r = $restaurant['restaurant'] ?? [];
        $faq = $global['restaurant-faq'][$langCode] ?? $global['restaurant-faq']['ru'] ?? null;
        if (!is_array($faq)) {
            return null;
        }
        $mainEntity = [];
        if (!empty($r['address'])) {
            $addr = $r['address'];
            $locality = isset($addr['addressLocality']) && (string) $addr['addressLocality'] !== '' ? ', ' . $addr['addressLocality'] : '';
            $answer = trim(($addr['streetAddress'] ?? '') . $locality);
            if ($answer !== '' && isset($faq['where'])) {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['where'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }
        if (!empty($r['openingHours']) && is_array($r['openingHours']) && isset($faq['hours'])) {
            $parts = array_map(static function ($h) {
                return trim(($h['days'] ?? '') . ' ' . ($h['hours'] ?? ''));
            }, $r['openingHours']);
            $answer = implode('; ', array_filter($parts));
            if ($answer !== '') {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['hours'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }
        if (!empty($r['servesCuisine']) && is_array($r['servesCuisine']) && isset($faq['cuisine'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['cuisine'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => implode(', ', $r['servesCuisine'])],
            ];
        }
        if (!empty($r['telephone']['title']) && isset($faq['contact'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['contact'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $r['telephone']['title']],
            ];
        }
        if (!empty($r['priceRange']) && isset($faq['price'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['price'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $r['priceRange']],
            ];
        }
        if ($mainEntity === []) {
            return null;
        }
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
        return (string) json_encode($ld, JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string,mixed> $restaurant
     * @return array<int, array{name: string, url: string}>
     */
    private function buildRestaurantBreadcrumb(array $global, string $langCode, array $restaurant): array
    {
        $r = $restaurant['restaurant'] ?? [];
        $name = (string) ($r['name'] ?? $restaurant['slug'] ?? '');
        $nav = $global['nav'][$langCode]['items'] ?? [];
        $homeTitle = 'Главная';
        $listTitle = 'Рестораны';
        $listHref = '/restaurants/';
        foreach ($nav as $item) {
            if (!is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['href'] ?? ''), '/');
            if ($href === '' || $href === '/') {
                $homeTitle = (string) ($item['title'] ?? $homeTitle);
            }
            if ($href === 'restaurants') {
                $listTitle = (string) ($item['title'] ?? $listTitle);
                $listHref = '/' . $href . '/';
            }
        }
        return [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => $listHref],
            ['name' => $name, 'url' => '/' . $restaurant['slug'] . '/'],
        ];
    }

    private function ensureCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
