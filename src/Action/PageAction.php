<?php

namespace App\Action;

use App\Event\EntityResolved;
use App\Event\PageLoaded;
use App\Event\SeoBuilt;
use App\Service\DataLoaderService;
use App\Service\SeoBuilderRegistry;
use App\Service\SeoService;
use App\Service\TemplateDataBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class PageAction
{
    /** @var array<string,mixed> */
    private array $settings;

    public function __construct(
        private Twig $twig,
        private DataLoaderService $dataLoader,
        private SeoService $seoService,
        private TemplateDataBuilder $templateDataBuilder,
        private SeoBuilderRegistry $seoRegistry,
        array $settings,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->settings = $settings;
    }

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

        $collections = (array) ($this->settings['collections'] ?? []);
        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');

        $status = 200;
        $entity = null;
        $entityType = '';
        $entityConfig = [];

        // Кейс A: pageData не найден — пробуем segments[0] как direct entity slug
        if ($pageData === null) {
            $directSlug = (string) ($segments[0] ?? '');
            if ($directSlug !== '') {
                foreach ($collections as $collKey => $collConfig) {
                    $collConfig = (array) $collConfig;
                    $slugs = $this->dataLoader->loadEntitySlugs($jsonBaseDir, $langCode, $collConfig);
                    if ($slugs !== null && in_array($directSlug, $slugs, true)) {
                        $loaded = $this->dataLoader->loadEntity($jsonBaseDir, $langCode, $directSlug, $baseUrl, $collConfig);
                        if ($loaded !== null) {
                            $entity = $loaded;
                            $entityType = (string) $collKey;
                            $entityConfig = $collConfig;
                            break;
                        }
                    }
                }
            }

            if ($entity !== null) {
                $pageId = $entity['slug'];
                $routeParams = [];
                $pageData = ['name' => $pageId, 'sections' => []];
                $this->dispatch(new EntityResolved($entityType, $pageId, $entity, $entityConfig));
            } else {
                $status = 404;
                $pageId = '404';
                $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            }
        }

        // Кейс B: попали на list_page_id — либо отдаём список, либо резолвим вложенный entity slug
        if ($entity === null) {
            foreach ($collections as $collKey => $collConfig) {
                $collConfig = (array) $collConfig;
                $listPageId = (string) ($collConfig['list_page_id'] ?? '');
                if ($pageId !== $listPageId) {
                    continue;
                }

                if (count($routeParams) === 1) {
                    $subSlug = (string) $routeParams[0];
                    $slugs = $this->dataLoader->loadEntitySlugs($jsonBaseDir, $langCode, $collConfig);
                    if ($slugs !== null && in_array($subSlug, $slugs, true)) {
                        $loaded = $this->dataLoader->loadEntity($jsonBaseDir, $langCode, $subSlug, $baseUrl, $collConfig);
                        if ($loaded !== null) {
                            $entity = $loaded;
                            $entityType = (string) $collKey;
                            $entityConfig = $collConfig;
                            $pageId = $subSlug;
                            $pageData = ['name' => $subSlug, 'sections' => []];
                            $this->dispatch(new EntityResolved($entityType, $subSlug, $entity, $entityConfig));
                        }
                    }
                    if ($entity === null) {
                        $status = 404;
                        $pageId = '404';
                        $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
                    }
                } elseif (count($routeParams) > 1) {
                    $status = 404;
                    $pageId = '404';
                    $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
                }
                break;
            }
        }

        $this->dispatch(new PageLoaded($pageId, $langCode, $pageData, $status));

        $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, $pageId, $baseUrl);

        if ($entity !== null) {
            $seoData = $this->buildSeoForEntity($entity, $baseUrl, $langCode, $entityType, $entityConfig, is_array($global) ? $global : []);
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

        $this->dispatch(new SeoBuilt($pageId, $seoData, $entity !== null));

        $template = 'pages/page.twig';
        $extras = [];
        if ($entity !== null) {
            $template = (string) ($entityConfig['template'] ?? 'pages/page.twig');
            $extrasKey = (string) ($entityConfig['extras_key'] ?? $entityType);
            if ($extrasKey !== '') {
                $extras[$extrasKey] = $entity;
            }
            $extras['entity'] = $entity;
            $extras['breadcrumb'] = $this->buildEntityBreadcrumb(is_array($global) ? $global : [], $langCode, $entity, $entityConfig);
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
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @param array<string,mixed> $global
     * @return array<string,mixed>
     */
    private function buildSeoForEntity(array $entity, string $baseUrl, string $langCode, string $entityType, array $config, array $global): array
    {
        $builder = $this->seoRegistry->get($entityType);
        if ($builder !== null) {
            return $builder->build($entity, $baseUrl, $langCode, $config, $global);
        }

        // Дефолтный generic-вариант
        $itemKey = (string) ($config['item_key'] ?? '');
        $ogType = (string) ($config['og_type'] ?? 'website');

        $inner = $itemKey !== '' ? ($entity[$itemKey] ?? []) : $entity;
        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $desc = (string) ($entity['desc']['short'] ?? $entity['desc']['full'] ?? $inner['desc'] ?? $inner['lead'] ?? '');

        return [
            'title' => $name,
            'meta' => [
                ['name' => 'description', 'content' => $desc],
                ['property' => 'og:type', 'content' => $ogType],
                ['property' => 'og:title', 'content' => $name],
                ['property' => 'og:description', 'content' => $desc],
            ],
            'json_ld' => null,
            'json_ld_faq' => null,
        ];
    }

    /**
     * @param array<string,mixed> $global
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @return array<int, array{name: string, url: string}>
     */
    private function buildEntityBreadcrumb(array $global, string $langCode, array $entity, array $config): array
    {
        $itemKey = (string) ($config['item_key'] ?? '');
        $navSlug = (string) ($config['nav_slug'] ?? '');
        $urlPattern = (string) ($config['entity_url_pattern'] ?? ('/' . $navSlug . '/{slug}'));

        $inner = $itemKey !== '' ? ($entity[$itemKey] ?? []) : $entity;
        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $slug = (string) ($entity['slug'] ?? '');

        $nav = $global['nav'][$langCode]['items'] ?? [];
        $homeTitle = 'Главная';
        $listTitle = (string) ($config['list_title'] ?? ucfirst($navSlug));
        $listHref = '/' . trim($navSlug, '/') . '/';
        if (is_array($nav)) {
            foreach ($nav as $navItem) {
                if (!is_array($navItem)) {
                    continue;
                }
                $href = trim((string) ($navItem['href'] ?? ''), '/');
                if ($href === '' || $href === '/') {
                    $homeTitle = (string) ($navItem['title'] ?? $homeTitle);
                }
                if ($href === $navSlug) {
                    $listTitle = (string) ($navItem['title'] ?? $listTitle);
                    $listHref = '/' . $href . '/';
                }
            }
        }

        return [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => $listHref],
            ['name' => $name, 'url' => str_replace('{slug}', $slug, $urlPattern)],
        ];
    }

    private function dispatch(object $event): void
    {
        $this->dispatcher?->dispatch($event);
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
