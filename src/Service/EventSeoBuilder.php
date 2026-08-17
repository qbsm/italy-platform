<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Строит SEO-данные для страницы события (og + Schema.org/Event).
 *
 * Дата берётся из event.date.iso, площадка — из event.restaurant, цена — из event.priceFrom.
 * Организатор ссылается на Organization из base.twig по @id, чтобы сущности не задваивались.
 */
final class EventSeoBuilder implements SeoBuilderInterface
{
    public function __construct(private readonly string $projectRoot = '') {}

    private const MONTHS_GENITIVE = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $e = is_array($entity['event'] ?? null) ? $entity['event'] : [];
        $name = trim((string) ($e['title'] ?? $entity['slug'] ?? ''));
        $desc = $this->resolveDescription($entity);
        $slug = (string) ($entity['slug'] ?? '');

        $prodBase = (string) ($config['prod_base_url'] ?? rtrim($baseUrl, '/'));
        $urlPattern = (string) ($config['entity_url_pattern'] ?? '/{slug}');
        $url = $prodBase . str_replace('{slug}', $slug, $urlPattern);

        $siteName = (string) ($config['site_name'] ?? 'Site');
        $images = $this->collectImages($entity, $prodBase, $config);
        $ogImage = $this->socialImage($images[0] ?? '', $prodBase, $config);

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:url', 'content' => $url],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'event')],
            ['property' => 'og:title', 'content' => $name],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:site_name', 'content' => $siteName],
        ];
        if ($ogImage !== '') {
            $meta[] = ['property' => 'og:image', 'content' => $ogImage];
            $meta[] = ['property' => 'og:image:secure_url', 'content' => $ogImage];
            $meta[] = ['property' => 'og:image:type', 'content' => $this->imageMimeFromPath($ogImage)];
            if (str_ends_with($ogImage, '-og.jpg')) {
                $meta[] = ['property' => 'og:image:width', 'content' => '1200'];
                $meta[] = ['property' => 'og:image:height', 'content' => '630'];
            }
        }

        return [
            'title' => $this->resolveTitle($entity, $name, $siteName),
            'meta' => $meta,
            'json_ld' => $this->buildEventJsonLd($entity, $desc, $images, $url, $prodBase),
        ];
    }

    /**
     * Заголовок: явный metaTitle, иначе «Название — 23 августа 2026, площадка».
     * Дата и площадка вытаскивают событие из брендового запроса в запрос по поводу.
     *
     * @param array<string,mixed> $entity
     */
    private function resolveTitle(array $entity, string $name, string $siteName): string
    {
        $metaTitle = trim((string) ($entity['metaTitle'] ?? ''));
        if ($metaTitle !== '') {
            return $metaTitle;
        }
        if ($name === '') {
            return $siteName;
        }

        $e = is_array($entity['event'] ?? null) ? $entity['event'] : [];
        $parts = [];

        $human = $this->humanDate($e['date'] ?? []);
        if ($human !== '') {
            $parts[] = $human;
        }

        $place = trim((string) ($e['restaurant']['name'] ?? ''));
        if ($place !== '') {
            $parts[] = $place;
        }

        return $parts === [] ? $name . ' — ' . $siteName : $name . ' — ' . implode(', ', $parts);
    }

    /** @param mixed $date */
    private function humanDate($date): string
    {
        if (!is_array($date)) {
            return '';
        }

        $iso = trim((string) ($date['iso'] ?? ''));
        if ($iso !== '') {
            $ts = strtotime($iso);
            if ($ts !== false) {
                $month = self::MONTHS_GENITIVE[(int) date('n', $ts)];
                return trim(date('j', $ts) . ' ' . $month . ' ' . date('Y', $ts));
            }
        }

        $day = trim((string) ($date['day'] ?? ''));
        $month = trim((string) ($date['month'] ?? ''));

        return $day !== '' && $month !== '' ? $day . ' ' . $month : '';
    }

    /** @param array<string,mixed> $entity */
    private function resolveDescription(array $entity): string
    {
        $metaDescription = trim((string) ($entity['metaDescription'] ?? ''));
        if ($metaDescription !== '') {
            return $metaDescription;
        }

        $lead = $entity['lead'] ?? '';
        if (is_array($lead)) {
            $lead = implode(' ', array_map('strval', $lead));
        }

        return trim((string) $lead);
    }

    /**
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private function collectImages(array $entity, string $prodBase, array $config): array
    {
        $images = [];
        $covers = $entity['covers'] ?? [];
        if (is_array($covers)) {
            foreach ($covers as $cover) {
                $src = is_array($cover) ? (string) ($cover['src'] ?? '') : '';
                if ($src === '') {
                    continue;
                }
                $images[] = $this->absolutize($src, $prodBase);
            }
        }

        if ($images === []) {
            $fallback = (string) ($config['fallback_og_image'] ?? '');
            if ($fallback !== '') {
                $images[] = $this->absolutize($fallback, $prodBase);
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Картинка для соцсетей и мессенджеров — строго JPEG 1200x630: Telegram не разворачивает
     * WebP в превью, а произвольная пропорция обложки обрезается сервисом по живому.
     * Файлы готовит tools/build/build-og-images.js рядом с исходником, суффикс -og.jpg.
     *
     * @param array<string,mixed> $config
     */
    private function socialImage(string $image, string $prodBase, array $config): string
    {
        $fallback = trim((string) ($config['fallback_og_image'] ?? ''));
        $fallbackUrl = $fallback !== '' ? $this->absolutize($fallback, $prodBase) : '';

        if ($image === '') {
            return $fallbackUrl;
        }

        $candidate = preg_replace('/\.(jpe?g|png|webp|avif)$/i', '-og.jpg', $image);
        if ($candidate !== null && $candidate !== $image && $this->existsLocally($candidate, $prodBase)) {
            return $candidate;
        }

        // Исходник годится только если он уже JPEG — иначе превью в мессенджере не раскроется
        if (preg_match('/\.jpe?g($|\?)/i', $image) === 1) {
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

    private function absolutize(string $src, string $prodBase): string
    {
        // Пути из JSON уже абсолютизированы текущим хостом — приводим к прод-домену,
        // иначе в og уедет адрес стенда, на котором отрисовали страницу.
        $pos = strpos($src, '/data/');
        if ($pos !== false) {
            return $prodBase . substr($src, $pos);
        }
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }
        return $prodBase . '/' . ltrim($src, '/');
    }

    private function imageMimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'image/jpeg',
        };
    }

    /**
     * @param array<string,mixed> $entity
     * @param array<int,string> $images
     */
    private function buildEventJsonLd(array $entity, string $description, array $images, string $url, string $prodBase): ?string
    {
        $e = is_array($entity['event'] ?? null) ? $entity['event'] : [];
        $name = trim((string) ($e['title'] ?? ''));
        $startDate = trim((string) ($e['date']['iso'] ?? ''));
        if ($name === '' || $startDate === '') {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => $url . '#event',
            'name' => $name,
            'startDate' => $startDate,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
        ];

        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($images !== []) {
            $data['image'] = $images;
        }

        $location = $this->buildLocation($e);
        if ($location !== null) {
            $data['location'] = $location;
        }

        $offers = $this->buildOffers($e, $url);
        if ($offers !== null) {
            $data['offers'] = $offers;
        }

        if ($prodBase !== '') {
            $data['organizer'] = ['@id' => $prodBase . '/#organization'];
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: null;
    }

    /**
     * @param array<string,mixed> $e
     * @return array<string,mixed>|null
     */
    private function buildLocation(array $e): ?array
    {
        $place = is_array($e['restaurant'] ?? null) ? $e['restaurant'] : [];
        $placeName = trim((string) ($place['name'] ?? ''));
        $address = trim((string) ($place['address'] ?? ''));
        if ($placeName === '' && $address === '') {
            return null;
        }

        $location = ['@type' => 'Place'];
        if ($placeName !== '') {
            $location['name'] = $placeName;
        }
        if ($address !== '') {
            $location['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressCountry' => 'RU',
            ];
        }

        return $location;
    }

    /**
     * @param array<string,mixed> $e
     * @return array<string,mixed>|null
     */
    private function buildOffers(array $e, string $url): ?array
    {
        $price = $e['priceFrom'] ?? $e['price'] ?? null;
        if (!is_numeric($price)) {
            return null;
        }

        $ticketUrl = trim((string) ($e['ticketUrl'] ?? ''));
        $seatsLeft = $e['seatsLeft'] ?? null;
        $soldOut = is_numeric($seatsLeft) && (int) $seatsLeft <= 0;

        return [
            '@type' => 'Offer',
            'price' => (string) $price,
            'priceCurrency' => $this->currencyCode((string) ($e['currency'] ?? '')),
            'availability' => $soldOut ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
            'url' => $ticketUrl !== '' ? $ticketUrl : $url,
        ];
    }

    private function currencyCode(string $currency): string
    {
        return match (trim($currency)) {
            '$', 'USD' => 'USD',
            '€', 'EUR' => 'EUR',
            default => 'RUB',
        };
    }
}
