<?php

namespace App\Tests\Unit\Dto;

use App\Dto\DepositRequest;
use App\Dto\RegisterRequest;
use App\Dto\WalletRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testWalletRequestAcceptsKnownCurrencyAndRejectsUnknown(): void
    {
        self::assertCount(0, $this->validator->validate(new WalletRequest('USD')));
        self::assertViolation($this->validator->validate(new WalletRequest('usd')), 'currency');
    }

    public function testDepositRequestRejectsNonPositiveAmountAndBadUuid(): void
    {
        self::assertViolation($this->validator->validate(new DepositRequest((string) Uuid::v4(), 0)), 'amount');
        self::assertViolation($this->validator->validate(new DepositRequest('not-a-uuid', 100)), 'walletId');
        self::assertCount(0, $this->validator->validate(new DepositRequest((string) Uuid::v4(), 100)));
    }

    public function testRegisterRequestValidation(): void
    {
        $invalid = $this->validator->validate(new RegisterRequest('nope', '123'));
        self::assertViolation($invalid, 'email');
        self::assertViolation($invalid, 'password');

        self::assertCount(0, $this->validator->validate(new RegisterRequest('user@example.com', 'secret123')));
    }

    private static function assertViolation(iterable $violations, string $property): void
    {
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $property) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Expected a validation violation on "%s".', $property));
    }
}
