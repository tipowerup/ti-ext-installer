<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Tests;

use Tipowerup\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getExtensionBasePath(): string
    {
        return dirname(__DIR__);
    }

    protected function getExtensionProviders(): array
    {
        return [\Tipowerup\Installer\Extension::class];
    }

    /**
     * Set a deterministic app key so Livewire can encrypt its component state.
     * The base testbench omits this, which causes ViewException on any Livewire test.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
    }
}
