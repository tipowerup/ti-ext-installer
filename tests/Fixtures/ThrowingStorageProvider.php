<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ThrowingStorageProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new RuntimeException('provider registration exploded');
    }
}
