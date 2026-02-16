<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Exceptions;

use RuntimeException;

class CompatibilityException extends RuntimeException
{
    public static function unsatisfied(string $packageCode, array $failures): self
    {
        $failureList = implode(', ', $failures);

        return new self(
            sprintf("Package '%s' is not compatible with your current setup. ", $packageCode).
            ('Failed requirements: '.$failureList)
        );
    }
}
