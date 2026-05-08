<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Реестр SEO-билдеров по типу коллекции.
 *
 * Регистрируется в DI с явным списком билдеров. PageAction вместо
 * service-locator-доступа к контейнеру получает этот реестр и
 * вызывает get() по entityType (имя коллекции из settings.collections).
 */
final class SeoBuilderRegistry
{
    /**
     * @param array<string, SeoBuilderInterface> $builders Карта entityType → SeoBuilderInterface
     */
    public function __construct(
        private array $builders = []
    ) {
    }

    public function get(string $type): ?SeoBuilderInterface
    {
        return $this->builders[$type] ?? null;
    }
}
