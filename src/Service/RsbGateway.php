<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Клиент эквайринга Русский Стандарт (RSB ecomm2 / RBS-платформа).
 * Порт рабочего механизма с tasteproject.ru: mutual-TLS клиентским сертификатом,
 * command=v — регистрация транзакции, command=c — запрос статуса. Ответ банка —
 * текст вида "KEY: value" построчно.
 *
 * @param array{
 *   enabled: bool, env: string, merchant_url: string, client_url: string,
 *   cert: string, key: string, ca: string, currency: string, description: string,
 *   ofd_group: string, ofd_tax_id: int, orders_dir: string, timeout: int
 * } $config
 */
final class RsbGateway
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function clientRedirectUrl(string $transactionId): string
    {
        return (string) $this->config['client_url'] . '?' . http_build_query(['trans_id' => $transactionId]);
    }

    /**
     * Регистрация транзакции (command=v). Возвращает transaction_id банка.
     *
     * @param array{amount:int,orderId:string,clientIp:string,phone:string,email:string,tickets:int,unitKopecks:int} $order
     */
    public function register(array $order, string $requestId = ''): ?string
    {
        $basket = [
            'Lines' => [
                [
                    'Qty' => $order['tickets'] * 1000,   // количество в тысячных долях (1 шт = 1000)
                    'Price' => $order['unitKopecks'],    // цена за единицу в копейках
                    'PayAttribute' => 4,                 // 4 = полная оплата
                    'TaxId' => (int) $this->config['ofd_tax_id'],
                    'Description' => 'Электронный билет',
                ],
            ],
        ];

        $params = [
            'command' => 'v',
            'amount' => $order['amount'],
            'currency' => (string) $this->config['currency'],
            'client_ip_addr' => $order['clientIp'],
            'description' => (string) $this->config['description'],
            'mrch_transaction_id' => $order['orderId'],
            'language' => 'ru',
            'server_version' => '2.0',
            'phone_client' => $order['phone'],
            'email_client' => $order['email'],
            'email' => $order['email'],               // email для фискального чека
            'Group' => (string) $this->config['ofd_group'],
            'basket' => json_encode($basket, JSON_UNESCAPED_UNICODE),
        ];

        $raw = $this->post($params, $requestId);
        if ($raw === null) {
            return null;
        }

        $fields = $this->parse($raw);
        $transactionId = $fields['TRANSACTION_ID'] ?? '';
        if ($transactionId === '') {
            $this->logger->warning('RSB: регистрация без TRANSACTION_ID', [
                'request_id' => $requestId,
                'response' => mb_substr($raw, 0, 500),
            ]);
            return null;
        }

        return $transactionId;
    }

    /**
     * Запрос статуса транзакции (command=c).
     *
     * @return array{result:string, mrchTransactionId:string, fields:array<string,string>}
     */
    public function status(string $transactionId, string $clientIp, string $requestId = ''): array
    {
        $params = [
            'command' => 'c',
            'trans_id' => $transactionId,
            'client_ip_addr' => $clientIp,
            'server_version' => '2.0',
        ];

        $raw = $this->post($params, $requestId);
        $fields = $raw !== null ? $this->parse($raw) : [];

        return [
            'result' => $fields['RESULT'] ?? '',
            'mrchTransactionId' => $fields['MRCH_TRANSACTION_ID'] ?? '',
            'fields' => $fields,
        ];
    }

    /**
     * POST на MerchantHandler с mutual-TLS. Возвращает сырой ответ или null при ошибке транспорта.
     *
     * @param array<string,string|int> $params
     */
    private function post(array $params, string $requestId): ?string
    {
        foreach (['cert', 'key', 'ca'] as $f) {
            $path = (string) ($this->config[$f] ?? '');
            if ($path === '' || !is_readable($path)) {
                $this->logger->error('RSB: недоступен файл сертификата', ['request_id' => $requestId, 'file' => $f, 'path' => $path]);
                return null;
            }
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => (string) $this->config['merchant_url'],
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 Firefox/1.0.7',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT => (string) $this->config['cert'],
            CURLOPT_SSLKEY => (string) $this->config['key'],
            CURLOPT_CAINFO => (string) $this->config['ca'],
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $result = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno !== 0 || !is_string($result)) {
            $this->logger->error('RSB: ошибка транспорта', [
                'request_id' => $requestId,
                'command' => $params['command'] ?? '',
                'errno' => $errno,
                'error' => $error,
                'http_code' => $httpCode,
            ]);
            return null;
        }

        return $result;
    }

    /**
     * Парсинг ответа банка "KEY: value" построчно.
     *
     * @return array<string,string>
     */
    private function parse(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = strtoupper(trim($key));
            if ($key !== '') {
                $out[$key] = trim($value);
            }
        }
        return $out;
    }
}
