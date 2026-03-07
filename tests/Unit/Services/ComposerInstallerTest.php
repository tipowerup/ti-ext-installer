<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\ComposerInstaller;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function composerInstallerUnit(): object
{
    return new class extends ComposerInstaller
    {
        public function __construct()
        {
            // Skip parent constructor
        }

        public function callParseComposerProgress(string $line, int $lastPercent): int
        {
            $reflection = new ReflectionClass(ComposerInstaller::class);
            $method = $reflection->getMethod('parseComposerProgress');

            return $method->invoke($this, $line, $lastPercent);
        }
    };
}

// ---------------------------------------------------------------------------
// parseComposerProgress
// ---------------------------------------------------------------------------

describe('parseComposerProgress', function (): void {
    it('maps Loading composer repositories to 10', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Loading composer repositories with package information', 0);

        expect($result)->toBe(10);
    });

    it('maps Updating dependencies to 20', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Updating dependencies', 0);

        expect($result)->toBe(20);
    });

    it('maps Resolving dependencies to 30', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Resolving dependencies through SAT', 0);

        expect($result)->toBe(30);
    });

    it('maps Dependency resolution completed to 40', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Dependency resolution completed in 0.123 seconds', 0);

        expect($result)->toBe(40);
    });

    it('maps Package operations to 50', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Package operations: 1 install, 0 updates, 0 removals', 0);

        expect($result)->toBe(50);
    });

    it('maps Installing package line to 60', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('  - Installing tipowerup/darkmode (1.0.0): Extracting archive', 0);

        expect($result)->toBe(60);
    });

    it('maps Downloading to 65', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('  - Downloading tipowerup/darkmode (1.0.0)', 0);

        expect($result)->toBe(65);
    });

    it('maps Extracting to 70', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('  - Extracting tipowerup/darkmode (1.0.0)', 0);

        expect($result)->toBe(70);
    });

    it('maps Generating autoload files to 85', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Generating autoload files', 0);

        expect($result)->toBe(85);
    });

    it('maps Generated autoload files to 90', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Generated autoload files', 0);

        expect($result)->toBe(90);
    });

    it('maps No security vulnerability advisories to 95', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('No security vulnerability advisories found', 0);

        expect($result)->toBe(95);
    });

    it('returns lastPercent unchanged for unknown lines', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Some unrecognized composer output line', 42);

        expect($result)->toBe(42);
    });

    it('returns raw mapped value for known lines regardless of lastPercent', function (): void {
        $result = composerInstallerUnit()->callParseComposerProgress('Loading composer repositories', 70);

        expect($result)->toBe(10);
    });
});
