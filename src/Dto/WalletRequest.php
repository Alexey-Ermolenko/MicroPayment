<?php

namespace App\Dto;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints as Assert;

final class WalletRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [Currency::class, 'values'])]
        public string $currency = 'USD',
    ) {
    }
}
