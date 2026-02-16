<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UrlExtension extends AbstractExtension
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', [$this, 'generateUrl']),
        ];
    }

    public function generateUrl(?string $path = ''): string
    {
        if ($path === null) {
            return '#';
        }
        // Пропускаем абсолютные URL, якоря, tel/mailto
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0
            || strpos($path, '#') === 0 || strpos($path, 'tel:') === 0
            || strpos($path, 'mailto:') === 0) {
            return $path;
        }

        $trimmedPath = ltrim($path, '/');

        // Добавляем trailing slash если нет расширения файла
        if ($trimmedPath !== '' && strpos(basename($trimmedPath), '.') === false) {
            $trimmedPath = rtrim($trimmedPath, '/') . '/';
        }

        return $this->baseUrl . $trimmedPath;
    }
}
