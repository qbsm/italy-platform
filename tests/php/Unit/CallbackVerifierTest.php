<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CallbackVerifier;
use PHPUnit\Framework\TestCase;

final class CallbackVerifierTest extends TestCase
{
    private const TOKEN = 'yourSecretToken';

    public function testChecksumFromBankDocumentationIsAccepted(): void
    {
        $data = 'amount;123456;mdOrder;3ff6962a-7dcc-4283-ab50-a6d7dd3386fe;operation;deposited;orderNumber;10747;status;1;';
        $params = [
            'amount' => '123456',
            'mdOrder' => '3ff6962a-7dcc-4283-ab50-a6d7dd3386fe',
            'operation' => 'deposited',
            'orderNumber' => '10747',
            'status' => '1',
            'checksum' => strtoupper(hash_hmac('sha256', $data, self::TOKEN)),
        ];

        self::assertTrue(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testParameterOrderDoesNotMatter(): void
    {
        $data = 'mdOrder;abc;operation;deposited;orderNumber;77;status;1;';
        $params = [
            'status' => '1',
            'checksum' => strtoupper(hash_hmac('sha256', $data, self::TOKEN)),
            'orderNumber' => '77',
            'operation' => 'deposited',
            'mdOrder' => 'abc',
        ];

        self::assertTrue(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testLowercaseChecksumIsAccepted(): void
    {
        $data = 'mdOrder;abc;status;1;';
        $params = [
            'mdOrder' => 'abc',
            'status' => '1',
            'checksum' => strtolower(hash_hmac('sha256', $data, self::TOKEN)),
        ];

        self::assertTrue(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testSignAliasIsNotPartOfChecksum(): void
    {
        $data = 'mdOrder;abc;status;1;';
        $params = [
            'mdOrder' => 'abc',
            'status' => '1',
            'sign_alias' => 'RSA',
            'checksum' => strtoupper(hash_hmac('sha256', $data, self::TOKEN)),
        ];

        self::assertTrue(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testTamperedParameterIsRejected(): void
    {
        $data = 'amount;500;mdOrder;abc;operation;deposited;status;1;';
        $params = [
            'amount' => '50000',
            'mdOrder' => 'abc',
            'operation' => 'deposited',
            'status' => '1',
            'checksum' => strtoupper(hash_hmac('sha256', $data, self::TOKEN)),
        ];

        self::assertFalse(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testChecksumFromAnotherTokenIsRejected(): void
    {
        $data = 'mdOrder;abc;status;1;';
        $params = [
            'mdOrder' => 'abc',
            'status' => '1',
            'checksum' => strtoupper(hash_hmac('sha256', $data, 'otherToken')),
        ];

        self::assertFalse(new CallbackVerifier(self::TOKEN)->verify($params));
    }

    public function testNotificationWithoutChecksumIsRejected(): void
    {
        self::assertFalse(new CallbackVerifier(self::TOKEN)->verify(['mdOrder' => 'abc', 'status' => '1']));
    }

    public function testWithoutConfiguredTokenNothingIsAccepted(): void
    {
        $verifier = new CallbackVerifier('');

        self::assertFalse($verifier->isEnabled());
        self::assertFalse($verifier->verify([
            'mdOrder' => 'abc',
            'checksum' => strtoupper(hash_hmac('sha256', 'mdOrder;abc;', '')),
        ]));
    }
}
