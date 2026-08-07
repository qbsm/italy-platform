<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Перевод заказа в «оплачен» и уведомление о покупке.
 *
 * Подтверждение приходит двумя путями — возвратом покупателя (PayReturnAction) и
 * callback-уведомлением банка (PayCallbackAction), причём в любом порядке и не по одному разу.
 * Признак «уже оплачен» в самом заказе делает повторные вызовы безвредными: письмо о покупке
 * уходит ровно один раз.
 */
final class OrderConfirmer
{
    public function __construct(
        private readonly OrderStore $orders,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $order
     * @return bool Заказ подтверждён именно этим вызовом
     */
    public function confirm(array $order, string $requestId): bool
    {
        $orderId = (string) ($order['id'] ?? '');
        if ($orderId === '' || ($order['status'] ?? '') === 'paid') {
            return false;
        }

        $this->orders->update($orderId, ['status' => 'paid', 'paid_at' => time()]);
        $this->notify($order, $requestId);

        return true;
    }

    /**
     * Уведомление о покупке на MAIL_TO (переиспользуем существующий канал форм).
     *
     * @param array<string,mixed> $order
     */
    private function notify(array $order, string $requestId): void
    {
        $amount = (int) ($order['amount'] ?? 0);
        $currency = in_array((string) ($order['currency'] ?? '810'), ['810', '643'], true) ? '₽' : (string) $order['currency'];
        $sum = number_format($amount / 100, 2, '.', ' ');
        $eventLine = trim((string) ($order['event_title'] ?? '') . ' — ' . (string) ($order['event_date'] ?? ''), ' —');

        $sent = $this->mail->sendFormSubmission([
            'name' => (string) ($order['name'] ?? ''),
            'phone' => (string) ($order['phone'] ?? ''),
            'email' => (string) ($order['email'] ?? ''),
            'event' => $eventLine,
            'tickets' => (string) ($order['tickets'] ?? ''),
            'message' => sprintf('ОПЛАЧЕНО. Заказ %s. Сумма %s %s.', (string) ($order['id'] ?? ''), $sum, $currency),
        ], [], $requestId);

        if (!$sent) {
            $this->logger->warning('Оплата проведена, но уведомление не отправлено (MAIL_TO?)', [
                'request_id' => $requestId,
                'order' => $order['id'] ?? '',
            ]);
        }
    }
}
