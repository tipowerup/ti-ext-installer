<?php

declare(strict_types=1);

use Tipowerup\Installer\Extension;

it('sets and returns config when called with an explicit argument', function (): void {
    $extension = new Extension($this->app);

    $result = $extension->extensionMeta(['code' => 'tipowerup.installer']);

    expect($result)->toBe(['code' => 'tipowerup.installer']);
});

it('returns the cached config on subsequent calls without re-reading the file', function (): void {
    $extension = new Extension($this->app);

    $first = $extension->extensionMeta();
    $second = $extension->extensionMeta();

    expect($second)->toBe($first);
});
