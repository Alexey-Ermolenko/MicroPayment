<?php

namespace App\Exception;

abstract class DomainException extends \RuntimeException
{
    abstract public function statusCode(): int;
}
