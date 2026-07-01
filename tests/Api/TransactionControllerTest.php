<?php

namespace App\Tests\Api;

use JsonException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransactionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @throws JsonException
     */
    public function testDepositIsPendingUntilAdminApproves(): void
    {
        $token = $this->token('user@example.com');
        $admin = $this->token('admin@example.com', 'ROLE_ADMIN');
        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $deposit = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $wallet, 'amount' => 7500], $token, ['HTTP_Idempotency-Key' => 'deposit-key']);
        self::assertSame('PENDING', $deposit['status']);

        // Balance untouched while pending.
        self::assertSame(0, $this->send('GET', '/api/v1/wallets/'.$wallet, token: $token)['balance']);

        // Admin approves -> worker credits the wallet.
        $this->send('POST', '/api/v1/transactions/'.$deposit['id'].'/approve', token: $admin);

        self::assertSame('APPROVED', $this->send('GET', '/api/v1/transactions/'.$deposit['id'], token: $token)['status']);
        self::assertSame(7500, $this->send('GET', '/api/v1/wallets/'.$wallet, token: $token)['balance']);
    }

    /**
     * @throws JsonException
     */
    public function testTransferRequiresAdminApproval(): void
    {
        $sender = $this->token('sender@example.com');
        $admin = $this->token('admin@example.com', 'ROLE_ADMIN');
        $senderWallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $sender)['id'];

        // Fund the sender wallet: deposit + admin approve.
        $deposit = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $senderWallet, 'amount' => 10000], $sender, ['HTTP_Idempotency-Key' => 'transfer-deposit-key']);
        $this->send('POST', '/api/v1/transactions/'.$deposit['id'].'/approve', token: $admin);

        $recipient = $this->token('recipient@example.com');
        $recipientWallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $recipient)['id'];

        $transfer = $this->send('POST', '/api/v1/transactions/transfer', [
            'senderWalletId' => $senderWallet,
            'recipientWalletId' => $recipientWallet,
            'amount' => 4000,
        ], $sender, ['HTTP_Idempotency-Key' => 'transfer-key']);
        self::assertSame('PENDING', $transfer['status']);

        // A regular user cannot approve.
        $forbidden = $this->send('POST', '/api/v1/transactions/'.$transfer['id'].'/approve', token: $sender);
        self::assertSame("Access Denied. The user doesn't have ROLE_ADMIN.", $forbidden['error']);

        // An admin approves -> funds move.
        $this->send('POST', '/api/v1/transactions/'.$transfer['id'].'/approve', token: $admin);

        self::assertSame(6000, $this->send('GET', '/api/v1/wallets/'.$senderWallet, token: $sender)['balance']);
        self::assertSame(4000, $this->send('GET', '/api/v1/wallets/'.$recipientWallet, token: $recipient)['balance']);
    }

    /**
     * @throws JsonException
     */
    public function testIdempotentDepositCreatesOneTransaction(): void
    {
        $token = $this->token('user@example.com');
        $admin = $this->token('admin@example.com', 'ROLE_ADMIN');
        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $key = ['HTTP_Idempotency-Key' => 'idempotent-key'];
        $first = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $wallet, 'amount' => 1000], $token, $key)['id'];
        $second = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $wallet, 'amount' => 1000], $token, $key)['id'];
        self::assertSame($first, $second);

        $this->send('POST', '/api/v1/transactions/'.$first.'/approve', token: $admin);
        self::assertSame(1000, $this->send('GET', '/api/v1/wallets/'.$wallet, token: $token)['balance']);
    }

    /**
     * @throws JsonException
     */
    public function testDepositRequiresIdempotencyKey(): void
    {
        $token = $this->token('user@example.com');
        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $response = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $wallet, 'amount' => 1000], $token);
        self::assertSame('Idempotency-Key header is required.', $response['error']);
    }

    /**
     * @throws JsonException
     */
    private function token(string $email, ?string $role = null): string
    {
        $payload = ['email' => $email, 'password' => 'secret123'];
        if (null !== $role) {
            $payload['role'] = $role;
        }
        $this->send('POST', '/api/v1/register', $payload);

        return $this->send('POST', '/api/v1/login', ['email' => $email, 'password' => 'secret123'])['token'];
    }

    /**
     * @throws JsonException
     */
    private function send(string $method, string $uri, array $body = [], ?string $token = null, array $headers = []): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'] + $headers;
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, server: $server, content: json_encode($body, JSON_THROW_ON_ERROR));

        return (array) json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }
}
