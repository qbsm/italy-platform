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
    public function __construct(private readonly string $projectRoot = '') {}

    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $r = $entity['restaurant'] ?? [];
        $name = (string) ($r['name'] ?? $entity['slug'] ?? '');
        $desc = $this->resolveDescription($entity);
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
        $ogImage = $this->socialImage($ogImage, $prodBase, $fallbackImage);

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:url', 'content' => $url],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'website')],
            ['property' => 'og:title', 'content' => $name],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:site_name', 'content' => $siteName],
            ['property' => 'og:image', 'content' => $ogImage],
            ['property' => 'og:image:secure_url', 'content' => $ogImage],
            ['property' => 'og:image:type', 'content' => $this->imageMimeFromPath($ogImage)],
        ];

        $metaTitle = trim((string) ($entity['metaTitle'] ?? ''));
        $title = $metaTitle !== ''
            ? $metaTitle
            : ($name !== '' ? $name . ' — ' . $siteName : $siteName);

        $images = $this->collectImages($entity, $prodBase, $ogImage);

        return [
            'title' => $title,
            'meta' => $meta,
            'json_ld' => $this->buildRestaurantJsonLd($entity, $desc, $images, $url, $prodBase),
            'json_ld_faq' => $this->buildRestaurantFaqJsonLd($entity, $langCode, $global),
        ];
    }

    /**
     * Картинка для соцсетей и мессенджеров — строго JPEG 1200x630: Telegram не разворачивает
     * WebP в превью, а обложка ресторана снята в своей пропорции и обрезается сервисом по живому.
     * Файлы готовит tools/build/build-og-images.js рядом с исходником, суффикс -og.jpg.
     */
    private function socialImage(string $image, string $prodBase, string $fallbackImage): string
    {
        $fallbackUrl = $fallbackImage !== '' ? $prodBase . '/' . ltrim($fallbackImage, '/') : '';

        $candidate = preg_replace('/\\.(jpe?g|png|webp|avif)$/i', '-og.jpg', $image);
        if ($candidate !== null && $candidate !== $image && $this->existsLocally($candidate, $prodBase)) {
            return $candidate;
        }

        if (preg_match('/\\.jpe?g($|\\?)/i', $image) === 1) {
            return $image;
        }

        return $fallbackUrl !== '' ? $fallbackUrl : $image;
    }

    private function existsLocally(string $url, string $prodBase): bool
    {
        if ($this->projectRoot === '') {
            return false;
        }
        $relative = $prodBase !== '' && str_starts_with($url, $prodBase)
            ? substr($url, strlen($prodBase))
            : (string) parse_url($url, PHP_URL_PATH);

        $relative = '/' . ltrim((string) $relative, '/');

        return is_file($this->projectRoot . $relative);
    }

    /**
     * Источник meta description: явное seo-поле metaDescription, иначе короткое, иначе полное описание.
     * desc.full может быть массивом абзацев — склеиваем. Не зависит от текста на странице.
     *
     * @param array<string,mixed> $entity
     */
    private function resolveDescription(array $entity): string
    {
        $metaDescription = trim((string) ($entity['metaDescription'] ?? ''));
        if ($metaDescription !== '') {
            return $metaDescription;
        }

        $short = trim((string) ($entity['desc']['short'] ?? ''));
        if ($short !== '') {
            return $short;
        }

        $full = $entity['desc']['full'] ?? '';
        if (is_array($full)) {
            $full = implode(' ', array_map('strval', $full));
        }

        return trim((string) $full);
    }

    /**
     * Все обложки ресторана как абсолютные URL (Google предпочитает несколько изображений).
     *
     * @param array<string,mixed> $entity
     * @return array<int,string>
     */
    private function collectImages(array $entity, string $prodBase, string $fallback): array
    {
        $images = [];
        if (!empty($entity['covers']) && is_array($entity['covers'])) {
            foreach ($entity['covers'] as $cover) {
                $src = is_array($cover) ? (string) ($cover['src'] ?? '') : '';
                if ($src === '') {
                    continue;
                }
                if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
                    $images[] = $src;
                    continue;
                }
                $pos = strpos($src, '/data/');
                $relPath = $pos !== false ? substr($src, $pos) : '/' . ltrim($src, '/');
                $images[] = $prodBase . $relPath;
            }
        }
        if ($images === [] && $fallback !== '') {
            $images[] = $fallback;
        }

        return array_values(array_unique($images));
    }

    /**
     * @param array<string,mixed> $entity
     * @param array<int,string> $images
     */
    private function buildRestaurantJsonLd(array $entity, string $description = '', array $images = [], string $url = '', string $prodBase = ''): string
    {
        $r = $entity['restaurant'] ?? [];
        $selfUrl = $r['url'] ?? ($url !== '' ? $url : null);
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            '@id' => $selfUrl !== null ? $selfUrl . '#restaurant' : null,
            'name' => $r['name'] ?? null,
            'description' => $description !== '' ? $description : null,
            'image' => $images !== [] ? $images : null,
            'telephone' => $r['telephone']['title'] ?? null,
            'address' => $r['address'] ?? null,
            'geo' => $r['geo'] ?? null,
            'url' => $selfUrl,
            'priceRange' => $r['priceRange'] ?? null,
            'currenciesAccepted' => 'RUB',
            'hasMap' => $r['hasMap'] ?? null,
            'menu' => $r['menuLink'] ?? null,
            'acceptsReservations' => !empty($r['bookingPoint']) ? true : null,
            'parentOrganization' => $prodBase !== '' ? ['@id' => $prodBase . '/#organization'] : null,
            'sameAs' => !empty($r['sameAs']) && is_array($r['sameAs']) ? $r['sameAs'] : null,
        ];
        $openingHours = $this->resolveOpeningHours($r);
        if ($openingHours !== []) {
            $ohs = $this->buildOpeningHoursSpecification($openingHours);
            if ($ohs !== []) {
                $ld['openingHoursSpecification'] = $ohs;
            }
        }
        if (!empty($r['servesCuisine'])) {
            $ld['servesCuisine'] = $r['servesCuisine'];
        }
        $ld = array_filter($ld, static fn($v) => $v !== null && $v !== '');
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
        $openingHours = $this->resolveOpeningHours($r);
        if ($openingHours !== [] && isset($faq['hours'])) {
            $parts = array_map(static function ($h) {
                return trim(($h['days'] ?? '') . ' ' . ($h['hours'] ?? ''));
            }, $openingHours);
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

    /**
     * Возвращает актуальный график: если задан openingHoursFrom с датой начала
     * и сегодня уже наступила эта дата — берёт новый список, иначе текущий openingHours.
     *
     * @param array<string,mixed> $r
     * @return array<int,array<string,mixed>>
     */
    private function resolveOpeningHours(array $r): array
    {
        $hours = (!empty($r['openingHours']) && is_array($r['openingHours'])) ? $r['openingHours'] : [];
        $from = $r['openingHoursFrom'] ?? null;
        if (is_array($from) && !empty($from['date']) && !empty($from['list']) && is_array($from['list'])) {
            $switch = strtotime((string) $from['date']);
            if ($switch !== false && date('Ymd') >= date('Ymd', $switch)) {
                $hours = $from['list'];
            }
        }
        return $hours;
    }

    /**
     * Преобразует человекочитаемые часы (RU: «Пн-Чт», «9:00–23:00», «Круглосуточно»)
     * в валидный schema.org OpeningHoursSpecification. Невалидный формат openingHours
     * (русские дни, тире-эндэш) Google молча игнорирует — spec парсится корректно.
     *
     * @param array<int,array<string,mixed>> $openingHours
     * @return array<int,array<string,mixed>>
     */
    private function buildOpeningHoursSpecification(array $openingHours): array
    {
        $order = ['Пн' => 0, 'Вт' => 1, 'Ср' => 2, 'Чт' => 3, 'Пт' => 4, 'Сб' => 5, 'Вс' => 6];
        $names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $pad = static function ($t): ?string {
            $t = trim((string) $t);
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
                return null;
            }
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        };

        $spec = [];
        foreach ($openingHours as $oh) {
            $daysStr = trim((string) ($oh['days'] ?? ''));
            $hoursStr = trim((string) ($oh['hours'] ?? ''));
            if ($daysStr === '' || $hoursStr === '') {
                continue;
            }

            $idx = [];
            foreach (explode(',', $daysStr) as $group) {
                $group = trim($group);
                if ($group === '') {
                    continue;
                }
                $parts = array_map('trim', (array) preg_split('/[-–—]/u', $group));
                if (count($parts) === 2 && isset($order[$parts[0]], $order[$parts[1]])) {
                    $i = $order[$parts[0]];
                    $end = $order[$parts[1]];
                    $guard = 0;
                    while (true) {
                        $idx[] = $i;
                        if ($i === $end || $guard++ > 7) {
                            break;
                        }
                        $i = ($i + 1) % 7;
                    }
                } elseif (isset($order[$group])) {
                    $idx[] = $order[$group];
                }
            }
            $idx = array_values(array_unique($idx));
            if ($idx === []) {
                continue;
            }

            if (mb_stripos($hoursStr, 'круглосуточно') !== false) {
                $opens = '00:00';
                $closes = '23:59';
            } else {
                $hp = array_map('trim', (array) preg_split('/[-–—]/u', $hoursStr));
                if (count($hp) !== 2) {
                    continue;
                }
                $opens = $pad($hp[0]);
                $closes = $pad($hp[1]);
                if ($opens === null || $closes === null) {
                    continue;
                }
                if ($closes === '00:00') {
                    $closes = '23:59';
                }
            }

            $spec[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_map(static fn($i) => $names[$i], $idx),
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $spec;
    }

    /** MIME по расширению пути og:image (для og:image:type). */
    private function imageMimeFromPath(string $path): string
    {
        $ext = strtolower((string) pathinfo((string) parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
