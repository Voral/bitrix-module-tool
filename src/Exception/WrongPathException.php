<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

class WrongPathException extends ExtensionException
{
    protected const CODE = 105;

    public function __construct(string $name, ?\Throwable $previous = null)
    {
        parent::__construct(
            'The path ' . $name . ' should be valid and relative to the project root directory',
            $previous,
        );
    }
}
