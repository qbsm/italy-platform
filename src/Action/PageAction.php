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
        if ($pageData === null) {
            $status = 404;
            $pageId = '404';
            $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
        }

        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
        $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, $pageId, $baseUrl);

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

        $template = 'pages/' . $pageId . '.twig';
        if (!$twigEnv->getLoader()->exists($template)) {
            $status = 404;
            $pageId = '404';
            $template = 'pages/404.twig';
            $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, '404', $baseUrl);
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
            ]
        );

        return $this->twig->render($response->withStatus($status), $template, $data);
    }
}
