<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Проверка подлинности callback-уведомления платёжного шлюза (симметричная схема).
 *
 * Шлюз считает контрольную сумму HMAC-SHA256 по строке `имя;значение;` из всех параметров
 * уведомления, кроме checksum и sign_alias, отсортированных по имени в прямом алфавитном
 * порядке. Ключ — callback-токен из личного кабинета банка.
 */
final class CallbackVerifier
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function isEnabled(): bool
    {
        return $this->token !== '';
    }

    /**
     * @param array<string,mixed> $params Query-параметры уведомления
     */
    public function verify(array $params): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $checksum = isset($params['checksum']) && is_string($params['checksum']) ? $params['checksum'] : '';
        if ($checksum === '') {
            return false;
        }

        unset($params['checksum'], $params['sign_alias']);
        ksort($params, SORT_STRING);

        $data = '';
        foreach ($params as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $data .= $name . ';' . (string) $value . ';';
        }

        return hash_equals(strtoupper(hash_hmac('sha256', $data, $this->token)), strtoupper($checksum));
    }
}
