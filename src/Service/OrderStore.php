<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Файловое хранилище заказов билетов (в italy-platform нет БД).
 * Один заказ = один JSON-файл var/orders/{id}.json, ключ — id заказа (hex).
 * mrch_transaction_id, передаваемый банку = id заказа, поэтому по ответу
 * банка (MRCH_TRANSACTION_ID) заказ находится напрямую.
 */
final class OrderStore
{
    public function __construct(
        private readonly string $dir,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null  созданный заказ (с полем id) или null при ошибке записи
     */
    public function create(array $data): ?array
    {
        $id = bin2hex(random_bytes(12)); // 24 hex-символа
        $now = time();
        $order = array_merge($data, [
            'id' => $id,
            'status' => 'new',
            'trans_id' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->write($id, $order) ? $order : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array
    {
        $path = $this->pathFor($id);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,mixed> $patch
     * @return array<string,mixed>|null
     */
    public function update(string $id, array $patch): ?array
    {
        $order = $this->find($id);
        if ($order === null) {
            return null;
        }
        $order = array_merge($order, $patch, ['id' => $id, 'updated_at' => time()]);
        return $this->write($id, $order) ? $order : null;
    }

    /**
     * @param array<string,mixed> $order
     */
    private function write(string $id, array $order): bool
    {
        $path = $this->pathFor($id);
        if ($path === null) {
            return false;
        }
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $json = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            $this->logger->error('OrderStore: не удалось записать заказ', ['id' => $id, 'path' => $path]);
            return false;
        }
        return true;
    }

    private function pathFor(string $id): ?string
    {
        // защита от path traversal — только hex-идентификаторы
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
            return null;
        }
        return rtrim($this->dir, '/') . '/' . $id . '.json';
    }
}
