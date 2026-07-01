<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $senderWalletId = '',

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $recipientWalletId = '',

        #[Assert\Positive]
        public int $amount = 0,
    ) {
    }
}
