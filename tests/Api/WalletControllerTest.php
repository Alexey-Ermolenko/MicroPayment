<?php

namespace App\Tests\Api;

use JsonException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WalletControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @throws JsonException
     */
    public function testCreateWallet(): void
    {
        $this->send('POST', '/api/v1/register', ['email' => 'user@example.com', 'password' => 'secret123']);
        $token = $this->send('POST', '/api/v1/login', ['email' => 'user@example.com', 'password' => 'secret123'])['token'];

        $wallet = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token);

        self::assertSame('USD', $wallet['currency']);
        self::assertSame(0, $wallet['balance']);
        self::assertNotEmpty($wallet['id']);
    }

    /**
     * @throws JsonException
     */
    public function testCannotShowOtherUsersWallet(): void
    {
        $this->send('POST', '/api/v1/register', ['email' => 'owner@example.com', 'password' => 'secret123']);
        $owner = $this->send('POST', '/api/v1/login', ['email' => 'owner@example.com', 'password' => 'secret123'])['token'];
        $walletId = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $owner)['id'];

        $this->send('POST', '/api/v1/register', ['email' => 'other@example.com', 'password' => 'secret123']);
        $other = $this->send('POST', '/api/v1/login', ['email' => 'other@example.com', 'password' => 'secret123'])['token'];

        $forbidden = $this->send('GET', '/api/v1/wallets/'. $walletId, token: $other);
        self::assertSame('Access Denied.', $forbidden['error']);
    }

    /**
     * @throws JsonException
     */
    private function send(string $method, string $uri, array $body = [], ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, server: $server, content: json_encode($body, JSON_THROW_ON_ERROR));

        return (array) json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }
}
