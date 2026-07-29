<?php

declare(strict_types=1);

namespace App\Action;

use App\Middleware\CorrelationIdMiddleware;
use App\Service\MailService;
use App\Service\AlfaGateway;
use App\Service\OrderStore;
use App\Service\TelegramAlertService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * POST /api/pay — регистрация оплаты билета в эквайринге Альфа-Банка и редирект на
 * платёжную страницу банка.
 *
 * Успех: 303 на formUrl банка (для обычного submit) либо JSON {formUrl} (для XHR).
 */
final class PayCreateAction
{
    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        private readonly AlfaGateway $gateway,
        private readonly OrderStore $orders,
        private readonly MailService $mail,
        private readonly TelegramAlertService $alerts,
        private readonly LoggerInterface $logger,
        private readonly array $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $wantsJson = $this->wantsJson($request);

        if (!$this->gateway->isEnabled()) {
            return $this->fail($response, $wantsJson, 503, 'PAYMENT_DISABLED', 'Онлайн-оплата временно недоступна', '/events', $requestId);
        }

        // CSRF (тот же механизм, что и в ApiSendAction)
        $csrfToken = $this->str($data, 'csrf_token');
        $sessionToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            return $this->fail($response, $wantsJson, 419, 'CSRF_INVALID', 'Сессия истекла. Обновите страницу и попробуйте снова.', '/events', $requestId);
        }

        // Событие
        $slug = $this->slug($this->str($data, 'slug'));
        if ($slug === '') {
            return $this->fail($response, $wantsJson, 422, 'EVENT_INVALID', 'Событие не указано', '/events', $requestId);
        }
        $event = $this->loadEvent($slug);
        $backUrl = '/events/' . $slug;
        if ($event === null) {
            return $this->fail($response, $wantsJson, 404, 'EVENT_NOT_FOUND', 'Событие не найдено', '/events', $requestId);
        }

        $e = is_array($event['event'] ?? null) ? $event['event'] : [];
        $price = $this->price($e['price'] ?? null);
        if ($price <= 0) {
            return $this->fail($response, $wantsJson, 422, 'PRICE_UNAVAILABLE', 'Оплата для этого события недоступна', $backUrl, $requestId);
        }

        // Контакты + количество
        $name = $this->str($data, 'name');
        $email = $this->str($data, 'email');
        $phone = $this->str($data, 'phone');
        $tickets = (int) $this->str($data, 'tickets');
        $policy = $this->str($data, 'policy');

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = 'Укажите имя';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Неверный E-mail';
        }
        if ($policy !== 'on') {
            $errors['policy'] = 'Согласитесь с политикой';
        }
        $seatsLeft = isset($e['seatsLeft']) && is_numeric($e['seatsLeft']) ? (int) $e['seatsLeft'] : 0;
        $maxTickets = $seatsLeft > 0 ? min($seatsLeft, 20) : 20;
        if ($tickets < 1 || $tickets > $maxTickets) {
            $errors['tickets'] = 'Неверное количество мест';
        }
        if ($errors !== []) {
            return $this->fail($response, $wantsJson, 422, 'VALIDATION_ERROR', 'Проверьте поля формы', $backUrl, $requestId, $errors);
        }

        $unitKopecks = (int) round($price * 100);
        $amount = $unitKopecks * $tickets;

        $order = $this->orders->create([
            'event_slug' => $slug,
            'event_title' => (string) ($e['title'] ?? $slug),
            'event_date' => $this->eventDate($e),
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'tickets' => $tickets,
            'unit_price' => $price,
            'amount' => $amount,
            'currency' => (string) ($this->settings['payment']['currency'] ?? '810'),
            'request_id' => $requestId,
        ]);
        if ($order === null) {
            return $this->fail($response, $wantsJson, 500, 'ORDER_STORE_ERROR', 'Не удалось создать заказ', $backUrl, $requestId);
        }

        $orderId = (string) $order['id'];
        $returnUrl = $this->returnUrl($orderId);

        $registered = $this->gateway->register([
            'amount' => $amount,
            'orderId' => $orderId,
            'phone' => $phone,
            'email' => $email,
            'tickets' => $tickets,
            'unitKopecks' => $unitKopecks,
            'returnUrl' => $returnUrl,
            'failUrl' => $returnUrl . '&fail=1',
        ], $requestId);

        if ($registered === null) {
            $this->orders->update($orderId, ['status' => 'failed', 'fail_reason' => 'register']);
            return $this->fail($response, $wantsJson, 502, 'GATEWAY_ERROR', 'Банк недоступен, попробуйте позже', $backUrl, $requestId);
        }

        $formUrl = $registered['formUrl'];
        $this->orders->update($orderId, ['status' => 'pending', 'bank_order_id' => $registered['orderId']]);
        $this->logger->info('Оплата зарегистрирована', ['request_id' => $requestId, 'order' => $orderId, 'amount' => $amount]);

        // Дубль ссылки на оплату клиенту на почту (редирект — основной сценарий; сбой почты оплату не ломает)
        $currency = $this->currencySign((string) ($this->settings['payment']['currency'] ?? '810'));
        $this->mail->sendPaymentLink(
            $email,
            $name,
            (string) ($e['title'] ?? $slug),
            $this->eventDate($e),
            $tickets,
            number_format($amount / 100, 0, '.', ' ') . ' ' . $currency,
            $formUrl,
            $requestId,
        );

        if ($wantsJson) {
            return $this->json($response, 200, ['success' => true, 'formUrl' => $formUrl, 'order_id' => $order['id'], 'request_id' => $requestId]);
        }
        return $response->withStatus(303)->withHeader('Location', $formUrl);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadEvent(string $slug): ?array
    {
        $lang = (string) ($this->settings['default_lang'] ?? 'ru');
        $base = (string) ($this->settings['paths']['json_base'] ?? '');
        $path = $base . '/' . $lang . '/events/' . $slug . '.json';
        if ($base === '' || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        if (array_key_exists('visible', $data) && $data['visible'] === false) {
            return null;
        }
        return $data;
    }

    private function price(mixed $raw): float
    {
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    /**
     * @param array<string,mixed> $e
     */
    private function eventDate(array $e): string
    {
        $d = is_array($e['date'] ?? null) ? $e['date'] : [];
        $parts = array_filter([
            (string) ($d['day'] ?? ''),
            (string) ($d['month'] ?? ''),
            (string) ($d['time'] ?? ''),
        ], static fn (string $p): bool => $p !== '');
        return implode(' ', $parts);
    }

    private function slug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';
        return trim($slug, '-');
    }

    /**
     * Адрес возврата с платёжной страницы. Свой id заказа кладём в query — банк
     * добавит к нему собственный orderId, оба нужны обработчику /pay/return.
     */
    private function returnUrl(string $orderId): string
    {
        $base = rtrim((string) ($this->settings['payment']['base_url'] ?? ''), '/');
        return $base . '/pay/return?order=' . rawurlencode($orderId);
    }

    private function currencySign(string $code): string
    {
        return in_array($code, ['810', '643'], true) ? '₽' : $code;
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        $xhr = strtolower($request->getHeaderLine('X-Requested-With'));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function str(array $data, string $key): string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : '';
    }

    /**
     * @param array<string,string> $errors
     */
    // PAYMENT_DISABLED сюда не входит: это штатно выключенная оплата, а не сбой —
    // иначе каждый POST на /api/pay (бот, прямой запрос) шлёт алерт в группу
    private const ALERT_CODES = ['GATEWAY_ERROR', 'ORDER_STORE_ERROR'];

    private function fail(
        ResponseInterface $response,
        bool $wantsJson,
        int $status,
        string $code,
        string $message,
        string $redirect,
        string $requestId,
        array $errors = [],
    ): ResponseInterface {
        $this->logger->warning('Оплата отклонена', ['request_id' => $requestId, 'code' => $code]);
        // серверные сбои (не пользовательская валидация) — алерт в группу Итали
        if (in_array($code, self::ALERT_CODES, true)) {
            $this->alerts->send("Ошибка оплаты билета: {$code} — {$message}", $requestId);
        }
        if ($wantsJson) {
            $payload = ['success' => false, 'code' => $code, 'message' => $message, 'request_id' => $requestId];
            if ($errors !== []) {
                $payload['errors'] = $errors;
            }
            return $this->json($response, $status, $payload);
        }
        $sep = str_contains($redirect, '?') ? '&' : '?';
        return $response->withStatus(303)->withHeader('Location', $redirect . $sep . http_build_query(['pay' => 'error', 'code' => $code]));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
