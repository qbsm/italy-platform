<?php

declare(strict_types=1);

namespace App\Action;

use App\Middleware\CorrelationIdMiddleware;
use App\Service\AlfaGateway;
use App\Service\MailService;
use App\Service\OrderStore;
use App\Service\TelegramAlertService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /pay/return — возврат покупателя с платёжной страницы банка. Альфа редиректит сюда
 * на returnUrl/failUrl, добавляя свой ?orderId=...; наш id заказа лежит в ?order=...
 * Источник истины — getOrderStatusExtended, а не то, на какой из адресов вернулся клиент.
 */
final class PayReturnAction
{
    public function __construct(
        private readonly AlfaGateway $gateway,
        private readonly OrderStore $orders,
        private readonly MailService $mail,
        private readonly TelegramAlertService $alerts,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        $query = $request->getQueryParams();
        $ourOrderId = isset($query['order']) && is_string($query['order']) ? trim($query['order']) : '';
        $bankOrderId = isset($query['orderId']) && is_string($query['orderId']) ? trim($query['orderId']) : '';

        if (!$this->gateway->isEnabled()) {
            return $this->redirect($response, '/events');
        }

        // Заказ ищем по своему id; id банка берём из query, а при его отсутствии — из заказа
        $order = $ourOrderId !== '' ? $this->orders->find($ourOrderId) : null;
        if ($bankOrderId === '' && $order !== null) {
            $bankOrderId = (string) ($order['bank_order_id'] ?? '');
        }

        if ($bankOrderId === '') {
            $this->logger->warning('Оплата: возврат без идентификатора заказа банка', [
                'request_id' => $requestId,
                'order' => $ourOrderId,
            ]);
            return $this->redirect($response, '/events');
        }

        $status = $this->gateway->status($bankOrderId, $requestId);
        if ($order === null && $status['orderNumber'] !== '') {
            $order = $this->orders->find($status['orderNumber']);
        }

        if ($order === null) {
            $this->logger->warning('Оплата: заказ по возврату не найден', [
                'request_id' => $requestId,
                'bank_order_id' => $bankOrderId,
                'order_number' => $status['orderNumber'],
                'order_status' => $status['orderStatus'],
            ]);
            return $this->redirect($response, '/events');
        }

        $slug = (string) ($order['event_slug'] ?? '');
        $back = $slug !== '' ? '/events/' . $slug : '/events';

        if ($this->gateway->isPaid($status)) {
            $alreadyPaid = ($order['status'] ?? '') === 'paid';
            if (!$alreadyPaid) {
                $this->orders->update((string) $order['id'], ['status' => 'paid', 'paid_at' => time()]);
                $this->notify($order, $requestId);
            }
            $this->logger->info('Оплата успешна', ['request_id' => $requestId, 'order' => $order['id']]);
            return $this->redirect($response, $back . '?' . http_build_query(['order' => $order['id'], 'paid' => '1']));
        }

        $reason = $this->gateway->failReason($status);
        $this->orders->update((string) $order['id'], ['status' => 'failed', 'fail_reason' => $reason]);
        $this->logger->warning('Оплата не прошла', [
            'request_id' => $requestId,
            'order' => $order['id'],
            'order_status' => $status['orderStatus'],
            'reason' => $reason,
        ]);
        $this->alerts->send(sprintf(
            'Оплата не прошла: заказ %s, событие %s, результат банка: %s',
            (string) $order['id'],
            (string) ($order['event_title'] ?? $slug),
            $reason,
        ), $requestId);
        return $this->redirect($response, $back . '?' . http_build_query(['order' => $order['id'], 'pay' => 'failed']));
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

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Location', $location);
    }
}
