<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

class WrongVersionFileException extends ExtensionException
{
    protected const CODE = 104;

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Wrong version file format.', $previous);
    }
}
