<?php
namespace App\Bootstrap;

use App\Config\ConfigManager;
use App\Template\TemplateEngine;
use App\Template\TemplateDataBuilder;
use App\Template\TemplateResolver;
use App\Routing\UrlParser;
use App\Routing\Router;
use App\Routing\Redirector;
use App\Language\LanguageDetector;
use App\Data\PageDataLoader;
use App\SEO\SeoDataLoader;
use App\SEO\SeoProcessor;
use App\Utils\ErrorHandler;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Application
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        ErrorHandler::setConfig($this->config);
    }

    public function run(): void
    {
        try {
            $settings = $this->config['settings'] ?? [];

            // --- Redirects ---
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

            $redirector = new Redirector();
            $redirect = $redirector->getRedirectTarget($requestPath, $this->config['base_url']);
            if ($redirect) {
                http_response_code($redirect['status']);
                header('Location: ' . $redirect['to']);
                exit;
            }

            // --- URL Parsing ---
            $basePath = parse_url($this->config['base_url'] ?? '/', PHP_URL_PATH) ?: '/';
            $urlParser = new UrlParser();
            $relativePath = $urlParser->parseRequestPath($requestPath, $basePath);
            $segments = $urlParser->extractSegments($relativePath);

            // --- Language Detection ---
            $global = $this->loadGlobal();
            $supportedLanguages = [];
            if (isset($global['lang']) && is_array($global['lang'])) {
                foreach ($global['lang'] as $langInfo) {
                    $supportedLanguages[$langInfo['code']] = $langInfo;
                }
            }
            $defaultLang = $settings['default_lang'] ?? 'ru';
            $detector = new LanguageDetector();
            $detected = $detector->detect($segments, $supportedLanguages, $defaultLang);
            $langCode = $detected['lang_code'];
            $currentLang = $detected['current_lang'];
            $isLangInUrl = $detected['is_lang_in_url'];
            $segments = $detected['segments'];

            // --- Routing ---
            $router = new Router();
            $router->resolveRoute($segments);
            $pageId = $router->getPageId();
            $routeParams = $router->getRouteParams();

            // --- Load Page Data ---
            $pageJsonDir = str_replace('{lang}', $langCode, $this->config['json']['pages_dir']);
            $pageData = PageDataLoader::loadPage($pageJsonDir, $pageId, $this->config['base_url']);

            if ($pageData === null) {
                http_response_code(404);
                $pageId = '404';
                $pageData = PageDataLoader::loadPage($pageJsonDir, '404', $this->config['base_url']);
                if ($pageData === null) {
                    $pageData = ['name' => '404', 'sections' => []];
                }
            }

            // --- SEO ---
            $currentPageSeo = SeoDataLoader::loadSeoData($langCode, $pageId, $this->config, $this->config['base_url']);

            // --- Template Engine ---
            $templateEngine = new TemplateEngine();
            $templateEngine->initialize($this->config['base_dir'], $this->config['base_url'], $settings);
            $twig = $templateEngine->getTwig();

            // --- Process SEO Templates ---
            if ($currentPageSeo !== null) {
                try {
                    $processor = new SeoProcessor();
                    $currentPageSeo = $processor->processSeoTemplates($currentPageSeo, [
                        'pageData' => $pageData,
                        'global' => $global,
                        'config' => $this->config,
                        'currentLang' => $currentLang,
                        'lang_code' => $langCode,
                        'route_params' => $routeParams,
                        'base_url' => $this->config['base_url'],
                        'is_lang_in_url' => $isLangInUrl,
                    ], $twig);
                } catch (LoaderError | RuntimeError | SyntaxError $e) {
                    error_log('SEO Twig error: ' . $e->getMessage());
                }
            }

            // --- Resolve Template ---
            $resolver = new TemplateResolver();
            $resolved = $resolver->resolve($twig, $pageId);
            if ($resolved['page_id'] !== $pageId) {
                $pageId = $resolved['page_id'];
                $currentPageSeo = SeoDataLoader::loadSeoData($langCode, $pageId, $this->config, $this->config['base_url']);
            }

            // --- Build Template Data ---
            $builder = new TemplateDataBuilder();
            $templateData = $builder->build(
                $this->config,
                $global,
                $pageData,
                $currentPageSeo,
                [
                    'current_lang' => $currentLang,
                    'lang_code' => $langCode,
                    'page_id' => $pageId,
                    'route_params' => $routeParams,
                    'base_url' => $this->config['base_url'],
                    'is_lang_in_url' => $isLangInUrl,
                ]
            );

            // --- Render ---
            echo $twig->render($resolved['path'], $templateData);
            exit;
        } catch (\Throwable $e) {
            ErrorHandler::handleThrowable($e);
        }
    }

    private function loadGlobal(): array
    {
        $globalPath = $this->config['json']['global'] ?? null;
        if ($globalPath && is_file($globalPath)) {
            $content = file_get_contents($globalPath);
            $global = json_decode($content, true) ?: [];
            if ($global !== null) {
                \App\Utils\JsonProcessor::processJsonPaths($global, $this->config['base_url']);
            }
            return $global;
        }
        return [];
    }
}
