<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AlfaGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;

final class AlfaGatewayTest extends TestCase
{
    /**
     * @param array<string,mixed> $overrides
     */
    private function gateway(array $overrides = []): AlfaGateway
    {
        return new AlfaGateway(array_merge([
            'enabled' => true,
            'env' => 'test',
            'api_url' => 'https://tws.egopay.ru/ab/rest',
            'username' => 'shop-api',
            'password' => 'secret',
            'token' => '',
            'currency' => '810',
            'description' => 'Покупка билета — Экосистема итали',
            'item_name' => 'Электронный билет',
            'session_timeout' => 1200,
            'fiscal' => ['enabled' => false, 'tax_type' => 0, 'measure' => 'шт'],
            'timeout' => 30,
        ], $overrides), new NullLogger());
    }

    /**
     * @param array<int,mixed> $args
     */
    private function call(AlfaGateway $gateway, string $method, array $args): mixed
    {
        return new ReflectionMethod($gateway, $method)->invokeArgs($gateway, $args);
    }

    public function testIsEnabledFollowsConfig(): void
    {
        self::assertTrue($this->gateway()->isEnabled());
        self::assertFalse($this->gateway(['enabled' => false])->isEnabled());
    }

    public function testIsPaidOnlyForDepositedOrder(): void
    {
        $gateway = $this->gateway();
        self::assertTrue($gateway->isPaid(['orderStatus' => 2, 'paymentState' => '']));
        self::assertTrue($gateway->isPaid(['orderStatus' => -1, 'paymentState' => 'DEPOSITED']));
        self::assertFalse($gateway->isPaid(['orderStatus' => 0, 'paymentState' => '']));
        self::assertFalse($gateway->isPaid(['orderStatus' => 6, 'paymentState' => 'DECLINED']));
    }

    public function testFailReasonPrefersBankDescription(): void
    {
        $gateway = $this->gateway();
        self::assertSame('Недостаточно средств', $gateway->failReason([
            'orderStatus' => 6,
            'actionCodeDescription' => 'Недостаточно средств',
            'errorCode' => '0',
        ]));
        self::assertSame('errorCode 6', $gateway->failReason([
            'orderStatus' => -1,
            'actionCodeDescription' => '',
            'errorCode' => '6',
        ]));
        self::assertSame('orderStatus 0', $gateway->failReason([
            'orderStatus' => 0,
            'actionCodeDescription' => '',
            'errorCode' => '0',
        ]));
    }

    public function testRegisterFailsWithoutCredentials(): void
    {
        $gateway = $this->gateway(['username' => '', 'password' => '', 'token' => '']);
        self::assertNull($gateway->register([
            'amount' => 100000,
            'orderId' => 'aabbccddeeff001122334455',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'guest@example.com',
            'tickets' => 1,
            'unitKopecks' => 100000,
            'returnUrl' => 'https://italycommunity.ru/pay/return?order=aabbccddeeff001122334455',
            'failUrl' => 'https://italycommunity.ru/pay/return?order=aabbccddeeff001122334455&fail=1',
        ]));
    }

    public function testPhoneIsNormalisedToBankFormat(): void
    {
        $gateway = $this->gateway();
        self::assertSame('+79991234567', $this->call($gateway, 'phone', ['+7 (999) 123-45-67']));
        self::assertSame('+79991234567', $this->call($gateway, 'phone', ['8 999 123 45 67']));
        self::assertSame('+79991234567', $this->call($gateway, 'phone', ['9991234567']));
        self::assertSame('', $this->call($gateway, 'phone', ['123']));
        self::assertSame('', $this->call($gateway, 'phone', ['']));
    }

    public function testDescriptionDropsCharactersRejectedByBank(): void
    {
        $description = $this->call($this->gateway(['description' => "Скидка 10% + бонус\nвторая строка"]), 'description', []);
        self::assertIsString($description);
        self::assertStringNotContainsString('%', $description);
        self::assertStringNotContainsString('+', $description);
        self::assertStringNotContainsString("\n", $description);
        self::assertLessThanOrEqual(99, mb_strlen($description));
    }

    public function testDescriptionIsTruncatedTo99Characters(): void
    {
        $description = $this->call($this->gateway(['description' => str_repeat('а', 150)]), 'description', []);
        self::assertIsString($description);
        self::assertSame(99, mb_strlen($description));
    }

    public function testOrderBundleIsOmittedWhileFiscalisationIsOff(): void
    {
        $bundle = $this->call($this->gateway(), 'orderBundle', [['tickets' => 2, 'unitKopecks' => 150000, 'amount' => 300000]]);
        self::assertNull($bundle);
    }

    public function testOrderBundleMatchesCartTotals(): void
    {
        $gateway = $this->gateway(['fiscal' => ['enabled' => true, 'tax_type' => 0, 'measure' => 'шт']]);
        $raw = $this->call($gateway, 'orderBundle', [['tickets' => 2, 'unitKopecks' => 150000, 'amount' => 300000]]);
        self::assertIsString($raw);

        $bundle = json_decode($raw, true);
        self::assertIsArray($bundle);
        $item = $bundle['cartItems']['items'][0];
        self::assertSame(1, $item['positionId']);
        self::assertSame('Электронный билет', $item['name']);
        self::assertSame(2, $item['quantity']['value']);
        self::assertSame('шт', $item['quantity']['measure']);
        self::assertSame(150000, $item['itemPrice']);
        self::assertSame(300000, $item['itemAmount']);
        self::assertSame(0, $item['tax']['taxType']);
    }
}
