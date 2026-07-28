<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Алерты об ошибках оплаты/отправки почты в Telegram-группу «Итали»
 * («Обновление сайта italy&co.»). Telegram на сервере закрыт напрямую —
 * запросы идут через локальный xray-прокси (TG_ALERT_PROXY).
 *
 * Сбой алерта никогда не ломает основной сценарий — только warning в лог.
 */
final class TelegramAlertService
{
    /**
     * @param array<string, string> $config token, chat_id, proxy, site
     */
    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(string $text, string $requestId = ''): bool
    {
        $token = $this->config['token'] ?? '';
        $chatId = $this->config['chat_id'] ?? '';
        if ($token === '' || $chatId === '') {
            return false; // алерты не сконфигурированы — тихо выходим
        }

        $site = $this->config['site'] ?? '';
        $message = trim(($site !== '' ? "⚠️ {$site}\n" : "⚠️ ") . $text
            . ($requestId !== '' ? "\nrequest_id: {$requestId}" : ''));

        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROXY => $this->config['proxy'] ?? '',
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $httpCode !== 200) {
            $this->logger->warning('Не удалось отправить Telegram-алерт', [
                'errno' => $errno,
                'http_code' => $httpCode,
                'response' => is_string($response) ? mb_substr($response, 0, 200) : null,
            ]);
            return false;
        }

        return true;
    }
}
