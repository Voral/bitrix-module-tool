<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Exception;

class NoVersionTagException extends ExtensionException
{
    protected const CODE = 103;

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('No version tag found.', $previous);
    }
}
