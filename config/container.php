<?php

declare(strict_types=1);

use App\Action\PageAction;
use App\Middleware\LanguageMiddleware;
use App\Middleware\RedirectMiddleware;
use App\Middleware\TrailingSlashMiddleware;
use App\Service\DataLoaderService;
use App\Service\LanguageService;
use App\Service\SeoService;
use App\Service\TemplateDataBuilder;
use App\Support\BaseUrlResolver;
use App\Twig\AssetExtension;
use App\Twig\DataExtension;
use App\Twig\UrlExtension;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension;
use Twig\Extension\StringLoaderExtension;

return static function (): ContainerInterface {
    $settings = require __DIR__ . '/settings.php';
    $builder = new ContainerBuilder();

    $builder->addDefinitions([
        'settings' => $settings,

        ResponseFactoryInterface::class => static fn() => new ResponseFactory(),
        BaseUrlResolver::class => static fn() => new BaseUrlResolver(),
        DataLoaderService::class => static fn() => new DataLoaderService(),
        LanguageService::class => static fn() => new LanguageService(),
        SeoService::class => static fn() => new SeoService(),
        TemplateDataBuilder::class => static fn() => new TemplateDataBuilder(),

        LoggerInterface::class => static function () use ($settings): LoggerInterface {
            $logDir = (string) ($settings['paths']['logs'] ?? '');
            if ($logDir !== '' && !is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $logger = new Logger('app');
            $logFile = rtrim($logDir, '/') . '/app.log';
            $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
            return $logger;
        },

        Twig::class => static function (ContainerInterface $container) use ($settings): Twig {
            $baseDir = (string) $settings['project_root'];
            $baseUrl = rtrim((string) ($_SERVER['APP_BASE_URL'] ?? ''), '/');
            if ($baseUrl === '') {
                $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
                $basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
                $baseUrl = $https . $host . $basePath;
            }
            $baseUrl .= '/';

            $twig = Twig::create((string) $settings['paths']['templates'], $settings['twig']);
            $env = $twig->getEnvironment();
            $env->addExtension(new StringLoaderExtension());
            $env->addExtension(new AssetExtension($baseDir, $baseUrl));
            $env->addExtension(new UrlExtension($baseUrl));
            $env->addExtension(new DataExtension($baseDir, $baseUrl));

            if (!empty($settings['twig']['debug'])) {
                $env->addExtension(new DebugExtension());
            }

            $global = $container->get(DataLoaderService::class)->loadGlobal(
                (string) $settings['paths']['json_global'],
                $baseUrl
            );
            $env->addGlobal('base_url', $baseUrl);
            $env->addGlobal('global', $global);

            return $twig;
        },

        TrailingSlashMiddleware::class => static fn(ContainerInterface $c) => new TrailingSlashMiddleware(
            $c->get(ResponseFactoryInterface::class)
        ),
        RedirectMiddleware::class => static fn(ContainerInterface $c) => new RedirectMiddleware(
            $c->get('settings'),
            $c->get(ResponseFactoryInterface::class),
            $c->get(BaseUrlResolver::class)
        ),
        LanguageMiddleware::class => static fn(ContainerInterface $c) => new LanguageMiddleware(
            $c->get('settings'),
            $c->get(DataLoaderService::class),
            $c->get(LanguageService::class),
            $c->get(BaseUrlResolver::class)
        ),
        PageAction::class => static fn(ContainerInterface $c) => new PageAction(
            $c->get(Twig::class),
            $c->get(DataLoaderService::class),
            $c->get(SeoService::class),
            $c->get(TemplateDataBuilder::class),
            $c->get('settings')
        ),
    ]);

    return $builder->build();
};
