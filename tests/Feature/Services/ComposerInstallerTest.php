<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\ComposerInstaller;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function composerInstaller(): ComposerInstaller
{
    return new class extends ComposerInstaller
    {
        public function __construct()
        {
            // Skip parent constructor
        }

        /**
         * Expose private methods for testing.
         */
        public function callRemoveFromComposerJson(string $packageCode): void
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('removeFromComposerJson');
            $method->invoke($this, $packageCode);
        }

        public function callRemoveFromComposerLock(string $packageCode): void
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('removeFromComposerLock');
            $method->invoke($this, $packageCode);
        }

        public function callRemoveFromInstalledJson(string $packageCode): void
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('removeFromInstalledJson');
            $method->invoke($this, $packageCode);
        }

        /**
         * Invoke install() to test validation paths.
         *
         * @param  array<string, mixed>  $licenseData
         * @return array<string, mixed>
         */
        public function callInstall(string $packageCode, array $licenseData): array
        {
            return $this->install($packageCode, $licenseData);
        }

        /**
         * Execute only the file-manipulation steps of uninstall() without
         * requiring TI managers or Composer to be available.
         */
        public function callUninstallFilesOnly(string $packageCode): void
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);

            $removeJson = $reflection->getMethod('removeFromComposerJson');
            $removeLock = $reflection->getMethod('removeFromComposerLock');
            $removeInstalled = $reflection->getMethod('removeFromInstalledJson');

            $removeJson->invoke($this, $packageCode);
            $removeLock->invoke($this, $packageCode);
            $removeInstalled->invoke($this, $packageCode);
        }
    };
}

function writeJsonFile(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

// ---------------------------------------------------------------------------
// removeFromComposerJson
// ---------------------------------------------------------------------------

describe('removeFromComposerJson', function (): void {
    beforeEach(function (): void {
        $this->composerJsonPath = base_path('composer.json');
        $this->originalComposerJson = file_get_contents($this->composerJsonPath);
    });

    afterEach(function (): void {
        file_put_contents($this->composerJsonPath, $this->originalComposerJson);
    });

    it('removes package from require section', function (): void {
        $data = json_decode($this->originalComposerJson, true);
        $data['require']['tipowerup/fake-test-pkg'] = 'dev-main';
        writeJsonFile($this->composerJsonPath, $data);

        composerInstaller()->callRemoveFromComposerJson('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->composerJsonPath), true);
        expect($result['require'])->not->toHaveKey('tipowerup/fake-test-pkg');
    });

    it('removes package from require-dev section', function (): void {
        $data = json_decode($this->originalComposerJson, true);
        $data['require-dev']['tipowerup/fake-test-pkg'] = 'dev-main';
        writeJsonFile($this->composerJsonPath, $data);

        composerInstaller()->callRemoveFromComposerJson('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->composerJsonPath), true);
        expect($result['require-dev'])->not->toHaveKey('tipowerup/fake-test-pkg');
    });

    it('does not write file when package is not present', function (): void {
        $before = file_get_contents($this->composerJsonPath);

        composerInstaller()->callRemoveFromComposerJson('tipowerup/nonexistent-pkg');

        expect(file_get_contents($this->composerJsonPath))->toBe($before);
    });

    it('preserves other packages when removing one', function (): void {
        $data = json_decode($this->originalComposerJson, true);
        $data['require']['tipowerup/fake-pkg-a'] = '^1.0';
        $data['require']['tipowerup/fake-pkg-b'] = '^2.0';
        writeJsonFile($this->composerJsonPath, $data);

        composerInstaller()->callRemoveFromComposerJson('tipowerup/fake-pkg-a');

        $result = json_decode(file_get_contents($this->composerJsonPath), true);
        expect($result['require'])->not->toHaveKey('tipowerup/fake-pkg-a');
        expect($result['require']['tipowerup/fake-pkg-b'])->toBe('^2.0');
    });
});

// ---------------------------------------------------------------------------
// removeFromComposerLock
// ---------------------------------------------------------------------------

describe('removeFromComposerLock', function (): void {
    beforeEach(function (): void {
        $this->lockPath = base_path('composer.lock');
        $this->lockExisted = file_exists($this->lockPath);
        if ($this->lockExisted) {
            $this->originalLock = file_get_contents($this->lockPath);
        }
    });

    afterEach(function (): void {
        if ($this->lockExisted) {
            file_put_contents($this->lockPath, $this->originalLock);
        } elseif (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }
    });

    it('removes package from packages array', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'test',
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0'],
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0'],
            ],
            'packages-dev' => [],
        ]);

        composerInstaller()->callRemoveFromComposerLock('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->lockPath), true);
        $names = array_column($result['packages'], 'name');
        expect($names)->not->toContain('tipowerup/fake-test-pkg');
        expect($names)->toContain('other/package');
    });

    it('removes package from packages-dev array', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'test',
            'packages' => [],
            'packages-dev' => [
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0'],
                ['name' => 'other/dev-pkg', 'version' => '2.0.0'],
            ],
        ]);

        composerInstaller()->callRemoveFromComposerLock('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->lockPath), true);
        $devNames = array_column($result['packages-dev'], 'name');
        expect($devNames)->not->toContain('tipowerup/fake-test-pkg');
        expect($devNames)->toContain('other/dev-pkg');
    });

    it('re-indexes packages array after removal', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'test',
            'packages' => [
                ['name' => 'aaa/first', 'version' => '1.0.0'],
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0'],
                ['name' => 'zzz/last', 'version' => '1.0.0'],
            ],
            'packages-dev' => [],
        ]);

        composerInstaller()->callRemoveFromComposerLock('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->lockPath), true);
        expect(array_keys($result['packages']))->toBe([0, 1]);
        expect($result['packages'][0]['name'])->toBe('aaa/first');
        expect($result['packages'][1]['name'])->toBe('zzz/last');
    });

    it('handles missing lock file gracefully', function (): void {
        if (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }

        // Should not throw
        composerInstaller()->callRemoveFromComposerLock('tipowerup/fake-test-pkg');
        expect(file_exists($this->lockPath))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// removeFromInstalledJson
// ---------------------------------------------------------------------------

describe('removeFromInstalledJson', function (): void {
    beforeEach(function (): void {
        $this->installedPath = base_path('vendor/composer/installed.json');
        $this->installedExisted = file_exists($this->installedPath);
        if ($this->installedExisted) {
            $this->originalInstalled = file_get_contents($this->installedPath);
        }
    });

    afterEach(function (): void {
        if ($this->installedExisted) {
            file_put_contents($this->installedPath, $this->originalInstalled);
        } elseif (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }
    });

    it('removes package from packages array', function (): void {
        writeJsonFile($this->installedPath, [
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0', 'install-path' => '../other/package'],
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0', 'install-path' => '../tipowerup/fake-test-pkg'],
            ],
            'dev' => true,
            'dev-package-names' => [],
        ]);

        composerInstaller()->callRemoveFromInstalledJson('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->installedPath), true);
        $names = array_column($result['packages'], 'name');
        expect($names)->not->toContain('tipowerup/fake-test-pkg');
        expect($names)->toContain('other/package');
    });

    it('preserves other packages when removing one', function (): void {
        writeJsonFile($this->installedPath, [
            'packages' => [
                ['name' => 'aaa/first', 'version' => '1.0.0'],
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0'],
                ['name' => 'zzz/last', 'version' => '3.0.0'],
            ],
            'dev' => true,
            'dev-package-names' => [],
        ]);

        composerInstaller()->callRemoveFromInstalledJson('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->installedPath), true);
        expect(count($result['packages']))->toBe(2);
        expect($result['packages'][0]['name'])->toBe('aaa/first');
        expect($result['packages'][1]['name'])->toBe('zzz/last');
    });

    it('re-indexes packages array after removal', function (): void {
        writeJsonFile($this->installedPath, [
            'packages' => [
                ['name' => 'aaa/first', 'version' => '1.0.0'],
                ['name' => 'tipowerup/fake-test-pkg', 'version' => '1.0.0'],
                ['name' => 'zzz/last', 'version' => '1.0.0'],
            ],
            'dev' => true,
            'dev-package-names' => [],
        ]);

        composerInstaller()->callRemoveFromInstalledJson('tipowerup/fake-test-pkg');

        $result = json_decode(file_get_contents($this->installedPath), true);
        expect(array_keys($result['packages']))->toBe([0, 1]);
    });

    it('handles missing file gracefully', function (): void {
        if (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }

        composerInstaller()->callRemoveFromInstalledJson('tipowerup/fake-test-pkg');
        expect(file_exists($this->installedPath))->toBeFalse();
    });

    it('handles nonexistent package gracefully', function (): void {
        writeJsonFile($this->installedPath, [
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0'],
            ],
            'dev' => true,
            'dev-package-names' => [],
        ]);

        composerInstaller()->callRemoveFromInstalledJson('tipowerup/nonexistent-pkg');

        $result = json_decode(file_get_contents($this->installedPath), true);
        expect(count($result['packages']))->toBe(1);
        expect($result['packages'][0]['name'])->toBe('other/package');
    });
});

// ---------------------------------------------------------------------------
// Helpers for private method access
// ---------------------------------------------------------------------------

function composerInstallerWithMethods(): object
{
    return new class extends ComposerInstaller
    {
        public function __construct()
        {
            // Skip parent constructor
        }

        public function callGetInstalledVersion(string $packageName): ?string
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('getInstalledVersion');

            return $method->invoke($this, $packageName);
        }

        public function callEnsureRepository(): void
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('ensureRepository');
            $method->invoke($this);
        }
    };
}

// ---------------------------------------------------------------------------
// getInstalledVersion
// ---------------------------------------------------------------------------

describe('getInstalledVersion', function (): void {
    beforeEach(function (): void {
        $this->lockPath = base_path('composer.lock');
        $this->lockExisted = file_exists($this->lockPath);
        if ($this->lockExisted) {
            $this->originalLock = file_get_contents($this->lockPath);
        }
    });

    afterEach(function (): void {
        if ($this->lockExisted) {
            file_put_contents($this->lockPath, $this->originalLock);
        } elseif (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }
    });

    it('returns version from packages array', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'abc123',
            'packages' => [
                ['name' => 'other/package', 'version' => '2.0.0'],
                ['name' => 'tipowerup/ti-ext-darkmode', 'version' => '1.3.5'],
            ],
            'packages-dev' => [],
        ]);

        $result = composerInstallerWithMethods()->callGetInstalledVersion('tipowerup/ti-ext-darkmode');

        expect($result)->toBe('1.3.5');
    });

    it('returns version from packages-dev array', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'abc123',
            'packages' => [],
            'packages-dev' => [
                ['name' => 'tipowerup/ti-ext-darkmode', 'version' => '0.9.0'],
                ['name' => 'other/dev-pkg', 'version' => '3.0.0'],
            ],
        ]);

        $result = composerInstallerWithMethods()->callGetInstalledVersion('tipowerup/ti-ext-darkmode');

        expect($result)->toBe('0.9.0');
    });

    it('returns null when package is not in lock file', function (): void {
        writeJsonFile($this->lockPath, [
            'content-hash' => 'abc123',
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0'],
            ],
            'packages-dev' => [],
        ]);

        $result = composerInstallerWithMethods()->callGetInstalledVersion('tipowerup/nonexistent-pkg');

        expect($result)->toBeNull();
    });

    it('returns null when lock file does not exist', function (): void {
        if (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }

        $result = composerInstallerWithMethods()->callGetInstalledVersion('tipowerup/ti-ext-darkmode');

        expect($result)->toBeNull();
    });

    it('returns null when lock file has invalid JSON', function (): void {
        file_put_contents($this->lockPath, 'this is not valid json {{{');

        $result = composerInstallerWithMethods()->callGetInstalledVersion('tipowerup/ti-ext-darkmode');

        expect($result)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// ensureRepository
// ---------------------------------------------------------------------------

describe('ensureRepository', function (): void {
    beforeEach(function (): void {
        $this->composerJsonPath = base_path('composer.json');
        $this->originalComposerJson = file_get_contents($this->composerJsonPath);
    });

    afterEach(function (): void {
        file_put_contents($this->composerJsonPath, $this->originalComposerJson);
    });

    it('does not add repo when tipowerup repository url is already present', function (): void {
        $data = json_decode($this->originalComposerJson, true);
        $data['repositories'][] = [
            'type' => 'composer',
            'url' => 'https://packages.tipowerup.com',
        ];
        writeJsonFile($this->composerJsonPath, $data);

        $contentBefore = file_get_contents($this->composerJsonPath);

        // ensureRepository should return early without invoking runComposer
        // We verify no exception is thrown and composer.json is untouched
        $installer = new class extends ComposerInstaller
        {
            public function __construct()
            {
                // Skip parent constructor
            }

            public function runEnsureRepository(): void
            {
                $reflection = new ReflectionClass(ComposerInstaller::class);
                $method = $reflection->getMethod('ensureRepository');
                $method->invoke($this);
            }

            /** @throws \RuntimeException always, to detect calls */
            private function runComposer(array $command, ?callable $onProgress = null): string
            {
                throw new \RuntimeException('runComposer was called unexpectedly');
            }
        };

        $installer->runEnsureRepository();

        expect(file_get_contents($this->composerJsonPath))->toBe($contentBefore);
    });

    it('config default repo URL points to the tipowerup packages endpoint', function (): void {
        expect(config('tipowerup.installer.composer_repo_url'))->toBe('https://packages.tipowerup.com');
    });
});

// ---------------------------------------------------------------------------
// install validation
// ---------------------------------------------------------------------------

describe('install validation', function (): void {
    it('throws when auth_token is missing from licenseData', function (): void {
        expect(fn () => composerInstaller()->callInstall('tipowerup/ti-ext-darkmode', []))
            ->toThrow(\Tipowerup\Installer\Exceptions\PackageInstallationException::class, 'Authentication token not provided');
    });

    it('throws on invalid package code format with dots', function (): void {
        expect(fn () => composerInstaller()->callInstall('tipowerup.darkmode', ['auth_token' => 'tok']))
            ->toThrow(\InvalidArgumentException::class, 'Invalid package code format');
    });

    it('throws on invalid package code format with spaces', function (): void {
        expect(fn () => composerInstaller()->callInstall('tipowerup dark mode', ['auth_token' => 'tok']))
            ->toThrow(\InvalidArgumentException::class, 'Invalid package code format');
    });

    it('accepts valid vendor/package format', function (): void {
        // Validation passes and it proceeds to ensureRepository / runComposer which we do not test here.
        // We just verify the exception is NOT an InvalidArgumentException.
        try {
            composerInstaller()->callInstall('tipowerup/ti-ext-darkmode', ['auth_token' => 'tok']);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Should not throw InvalidArgumentException for valid package code: '.$e->getMessage());
        } catch (\Throwable) {
            // Any other exception (e.g., from ensureRepository/runComposer) is acceptable
        }

        expect(true)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// uninstall flow
// ---------------------------------------------------------------------------

describe('uninstall flow', function (): void {
    beforeEach(function (): void {
        $this->composerJsonPath = base_path('composer.json');
        $this->lockPath = base_path('composer.lock');
        $this->installedPath = base_path('vendor/composer/installed.json');

        $this->originalComposerJson = file_get_contents($this->composerJsonPath);

        $this->lockExisted = file_exists($this->lockPath);
        if ($this->lockExisted) {
            $this->originalLock = file_get_contents($this->lockPath);
        }

        $this->installedExisted = file_exists($this->installedPath);
        if ($this->installedExisted) {
            $this->originalInstalled = file_get_contents($this->installedPath);
        }
    });

    afterEach(function (): void {
        file_put_contents($this->composerJsonPath, $this->originalComposerJson);

        if ($this->lockExisted) {
            file_put_contents($this->lockPath, $this->originalLock);
        } elseif (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }

        if ($this->installedExisted) {
            file_put_contents($this->installedPath, $this->originalInstalled);
        } elseif (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }
    });

    it('removes package from composer.json, composer.lock, and installed.json in sequence', function (): void {
        $data = json_decode($this->originalComposerJson, true);
        $data['require']['tipowerup/ti-ext-fake-uninstall'] = '^1.0';
        writeJsonFile($this->composerJsonPath, $data);

        writeJsonFile($this->lockPath, [
            'content-hash' => 'test',
            'packages' => [
                ['name' => 'tipowerup/ti-ext-fake-uninstall', 'version' => '1.0.0'],
                ['name' => 'other/keep-me', 'version' => '2.0.0'],
            ],
            'packages-dev' => [],
        ]);

        writeJsonFile($this->installedPath, [
            'packages' => [
                ['name' => 'tipowerup/ti-ext-fake-uninstall', 'version' => '1.0.0', 'install-path' => '../tipowerup/ti-ext-fake-uninstall'],
                ['name' => 'other/keep-me', 'version' => '2.0.0', 'install-path' => '../other/keep-me'],
            ],
            'dev' => true,
            'dev-package-names' => [],
        ]);

        composerInstaller()->callUninstallFilesOnly('tipowerup/ti-ext-fake-uninstall');

        $composerJson = json_decode(file_get_contents($this->composerJsonPath), true);
        expect($composerJson['require'])->not->toHaveKey('tipowerup/ti-ext-fake-uninstall');

        $lock = json_decode(file_get_contents($this->lockPath), true);
        $lockNames = array_column($lock['packages'], 'name');
        expect($lockNames)->not->toContain('tipowerup/ti-ext-fake-uninstall');
        expect($lockNames)->toContain('other/keep-me');

        $installed = json_decode(file_get_contents($this->installedPath), true);
        $installedNames = array_column($installed['packages'], 'name');
        expect($installedNames)->not->toContain('tipowerup/ti-ext-fake-uninstall');
        expect($installedNames)->toContain('other/keep-me');
    });

    it('handles missing vendor directory gracefully during uninstall file cleanup', function (): void {
        $vendorDir = base_path('vendor/tipowerup/ti-ext-nonexistent-dir');

        expect(file_exists($vendorDir))->toBeFalse();

        // Should not throw when vendor directory does not exist
        composerInstaller()->callUninstallFilesOnly('tipowerup/ti-ext-nonexistent-dir');

        expect(true)->toBeTrue();
    });
});
