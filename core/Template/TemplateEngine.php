<?php
namespace App\Template;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TemplateEngine
{
    private Environment $twig;

    public function initialize(string $baseDir, string $baseUrl, array $settings = []): void
    {
        $templatesDir = rtrim($baseDir, '/') . '/templates';
        $loader = new FilesystemLoader($templatesDir);

        $this->twig = new Environment($loader, [
            'cache' => $settings['twig_cache'] ?? false,
            'debug' => $settings['debug'] ?? false,
            'auto_reload' => $settings['auto_reload'] ?? true,
        ]);

        if (!empty($settings['debug'])) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Расширения Twig
        if (class_exists('App\\Twig\\AssetExtension')) {
            $this->twig->addExtension(new \App\Twig\AssetExtension($baseDir, $baseUrl));
        }
        if (class_exists('App\\Twig\\UrlExtension')) {
            $this->twig->addExtension(new \App\Twig\UrlExtension($baseUrl));
        }

        $this->twig->addExtension(new \Twig\Extension\StringLoaderExtension());
        $this->twig->addGlobal('base_url', rtrim($baseUrl, '/') . '/');
    }

    public function render(string $templatePath, array $data = []): string
    {
        return $this->twig->render($templatePath, $data);
    }

    public function templateExists(string $path): bool
    {
        return $this->twig->getLoader()->exists($path);
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
