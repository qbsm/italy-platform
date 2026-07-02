<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Контракт для построителя SEO-данных конкретной коллекции.
 *
 * PageAction вызывает реализацию, FQCN которой указан в `collections.{key}.seo_builder`.
 * Возвращает массив того же формата, что и SEO-JSON в data/json/{lang}/seo/*.json
 * (title, meta[], json_ld, json_ld_faq).
 */
interface SeoBuilderInterface
{
    /**
     * @param array<string,mixed> $entity   Загруженная сущность (с ключом slug)
     * @param string              $baseUrl  Базовый URL текущего окружения
     * @param string              $langCode Код языка
     * @param array<string,mixed> $config   Конфиг коллекции из settings
     * @param array<string,mixed> $global   global.json
     * @return array<string,mixed>
     */
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array;
}
