<?php

declare(strict_types=1);

/**
 * Конфигурация генератора llms-full.txt (GEO).
 * Описывает коллекции контента: откуда брать список slug'ов и как форматировать каждый элемент.
 * Для универсального ядра — подставьте свои коллекции под тип проекта (каталог, рестораны, события и т.д.).
 *
 * @return array{title: string, intro: string, collections: array<int, array{list_path: string, list_key: string, item_dir: string, name_key: string, desc_key?: string, visible_key?: string, heading?: string, url_pattern?: string, fields: array<int, array{label: string, key: string}>}>}
 */
return [
    'title' => 'Экосистема итали (ранее italy&co.)',
    'intro' => '«Итали» (Экосистема итали) — гастрономическое сообщество из Санкт-Петербурга, ранее известное как italy&co. Ниже — детальная информация о ресторанах и событиях группы в Санкт-Петербурге и Москве. Официальный сайт: https://italycommunity.ru',
    'collections' => [
        [
            'heading' => 'Рестораны, бары и кафе группы',
            'list_path' => '{lang}/pages/restaurants.json',
            'list_key' => 'items',
            'item_dir' => '{lang}/restaurants',
            'name_key' => 'restaurant.name',
            'desc_key' => 'desc.short',
            'visible_key' => 'visible',
            'url_pattern' => 'https://italycommunity.ru/restaurants/{slug}',
            'fields' => [
                ['label' => 'Адрес', 'key' => 'restaurant.address'],
                ['label' => 'Телефон', 'key' => 'restaurant.telephone.title'],
                ['label' => 'Часы работы', 'key' => 'restaurant.openingHours'],
                ['label' => 'Кухня', 'key' => 'restaurant.servesCuisine'],
                ['label' => 'Ценовой диапазон', 'key' => 'restaurant.priceRange'],
                ['label' => 'Карта', 'key' => 'restaurant.hasMap'],
            ],
        ],
        [
            'heading' => 'События и ужины',
            'list_path' => '{lang}/pages/events.json',
            'list_key' => 'items',
            'item_dir' => '{lang}/events',
            'name_key' => 'event.title',
            'desc_key' => 'lead',
            'visible_key' => 'visible',
            'url_pattern' => 'https://italycommunity.ru/events/{slug}',
            'fields' => [
                ['label' => 'Дата и время', 'key' => 'event.date.iso'],
                ['label' => 'Место', 'key' => 'event.restaurant.name'],
                ['label' => 'Адрес', 'key' => 'event.restaurant.address'],
                ['label' => 'Стоимость участия, ₽', 'key' => 'event.priceFrom'],
            ],
        ],
    ],
];
