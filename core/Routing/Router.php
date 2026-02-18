<?php
namespace App\Routing;

class Router
{
    private ?string $pageId = null;
    /** @var string[] */
    private array $routeParams = [];

    private const ROUTE_MAP = [
        'restaurants' => 'restaurants-list',
    ];

    /**
     * Примитивное определение page_id и параметров по сегментам
     */
    public function resolveRoute(array $segments): void
    {
        $this->pageId = 'index';
        $this->routeParams = [];
        if (!empty($segments)) {
            $slug = (string)$segments[0];
            $this->pageId = self::ROUTE_MAP[$slug] ?? $slug;
            $this->routeParams = array_slice($segments, 1);
        }
    }

    public function getPageId(): string
    {
        return $this->pageId ?? 'index';
    }

    /**
     * @return string[]
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }
}

