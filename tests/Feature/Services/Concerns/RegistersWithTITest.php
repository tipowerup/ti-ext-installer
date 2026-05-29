<?php

declare(strict_types=1);

use Igniter\System\Classes\BaseExtension;
use Igniter\System\Classes\ExtensionManager;
use Tipowerup\Installer\Services\Concerns\RegistersWithTI;

/**
 * Stub class that exposes the trait's private resolveExtensionCode via a
 * public proxy so we can test it in isolation.
 */
class RegistersWithTITestHarness
{
    use RegistersWithTI;

    public function callResolveExtensionCode(string $vendorPath): string
    {
        return $this->resolveExtensionCode($vendorPath);
    }
}

beforeEach(function (): void {
    $this->harness = new RegistersWithTITestHarness;
});

it('returns the resolved extension code on success', function (): void {
    $fakeExtension = Mockery::mock(BaseExtension::class);

    $this->mock(ExtensionManager::class, function ($mock) use ($fakeExtension): void {
        $mock->shouldReceive('loadExtension')->with('/some/path')->andReturn($fakeExtension);
        $mock->shouldReceive('getIdentifier')->andReturn('tipowerup.darkmode');
    });

    expect($this->harness->callResolveExtensionCode('/some/path'))->toBe('tipowerup.darkmode');
});

it('returns an empty string when getIdentifier returns false', function (): void {
    $fakeExtension = Mockery::mock(BaseExtension::class);

    $this->mock(ExtensionManager::class, function ($mock) use ($fakeExtension): void {
        $mock->shouldReceive('loadExtension')->with('/some/path')->andReturn($fakeExtension);
        $mock->shouldReceive('getIdentifier')->andReturn(false);
    });

    expect($this->harness->callResolveExtensionCode('/some/path'))->toBe('');
});

it('returns an empty string when getIdentifier returns an empty string', function (): void {
    $fakeExtension = Mockery::mock(BaseExtension::class);

    $this->mock(ExtensionManager::class, function ($mock) use ($fakeExtension): void {
        $mock->shouldReceive('loadExtension')->with('/some/path')->andReturn($fakeExtension);
        $mock->shouldReceive('getIdentifier')->andReturn('');
    });

    expect($this->harness->callResolveExtensionCode('/some/path'))->toBe('');
});
