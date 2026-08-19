<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\MailService;
use App\Service\OrderConfirmer;
use App\Service\OrderStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class OrderConfirmerTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/italy-orders-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*.json') as $file) {
            @unlink((string) $file);
        }
        @rmdir($this->dir);
    }

    public function testConfirmMarksOrderPaidAndSendsSingleNotification(): void
    {
        $sent = 0;
        $store = new OrderStore($this->dir, new NullLogger());
        $confirmer = new OrderConfirmer($store, $this->mailService($sent), new NullLogger());

        $order = $store->create($this->orderData());
        self::assertIsArray($order);

        self::assertTrue($confirmer->confirm($order, 'req-1'));

        $stored = $store->find((string) $order['id']);
        self::assertIsArray($stored);
        self::assertSame('paid', $stored['status']);
        self::assertArrayHasKey('paid_at', $stored);
        self::assertSame(1, $sent);
    }

    public function testAlreadyPaidOrderIsNotConfirmedTwice(): void
    {
        $sent = 0;
        $store = new OrderStore($this->dir, new NullLogger());
        $confirmer = new OrderConfirmer($store, $this->mailService($sent), new NullLogger());

        $order = $store->create($this->orderData());
        self::assertIsArray($order);

        $confirmer->confirm($order, 'req-1');
        $paid = $store->find((string) $order['id']);
        self::assertIsArray($paid);

        // Второе подтверждение приходит другим путём (возврат покупателя после callback банка)
        self::assertFalse($confirmer->confirm($paid, 'req-2'));
        self::assertSame(1, $sent);
    }

    public function testOrderWithoutIdIsIgnored(): void
    {
        $sent = 0;
        $confirmer = new OrderConfirmer(
            new OrderStore($this->dir, new NullLogger()),
            $this->mailService($sent),
            new NullLogger()
        );

        self::assertFalse($confirmer->confirm(['status' => 'pending'], 'req-1'));
        self::assertSame(0, $sent);
    }

    /**
     * @return array<string,mixed>
     */
    private function orderData(): array
    {
        return [
            'status' => 'pending',
            'event_slug' => 'gala-dinner',
            'event_title' => 'Гала-ужин',
            'event_date' => '12 сентября',
            'tickets' => 2,
            'amount' => 3000000,
            'currency' => '810',
            'name' => 'Гость',
            'phone' => '+79990000000',
            'email' => 'guest@example.com',
        ];
    }

    private function mailService(int &$sent): MailService
    {
        $mailer = new class ($sent) implements MailerInterface {
            public function __construct(private int &$sent) {}

            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                $this->sent++;
            }
        };

        return new MailService($mailer, new NullLogger(), [
            'to' => 'sales@example.com',
            'from' => 'noreply@example.com',
            'from_name' => 'Italy',
            'subject_prefix' => '',
        ]);
    }
}
