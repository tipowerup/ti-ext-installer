<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services\Concerns;

use InvalidArgumentException;

/**
 * Provides package code format validation for Composer-format package codes.
 * Valid format: vendor/package (e.g. tipowerup/ti-ext-darkmode)
 */
trait ValidatesPackageCode
{
    /**
     * Validate package code format (Composer slash notation, e.g. tipowerup/ti-ext-darkmode).
     *
     * @throws InvalidArgumentException
     */
    private function validatePackageCode(string $packageCode): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/i', $packageCode)) {
            throw new InvalidArgumentException(
                sprintf("Invalid package code format: '%s'", $packageCode)
            );
        }
    }
}
