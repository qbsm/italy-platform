<?php
namespace App\Routing;

class Router
{
    private ?string $pageId = null;
    /** @var string[] */
    private array $routeParams = [];

    /**
     * Примитивное определение page_id и параметров по сегментам
     */
    public function resolveRoute(array $segments): void
    {
        $this->pageId = 'index';
        $this->routeParams = [];
        if (!empty($segments)) {
            $this->pageId = (string)$segments[0];
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

