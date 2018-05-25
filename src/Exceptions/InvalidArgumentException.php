<?php

namespace SuperSimpleCache\Exceptions;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
}
