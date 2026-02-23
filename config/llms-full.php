<?php

declare(strict_types=1);

/**
 * Конфигурация генератора llms-full.txt (GEO).
 * Описывает коллекции контента: откуда брать список slug'ов и как форматировать каждый элемент.
 * Для универсального ядра — подставьте свои коллекции под тип проекта (каталог, рестораны, события и т.д.).
 *
 * @return array{title: string, intro: string, collections: array<int, array{list_path: string, list_key: string, item_dir: string, name_key: string, desc_key?: string, visible_key?: string, fields: array<int, array{label: string, key: string}>}>}
 */
return [
    'title' => 'iSmart Platform',
    'intro' => 'Детальная информация о разделах и страницах платформы.',
    'collections' => [
        [
            'list_path' => '{lang}/pages/restaurants.json',
            'list_key' => 'items',
            'item_dir' => '{lang}/restaurants',
            'name_key' => 'restaurant.name',
            'desc_key' => 'desc.short',
            'visible_key' => 'visible',
            'fields' => [
                ['label' => 'Адрес', 'key' => 'restaurant.address'],
                ['label' => 'Телефон', 'key' => 'restaurant.telephone.title'],
                ['label' => 'Часы работы', 'key' => 'restaurant.openingHours'],
                ['label' => 'Кухня', 'key' => 'restaurant.servesCuisine'],
                ['label' => 'Ценовой диапазон', 'key' => 'restaurant.priceRange'],
                ['label' => 'Карта', 'key' => 'restaurant.hasMap'],
            ],
        ],
    ],
];
