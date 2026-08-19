<?php

declare(strict_types=1);

namespace App\Action;

use App\Middleware\CorrelationIdMiddleware;
use App\Service\AlfaGateway;
use App\Service\CallbackVerifier;
use App\Service\OrderConfirmer;
use App\Service\OrderStore;
use App\Service\TelegramAlertService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /pay/callback — серверное уведомление банка об операции с заказом.
 *
 * Нужен потому, что возврат покупателя (PayReturnAction) может не состояться: оплатил и
 * закрыл вкладку — заказ навсегда остался бы pending, а продажа билета осталась незамеченной.
 * Уведомление приходит независимо от браузера, поэтому подлинность проверяется подписью, а
 * факт оплаты всё равно подтверждается запросом статуса в банк.
 *
 * Банк повторяет уведомление, пока не получит 200 OK, поэтому 200 отдаётся и на повторы —
 * защита от двойного письма живёт в статусе заказа (OrderConfirmer).
 */
final class PayCallbackAction
{
    private const OPERATION_DEPOSITED = 'deposited';

    public function __construct(
        private readonly CallbackVerifier $verifier,
        private readonly AlfaGateway $gateway,
        private readonly OrderStore $orders,
        private readonly OrderConfirmer $confirmer,
        private readonly TelegramAlertService $alerts,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        $params = $request->getQueryParams();

        $bankOrderId = isset($params['mdOrder']) && is_string($params['mdOrder']) ? trim($params['mdOrder']) : '';
        $orderNumber = isset($params['orderNumber']) && is_string($params['orderNumber']) ? trim($params['orderNumber']) : '';
        $operation = isset($params['operation']) && is_string($params['operation']) ? $params['operation'] : '';
        $status = isset($params['status']) ? (string) $params['status'] : '';

        // Адрес открывают руками при настройке уведомлений в кабинете банка. Уведомление
        // всегда приходит с параметрами, поэтому голый запрос — это проверка доступности,
        // и отвечать на неё пустой страницей значит выдавать рабочий адрес за сломанный.
        if ($params === []) {
            return $this->text($response, 200, 'Приём уведомлений об оплате. Адрес рабочий, уведомления банк шлёт с параметрами.');
        }

        if (!$this->verifier->isEnabled()) {
            $this->logger->error('Оплата: callback пришёл, но токен подписи не настроен', [
                'request_id' => $requestId,
                'order_number' => $orderNumber,
            ]);
            return $this->text($response, 503, 'Приём уведомлений не настроен: не задан ключ контрольной суммы.');
        }

        if (!$this->verifier->verify($params)) {
            $this->logger->warning('Оплата: callback с неверной подписью', [
                'request_id' => $requestId,
                'order_number' => $orderNumber,
                'operation' => $operation,
            ]);
            return $this->text($response, 403, 'Уведомление отклонено: неверная контрольная сумма.');
        }

        $this->logger->info('Оплата: callback банка', [
            'request_id' => $requestId,
            'order_number' => $orderNumber,
            'bank_order_id' => $bankOrderId,
            'operation' => $operation,
            'status' => $status,
        ]);

        if ($operation !== self::OPERATION_DEPOSITED || $status !== '1') {
            return $this->ok($response);
        }

        $order = $orderNumber !== '' ? $this->orders->find($orderNumber) : null;
        if ($order === null) {
            $this->logger->warning('Оплата: заказ из callback не найден', [
                'request_id' => $requestId,
                'order_number' => $orderNumber,
                'bank_order_id' => $bankOrderId,
            ]);
            return $this->ok($response);
        }

        if (($order['status'] ?? '') === 'paid') {
            return $this->ok($response);
        }

        if ($bankOrderId === '') {
            $bankOrderId = (string) ($order['bank_order_id'] ?? '');
        }

        $gatewayStatus = $this->gateway->status($bankOrderId, $requestId);
        if (!$this->gateway->isPaid($gatewayStatus)) {
            $this->logger->warning('Оплата: callback deposited, но банк не подтверждает оплату', [
                'request_id' => $requestId,
                'order' => $order['id'] ?? '',
                'order_status' => $gatewayStatus['orderStatus'],
            ]);
            return $this->ok($response);
        }

        if ($this->confirmer->confirm($order, $requestId)) {
            $this->logger->info('Оплата подтверждена по callback банка', [
                'request_id' => $requestId,
                'order' => $order['id'] ?? '',
            ]);
            $this->alerts->send(sprintf(
                'Оплачен билет (подтверждение банком без возврата на сайт): заказ %s, событие %s',
                (string) ($order['id'] ?? ''),
                (string) ($order['event_title'] ?? $order['event_slug'] ?? ''),
            ), $requestId);
        }

        return $this->ok($response);
    }

    private function ok(ResponseInterface $response): ResponseInterface
    {
        return $this->text($response, 200, 'OK');
    }

    private function text(ResponseInterface $response, int $status, string $body): ResponseInterface
    {
        $response->getBody()->write($body);
        return $response->withStatus($status)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
