<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 6)]
        public string $password = '',

        #[Assert\Choice(choices: ['ROLE_USER', 'ROLE_ADMIN'])]
        public string $role = 'ROLE_USER',
    ) {
    }
}
