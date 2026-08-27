<?php

declare(strict_types=1);

/**
 * Синхронизация часов работы ресторанов с системой доставки.
 *
 * Источник — поле openHours карточки заведения: именно оно совпадает с тем, что видит
 * гость в Яндекс.Картах. serviceHours внутри services брать нельзя, это окна доступности
 * брони и доставки: у бара «на месте» там 16:00, хотя заведение открыто с 10:00.
 *
 * Карточка сайта связана с заведением полем restaurant.venueId.
 *
 * Если в карточке стоит restaurant.hoursSource = "manual", часы заданы вручную и синхронизация
 * их не трогает: так держим заведения, где расписание в системе доставки отстаёт от реального.
 *
 * Запуск:
 *   php tools/ops/sync-venue-hours.php            — показать расхождения, ничего не менять
 *   php tools/ops/sync-venue-hours.php --apply    — записать часы в JSON
 */

const API_URL = 'https://app.italydomoy.ru/api/v1/venues';
const DAYS = ['mon' => 'Пн', 'tue' => 'Вт', 'wed' => 'Ср', 'thu' => 'Чт', 'fri' => 'Пт', 'sat' => 'Сб', 'sun' => 'Вс'];

$root = dirname(__DIR__, 2);
$args = (array) ($_SERVER['argv'] ?? []);
$apply = in_array('--apply', $args, true);

$raw = @file_get_contents(API_URL, false, stream_context_create([
    'http' => ['timeout' => 20, 'header' => "Accept: application/json\r\n"],
]));

if ($raw === false) {
    fwrite(STDERR, "sync-venue-hours: не удалось получить {" . API_URL . "}\n");
    exit(1);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || !isset($payload['results'])) {
    fwrite(STDERR, "sync-venue-hours: неожиданный ответ API\n");
    exit(1);
}

/** @var array<int, array<string, mixed>> $venues */
$venues = [];
foreach ($payload['results'] as $venue) {
    if (isset($venue['id'])) {
        $venues[(int) $venue['id']] = $venue;
    }
}

/**
 * Складывает подневное расписание в формат сайта: соседние дни с одинаковым временем
 * объединяются в диапазон, как их читает человек.
 *
 * @param array<string, string|null> $hours
 * @return array<int, array{days: string, hours: string}>
 */
function foldHours(array $hours): array
{
    $rows = [];
    foreach (DAYS as $key => $label) {
        $value = $hours[$key] ?? null;
        // «00:00-00:00» в системе означает круглосуточно — так и пишем, а не диапазоном
        $value = is_string($value) ? ($value === '00:00-00:00' ? 'Круглосуточно' : str_replace('-', '–', $value)) : null;
        if ($value === null) {
            continue;
        }
        $last = $rows === [] ? null : $rows[count($rows) - 1];
        if ($last !== null && $last['hours'] === $value && $last['nextIndex'] === array_search($key, array_keys(DAYS), true)) {
            $rows[count($rows) - 1]['last'] = $label;
            $rows[count($rows) - 1]['nextIndex']++;
            continue;
        }
        $rows[] = [
            'first' => $label,
            'last' => $label,
            'hours' => $value,
            'nextIndex' => (int) array_search($key, array_keys(DAYS), true) + 1,
        ];
    }

    $out = [];
    foreach ($rows as $row) {
        $days = $row['first'] === $row['last'] ? $row['first'] : $row['first'] . '-' . $row['last'];
        $out[] = ['days' => $days, 'hours' => $row['hours']];
    }

    return $out;
}

/** @param array<int, array{days: string, hours: string}> $rows */
function asText(array $rows): string
{
    return implode('; ', array_map(static fn(array $r): string => $r['days'] . ' ' . $r['hours'], $rows));
}

$changed = $same = $skipped = 0;
$report = [];

foreach (glob($root . '/data/json/ru/restaurants/*.json') ?: [] as $file) {
    $slug = basename($file, '.json');
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['restaurant'])) {
        continue;
    }

    if (($data['restaurant']['hoursSource'] ?? null) === 'manual') {
        $skipped++;
        $report[] = sprintf('  %-26s часы заданы вручную, система не перезаписывает', $slug);
        continue;
    }

    $venueId = $data['restaurant']['venueId'] ?? null;
    if ($venueId === null) {
        $skipped++;
        $report[] = sprintf('  %-26s не привязан к заведению', $slug);
        continue;
    }

    $venue = $venues[(int) $venueId] ?? null;
    if ($venue === null) {
        $skipped++;
        $report[] = sprintf('  %-26s заведения %d нет в ответе API', $slug, $venueId);
        continue;
    }

    $fresh = foldHours(is_array($venue['openHours'] ?? null) ? $venue['openHours'] : []);
    if ($fresh === []) {
        $skipped++;
        $report[] = sprintf('  %-26s в API нет часов', $slug);
        continue;
    }

    $current = $data['restaurant']['openingHours'] ?? [];
    if (asText(is_array($current) ? $current : []) === asText($fresh)) {
        $same++;
        continue;
    }

    $changed++;
    $report[] = sprintf(
        "  %-26s\n      было:  %s\n      стало: %s",
        $slug,
        asText(is_array($current) ? $current : []) ?: '—',
        asText($fresh)
    );

    if ($apply) {
        $data['restaurant']['openingHours'] = $fresh;
        file_put_contents(
            $file,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"
        );
    }
}

echo $apply ? "Часы обновлены из системы\n\n" : "Сверка часов с системой (изменения не записаны)\n\n";
echo implode("\n", $report), $report === [] ? '' : "\n\n";
printf("совпадает: %d | расходится: %d | пропущено: %d\n", $same, $changed, $skipped);

exit($changed > 0 && !$apply ? 2 : 0);
