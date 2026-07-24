<?php

declare(strict_types=1);

namespace App\Action;

use App\Middleware\CorrelationIdMiddleware;
use App\Service\MailService;
use App\Service\OrderStore;
use App\Service\RsbGateway;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /pay/return — возврат покупателя с платёжной страницы банка (RSB ClientHandler
 * редиректит сюда с ?trans_id=...). Запрашиваем статус (command=c), проводим заказ и
 * отправляем на страницу события с результатом.
 */
final class PayReturnAction
{
    private const SUCCESS_RESULTS = ['OK'];

    public function __construct(
        private readonly RsbGateway $gateway,
        private readonly OrderStore $orders,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        $query = $request->getQueryParams();
        $transactionId = isset($query['trans_id']) && is_string($query['trans_id']) ? trim($query['trans_id']) : '';

        if ($transactionId === '' || !$this->gateway->isEnabled()) {
            return $this->redirect($response, '/events');
        }

        $status = $this->gateway->status($transactionId, $this->clientIp($request), $requestId);
        $orderId = $status['mrchTransactionId'];
        $order = $orderId !== '' ? $this->orders->find($orderId) : null;

        if ($order === null) {
            $this->logger->warning('Оплата: заказ по возврату не найден', [
                'request_id' => $requestId,
                'trans_id' => $transactionId,
                'mrch' => $orderId,
                'result' => $status['result'],
            ]);
            return $this->redirect($response, '/events');
        }

        $slug = (string) ($order['event_slug'] ?? '');
        $back = $slug !== '' ? '/events/' . $slug : '/events';
        $paid = in_array($status['result'], self::SUCCESS_RESULTS, true);

        if ($paid) {
            $alreadyPaid = ($order['status'] ?? '') === 'paid';
            if (!$alreadyPaid) {
                $this->orders->update((string) $order['id'], ['status' => 'paid', 'paid_at' => time()]);
                $this->notify($order, $requestId);
            }
            $this->logger->info('Оплата успешна', ['request_id' => $requestId, 'order' => $order['id']]);
            return $this->redirect($response, $back . '?' . http_build_query(['order' => $order['id'], 'paid' => '1']));
        }

        $this->orders->update((string) $order['id'], ['status' => 'failed', 'fail_reason' => $status['result'] ?: 'unknown']);
        $this->logger->warning('Оплата не прошла', ['request_id' => $requestId, 'order' => $order['id'], 'result' => $status['result']]);
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
        $currency = ((string) ($order['currency'] ?? '643')) === '643' ? '₽' : (string) $order['currency'];
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

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $fwd = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return (string) ($server['REMOTE_ADDR'] ?? '');
    }

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Location', $location);
    }
}
