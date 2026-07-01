<?php

namespace App\Tests\Api;

use JsonException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @throws JsonException
     */
    public function testRegisterLoginAndProfile(): void
    {
        $email = 'user@example.com';

        $registered = $this->send('POST', '/api/v1/register', ['email' => $email, 'password' => 'secret123']);
        $token = $this->send('POST', '/api/v1/login', ['email' => $email, 'password' => 'secret123'])['token'];
        $walletId = $this->send('POST', '/api/v1/wallets', ['currency' => 'USD'], $token)['id'];

        $profile = $this->send('GET', '/api/v1/profile', token: $token);

        self::assertSame($email, $registered['email']);
        self::assertSame($email, $profile['email']);
        self::assertIsArray($profile['transactions']);
        self::assertSame([$walletId], array_column($profile['wallets'], 'id'));
    }

    /**
     * @throws JsonException
     */
    public function testUnauthenticatedRequestIsRejected(): void
    {
        $response = $this->send('GET', '/api/v1/profile');
        self::assertSame(401, $response['code']);
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

        return (array)json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }
}
