<?php

namespace App\Tests\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
        $admin = $this->token('admin@example.com', true);
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
        $admin = $this->token('admin@example.com', true);
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
        $admin = $this->token('admin@example.com', true);
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
    public function testSameIdempotencyKeyFromTwoUsersDoesNotCollide(): void
    {
        $first = $this->token('first@example.com');
        $second = $this->token('second@example.com');
        $firstWallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $first)['id'];
        $secondWallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $second)['id'];

        $key = ['HTTP_Idempotency-Key' => 'shared-key'];
        $firstDeposit = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $firstWallet, 'amount' => 1000], $first, $key);
        $secondDeposit = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $secondWallet, 'amount' => 2000], $second, $key);

        self::assertNotSame($firstDeposit['id'], $secondDeposit['id']);
        self::assertSame('PENDING', $secondDeposit['status']);
    }

    /**
     * @throws JsonException
     */
    public function testSecondRefundOfTheSameTransactionIsRejected(): void
    {
        $token = $this->token('refund-twice@example.com');
        $admin = $this->token('refund-admin@example.com', true);
        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $deposit = $this->send('POST', '/api/v1/transactions/deposit', ['walletId' => $wallet, 'amount' => 3000], $token, ['HTTP_Idempotency-Key' => 'refund-twice-key']);
        $this->send('POST', '/api/v1/transactions/'.$deposit['id'].'/approve', token: $admin);

        $refund = $this->send('POST', '/api/v1/transactions/'.$deposit['id'].'/refund', token: $token);
        self::assertSame('PENDING', $refund['status']);

        $second = $this->send('POST', '/api/v1/transactions/'.$deposit['id'].'/refund', token: $token);
        self::assertSame('Transaction has already been refunded.', $second['error']);
    }

    /**
     * @throws JsonException
     */
    public function testTransferToTheSameWalletIsRejected(): void
    {
        $token = $this->token('self-transfer@example.com');
        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $response = $this->send('POST', '/api/v1/transactions/transfer', [
            'senderWalletId' => $wallet,
            'recipientWalletId' => $wallet,
            'amount' => 1000,
        ], $token, ['HTTP_Idempotency-Key' => 'self-transfer-key']);

        self::assertSame('Sender and recipient wallets must differ.', $response['error']);
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
    private function token(string $email, bool $admin = false): string
    {
        $this->send('POST', '/api/v1/register', ['email' => $email, 'password' => 'secret123']);

        if ($admin) {
            // ROLE_ADMIN is not reachable through the API: grant it the way the seed migration does.
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->getRepository(User::class)->findOneBy(['email' => $email])->setRoles(['ROLE_ADMIN']);
            $em->flush();
        }

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
