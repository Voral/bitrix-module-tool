<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

class NotAccessibleException extends ExtensionException
{
    protected const CODE = 101;

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'Unable to determine project path: current working directory is not accessible.',
            $previous,
        );
    }
}
