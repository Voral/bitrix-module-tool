<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

use Vasoft\VersionIncrement\Exceptions\UserException;

abstract class ExtensionException extends UserException
{
    protected const CODE = 100;

    public static int $errorCodeDelta = 0;

    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(static::CODE + self::$errorCodeDelta, $message, $previous);
    }
}
