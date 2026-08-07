<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Клиент интернет-эквайринга Альфа-Банка (платформа RBS, REST-интерфейс).
 * register.do — регистрация заказа, банк возвращает свой orderId и formUrl платёжной
 * страницы; getOrderStatusExtended.do — статус заказа. Авторизация — логин/пароль
 * магазина (логин с суффиксом -api) либо token. Ответ банка — JSON.
 *
 * Боевой шлюз: https://pay.alfabank.ru/payment/rest/
 * Тестовый:    https://tws.egopay.ru/ab/rest/
 *
 * @param array{
 *   enabled: bool, env: string, api_url: string, username: string, password: string,
 *   token: string, currency: string, description: string, item_name: string,
 *   session_timeout: int, fiscal: array{enabled: bool, tax_type: int, measure: string},
 *   orders_dir: string, timeout: int
 * } $config
 */
final class AlfaGateway
{
    /** Полная авторизация суммы заказа — заказ оплачен (одностадийный платёж). */
    public const ORDER_STATUS_DEPOSITED = 2;

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

    /**
     * Регистрация заказа (register.do).
     *
     * @param array{amount:int,orderId:string,phone:string,email:string,tickets:int,unitKopecks:int,returnUrl:string,failUrl:string} $order
     * @return array{orderId:string,formUrl:string}|null  идентификатор заказа в банке и URL платёжной страницы
     */
    public function register(array $order, string $requestId = ''): ?array
    {
        $params = [
            'orderNumber' => $order['orderId'],
            'amount' => $order['amount'],
            'currency' => (string) $this->config['currency'],
            'returnUrl' => $order['returnUrl'],
            'failUrl' => $order['failUrl'],
            'description' => $this->description(),
            'language' => 'ru',
            'sessionTimeoutSecs' => (int) ($this->config['session_timeout'] ?? 1200),
        ];

        $email = $this->email($order['email']);
        if ($email !== '') {
            $params['email'] = $email;
            // email для уведомлений покупателю банк берёт из jsonParams
            $params['jsonParams'] = (string) json_encode(['email' => $email], JSON_UNESCAPED_UNICODE);
        }
        $phone = $this->phone($order['phone']);
        if ($phone !== '') {
            $params['phone'] = $phone;
        }

        $bundle = $this->orderBundle($order);
        if ($bundle !== null) {
            $params['orderBundle'] = $bundle;
        }

        $fields = $this->post('register.do', $params, $requestId);
        if ($fields === null) {
            return null;
        }

        $bankOrderId = isset($fields['orderId']) && is_string($fields['orderId']) ? $fields['orderId'] : '';
        $formUrl = isset($fields['formUrl']) && is_string($fields['formUrl']) ? $fields['formUrl'] : '';

        if ($bankOrderId === '' || $formUrl === '') {
            $this->logger->warning('Альфа: регистрация отклонена', [
                'request_id' => $requestId,
                'order' => $order['orderId'],
                'error_code' => (string) ($fields['errorCode'] ?? ''),
                'error_message' => (string) ($fields['errorMessage'] ?? ''),
            ]);
            return null;
        }

        return ['orderId' => $bankOrderId, 'formUrl' => $formUrl];
    }

    /**
     * Статус заказа (getOrderStatusExtended.do). orderNumber в ответе — наш id заказа.
     *
     * @return array{orderStatus:int, orderNumber:string, paymentState:string, actionCodeDescription:string, errorCode:string, fields:array<string,mixed>}
     */
    public function status(string $bankOrderId, string $requestId = ''): array
    {
        $fields = $this->post('getOrderStatusExtended.do', ['orderId' => $bankOrderId, 'language' => 'ru'], $requestId) ?? [];

        $amountInfo = isset($fields['paymentAmountInfo']) && is_array($fields['paymentAmountInfo']) ? $fields['paymentAmountInfo'] : [];

        return [
            'orderStatus' => isset($fields['orderStatus']) && is_numeric($fields['orderStatus']) ? (int) $fields['orderStatus'] : -1,
            'orderNumber' => isset($fields['orderNumber']) && is_string($fields['orderNumber']) ? $fields['orderNumber'] : '',
            'paymentState' => isset($amountInfo['paymentState']) && is_string($amountInfo['paymentState']) ? $amountInfo['paymentState'] : '',
            'actionCodeDescription' => isset($fields['actionCodeDescription']) && is_string($fields['actionCodeDescription']) ? $fields['actionCodeDescription'] : '',
            'errorCode' => isset($fields['errorCode']) ? (string) $fields['errorCode'] : '',
            'fields' => $fields,
        ];
    }

    /**
     * @param array{orderStatus:int, paymentState:string} $status
     */
    public function isPaid(array $status): bool
    {
        return $status['orderStatus'] === self::ORDER_STATUS_DEPOSITED || $status['paymentState'] === 'DEPOSITED';
    }

    /**
     * Причина отказа для журнала и алерта — описание кода ответа банка либо статус заказа.
     *
     * @param array{orderStatus:int, actionCodeDescription:string, errorCode:string} $status
     */
    public function failReason(array $status): string
    {
        if ($status['actionCodeDescription'] !== '') {
            return $status['actionCodeDescription'];
        }
        if ($status['errorCode'] !== '' && $status['errorCode'] !== '0') {
            return 'errorCode ' . $status['errorCode'];
        }
        return $status['orderStatus'] >= 0 ? 'orderStatus ' . $status['orderStatus'] : 'unknown';
    }

    /**
     * Корзина для фискального чека (54-ФЗ). Передаётся, только если у магазина
     * включена фискализация на стороне банка — иначе банк отклонит заказ.
     *
     * @param array{tickets:int,unitKopecks:int,amount:int} $order
     */
    private function orderBundle(array $order): ?string
    {
        $fiscal = is_array($this->config['fiscal'] ?? null) ? $this->config['fiscal'] : [];
        if (!($fiscal['enabled'] ?? false)) {
            return null;
        }

        $bundle = [
            'cartItems' => [
                'items' => [
                    [
                        'positionId' => 1,
                        'name' => (string) ($this->config['item_name'] ?? 'Электронный билет'),
                        'quantity' => [
                            'value' => $order['tickets'],
                            'measure' => (string) ($fiscal['measure'] ?? 'шт'),
                        ],
                        'itemAmount' => $order['unitKopecks'] * $order['tickets'],
                        'itemCode' => 'ticket',
                        'itemPrice' => $order['unitKopecks'],
                        'tax' => ['taxType' => (int) ($fiscal['tax_type'] ?? 0)],
                    ],
                ],
            ],
        ];

        return (string) json_encode($bundle, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Назначение платежа: банк принимает не более 99 символов, без % + \r \n.
     */
    private function description(): string
    {
        $raw = (string) ($this->config['description'] ?? '');
        $clean = str_replace(['%', '+', "\r", "\n"], ' ', $raw);
        return trim(mb_substr(preg_replace('/\s+/u', ' ', $clean) ?? '', 0, 99));
    }

    private function email(string $raw): string
    {
        $email = trim($raw);
        return mb_strlen($email) <= 40 ? $email : '';
    }

    /**
     * Телефон в формате банка (ANS.12): +7XXXXXXXXXX. Некорректный номер не передаём —
     * поле необязательное, а неверный формат отклоняет регистрацию целиком.
     */
    private function phone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return '+7' . $digits;
        }
        return '';
    }

    /**
     * POST на REST-интерфейс шлюза. Возвращает разобранный JSON или null при ошибке транспорта.
     *
     * @param array<string,string|int> $params
     * @return array<string,mixed>|null
     */
    private function post(string $method, array $params, string $requestId): ?array
    {
        $auth = $this->auth();
        if ($auth === null) {
            $this->logger->error('Альфа: не заданы учётные данные магазина', ['request_id' => $requestId, 'method' => $method]);
            return null;
        }

        $url = rtrim((string) $this->config['api_url'], '/') . '/' . $method;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => http_build_query($auth + $params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $result = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($errno !== 0 || !is_string($result)) {
            $this->logger->error('Альфа: ошибка транспорта', [
                'request_id' => $requestId,
                'method' => $method,
                'errno' => $errno,
                'error' => $error,
                'http_code' => $httpCode,
            ]);
            return null;
        }

        $data = json_decode($result, true);
        if (!is_array($data)) {
            $this->logger->error('Альфа: неразбираемый ответ', [
                'request_id' => $requestId,
                'method' => $method,
                'http_code' => $httpCode,
                'response' => mb_substr($result, 0, 500),
            ]);
            return null;
        }

        return $data;
    }

    /**
     * @return array<string,string>|null
     */
    private function auth(): ?array
    {
        $token = trim((string) ($this->config['token'] ?? ''));
        if ($token !== '') {
            return ['token' => $token];
        }

        $username = trim((string) ($this->config['username'] ?? ''));
        $password = (string) ($this->config['password'] ?? '');
        if ($username === '' || $password === '') {
            return null;
        }

        return ['userName' => $username, 'password' => $password];
    }
}
