<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Строит SEO-данные для страницы ресторана (og + Schema.org/Restaurant + FAQPage).
 *
 * og:image берётся из covers[0]; в URL подставляется prod-домен (см. config/project.php или settings).
 * FAQ собирается из global['restaurant-faq'] по полям address/openingHours/cuisine/contact/price.
 */
final class RestaurantSeoBuilder implements SeoBuilderInterface
{
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $r = $entity['restaurant'] ?? [];
        $name = (string) ($r['name'] ?? $entity['slug'] ?? '');
        $desc = (string) ($entity['desc']['short'] ?? $entity['desc']['full'] ?? '');
        $slug = (string) ($entity['slug'] ?? '');

        $prodBase = (string) ($config['prod_base_url'] ?? rtrim($baseUrl, '/'));
        $urlPattern = (string) ($config['entity_url_pattern'] ?? '/{slug}');
        $url = $prodBase . str_replace('{slug}', $slug, $urlPattern);

        $siteName = (string) ($config['site_name'] ?? 'Site');

        $coverSrc = null;
        if (!empty($entity['covers']) && is_array($entity['covers'])) {
            $first = $entity['covers'][0];
            if (is_array($first) && !empty($first['src'])) {
                $coverSrc = (string) $first['src'];
            }
        }
        $fallbackImage = (string) ($config['fallback_og_image'] ?? '/data/img/seo/og.webp');
        if ($coverSrc !== null) {
            $pos = strpos($coverSrc, '/data/');
            $relPath = $pos !== false ? substr($coverSrc, $pos) : '/' . ltrim($coverSrc, '/');
            $ogImage = $prodBase . $relPath;
        } else {
            $ogImage = $prodBase . '/' . ltrim($fallbackImage, '/');
        }

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:url', 'content' => $url],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'website')],
            ['property' => 'og:title', 'content' => $name],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:site_name', 'content' => $siteName],
            ['property' => 'og:image', 'content' => $ogImage],
            ['property' => 'og:image:secure_url', 'content' => $ogImage],
        ];

        return [
            'title' => $name,
            'meta' => $meta,
            'json_ld' => $this->buildRestaurantJsonLd($entity),
            'json_ld_faq' => $this->buildRestaurantFaqJsonLd($entity, $langCode, $global),
        ];
    }

    /** @param array<string,mixed> $entity */
    private function buildRestaurantJsonLd(array $entity): string
    {
        $r = $entity['restaurant'] ?? [];
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $r['name'] ?? null,
            'telephone' => isset($r['telephone']['title']) ? $r['telephone']['title'] : null,
            'address' => isset($r['address']) ? $r['address'] : null,
            'geo' => $r['geo'] ?? null,
            'url' => $r['url'] ?? null,
            'priceRange' => $r['priceRange'] ?? null,
            'hasMap' => $r['hasMap'] ?? null,
            'menu' => $r['menuLink'] ?? null,
        ];
        if (!empty($r['openingHours']) && is_array($r['openingHours'])) {
            $ld['openingHours'] = array_map(static function ($h) {
                return trim(($h['days'] ?? '') . ' ' . ($h['hours'] ?? ''));
            }, $r['openingHours']);
        }
        if (!empty($r['servesCuisine'])) {
            $ld['servesCuisine'] = $r['servesCuisine'];
        }
        $ld = array_filter($ld, static fn ($v) => $v !== null && $v !== '');
        return (string) json_encode($ld, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $global
     */
    private function buildRestaurantFaqJsonLd(array $entity, string $langCode, array $global): ?string
    {
        $r = $entity['restaurant'] ?? [];
        $faq = $global['restaurant-faq'][$langCode] ?? $global['restaurant-faq']['ru'] ?? null;
        if (!is_array($faq)) {
            return null;
        }
        $mainEntity = [];
        if (!empty($r['address'])) {
            $addr = $r['address'];
            $locality = isset($addr['addressLocality']) && (string) $addr['addressLocality'] !== '' ? ', ' . $addr['addressLocality'] : '';
            $answer = trim(($addr['streetAddress'] ?? '') . $locality);
            if ($answer !== '' && isset($faq['where'])) {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['where'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }
        if (!empty($r['openingHours']) && is_array($r['openingHours']) && isset($faq['hours'])) {
            $parts = array_map(static function ($h) {
                return trim(($h['days'] ?? '') . ' ' . ($h['hours'] ?? ''));
            }, $r['openingHours']);
            $answer = implode('; ', array_filter($parts));
            if ($answer !== '') {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['hours'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }
        if (!empty($r['servesCuisine']) && is_array($r['servesCuisine']) && isset($faq['cuisine'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['cuisine'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => implode(', ', $r['servesCuisine'])],
            ];
        }
        if (!empty($r['telephone']['title']) && isset($faq['contact'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['contact'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $r['telephone']['title']],
            ];
        }
        if (!empty($r['priceRange']) && isset($faq['price'])) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['price'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $r['priceRange']],
            ];
        }
        if ($mainEntity === []) {
            return null;
        }
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
        return (string) json_encode($ld, JSON_UNESCAPED_UNICODE);
    }
}
