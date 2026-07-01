<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class DepositRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $walletId = '',

        #[Assert\Positive]
        public int $amount = 0,
    ) {
    }
}
