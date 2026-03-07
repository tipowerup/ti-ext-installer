<?php

declare(strict_types=1);

use Tipowerup\Installer\Exceptions\CompatibilityException;

it('unsatisfied creates exception with package code and failures', function (): void {
    $exception = CompatibilityException::unsatisfied('tipowerup/ti-ext-test', [
        'PHP >=9.0',
        'TastyIgniter >=99.0',
    ]);

    expect($exception)->toBeInstanceOf(CompatibilityException::class)
        ->and($exception->getMessage())->toContain('tipowerup/ti-ext-test')
        ->and($exception->getMessage())->toContain('PHP >=9.0')
        ->and($exception->getMessage())->toContain('TastyIgniter >=99.0');
});
