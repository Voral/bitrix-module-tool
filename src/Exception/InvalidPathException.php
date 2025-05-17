<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

class InvalidPathException extends ExtensionException
{
    protected const CODE = 102;

    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Unable to determine project path: the resolved path "%s" is invalid.',
                $path,
            ),
            $previous,
        );
    }
}
