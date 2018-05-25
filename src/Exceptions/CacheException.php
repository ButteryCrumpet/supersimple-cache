<?php

namespace SuperSimpleCache\Exceptions;

use Psr\SimpleCache\CacheException as PsrCacheException;

class CacheException extends \Exception implements PsrCacheException
{
}
