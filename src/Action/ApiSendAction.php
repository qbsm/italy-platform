<?php

declare(strict_types=1);

namespace App\Action;

use App\Middleware\CorrelationIdMiddleware;
use App\Service\MailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class ApiSendAction
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly LoggerInterface $logger,
        private readonly \App\Notification\Channel\RescueChannel $rescue,
        private readonly \App\Support\FormToken $formToken,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(['cache_limiter' => '']);
        }

        $this->pruneIdempotencyStore();

        $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        $parsed = $request->getParsedBody();
        $data = is_array($parsed) ? $parsed : [];
        $idempotencyKey = $this->extractString($data, 'idempotency_key');

        // Ловушка: поле спрятано от человека, робот заполняет всё подряд. Отвечаем как при
        // успехе — иначе робот подберёт набор полей и вернётся.
        if ($this->extractString($data, 'company_site') !== '') {
            $this->logger->warning('Заявка отброшена ловушкой', ['request_id' => $requestId]);
            return $this->json($response, 200, [
                'success' => true,
                'message' => 'Заявка успешно отправлена',
                'channels' => [],
                'request_id' => $requestId,
            ]);
        }

        // Подтверждение источника: токен выдан по запросу браузера и несёт время выдачи.
        $verdict = $this->formToken->inspect($this->extractString($data, 'form_token'));
        if (!$verdict['valid']) {
            $this->logger->warning('Заявка отклонена проверкой токена', [
                'request_id' => $requestId,
                'reason' => $verdict['reason'],
            ]);
            return $this->json($response, 419, [
                'success' => false,
                'code' => 'TOKEN_INVALID',
                'message' => 'Не удалось подтвердить отправку. Попробуйте ещё раз.',
                'retry_after' => max(1, $this->formToken->minAge()),
                'request_id' => $requestId,
            ]);
        }

        // Идемпотентность
        if ($idempotencyKey !== '') {
            $cached = $this->getCachedResponse($idempotencyKey);
            if ($cached !== null) {
                return $this->json($response, $cached['status'], $cached['payload']);
            }
        }

        // Валидация
        $errors = $this->validate($data);
        if ($errors !== []) {
            $payload = [
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Проверьте поля формы',
                'errors' => $errors,
                'request_id' => $requestId,
            ];
            $this->cacheResponse($idempotencyKey, 422, $payload);
            return $this->json($response, 422, $payload);
        }

        // Отправка email
        $uploadedFiles = $request->getUploadedFiles();
        $mailSent = $this->mailService->sendFormSubmission($data, $uploadedFiles, $requestId);

        if (!$mailSent) {
            $this->logger->warning('Форма принята, но письмо не отправлено', ['request_id' => $requestId]);
        }

        // Резервная копия заявки в приёмник: письмо уходит в один заход, и отказ SMTP означал бы
        // потерянный лид. Канал изолирован — его отказ не должен ломать ответ формы.
        try {
            if ($this->rescue->isEnabled()) {
                $data['_user_agent'] = (string) ($request->getHeaderLine('User-Agent') ?: '');
                $this->rescue->send($data, $uploadedFiles, $requestId);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Rescue: канал упал', ['request_id' => $requestId, 'error' => $e->getMessage()]);
        }

        $payload = [
            'success' => true,
            'message' => 'Заявка успешно отправлена',
            'request_id' => $requestId,
        ];
        $this->cacheResponse($idempotencyKey, 200, $payload);
        return $this->json($response, 200, $payload);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        $email = $this->extractString($data, 'email');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Неверный E-mail';
        }

        $name = $this->extractString($data, 'name');
        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = 'Укажите имя';
        }

        $policy = $this->extractString($data, 'policy');
        if ($policy !== 'on') {
            $errors['policy'] = 'Согласитесь с политикой';
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function extractString(array $data, string $key): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function pruneIdempotencyStore(): void
    {
        $store = $_SESSION['api_send_idempotency'] ?? [];
        if (!is_array($store)) {
            $_SESSION['api_send_idempotency'] = [];
            return;
        }

        $now = time();
        $ttl = 900;
        foreach ($store as $key => $item) {
            if (!is_array($item) || !isset($item['ts']) || !is_int($item['ts']) || ($now - $item['ts']) > $ttl) {
                unset($store[$key]);
            }
        }
        $_SESSION['api_send_idempotency'] = $store;
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}|null
     */
    private function getCachedResponse(string $idempotencyKey): ?array
    {
        $store = $_SESSION['api_send_idempotency'] ?? [];
        if (!is_array($store) || !isset($store[$idempotencyKey]) || !is_array($store[$idempotencyKey])) {
            return null;
        }

        $entry = $store[$idempotencyKey];
        if (!isset($entry['status'], $entry['payload']) || !is_int($entry['status']) || !is_array($entry['payload'])) {
            return null;
        }

        return ['status' => $entry['status'], 'payload' => $entry['payload']];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function cacheResponse(string $idempotencyKey, int $status, array $payload): void
    {
        if ($idempotencyKey === '') {
            return;
        }

        $store = $_SESSION['api_send_idempotency'] ?? [];
        if (!is_array($store)) {
            $store = [];
        }

        $store[$idempotencyKey] = ['status' => $status, 'payload' => $payload, 'ts' => time()];
        $_SESSION['api_send_idempotency'] = $store;
    }
}
