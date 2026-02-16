<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Exceptions;

use RuntimeException;

class PackageInstallationException extends RuntimeException
{
    public static function downloadFailed(string $packageCode, string $reason): self
    {
        return new self(
            sprintf("Failed to download package '%s': %s", $packageCode, $reason)
        );
    }

    public static function extractionFailed(string $packageCode, string $reason): self
    {
        return new self(
            sprintf("Failed to extract package '%s': %s", $packageCode, $reason)
        );
    }

    public static function migrationFailed(string $packageCode, string $reason): self
    {
        return new self(
            sprintf("Migration failed for package '%s': %s", $packageCode, $reason)
        );
    }

    public static function checksumMismatch(string $packageCode): self
    {
        return new self(
            sprintf("Package integrity check failed for '%s'. The download may be corrupted.", $packageCode)
        );
    }
}
