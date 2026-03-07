<?php

declare(strict_types=1);

use Igniter\System\Classes\ExtensionManager;
use Tipowerup\Installer\Exceptions\CompatibilityException;
use Tipowerup\Installer\Services\CompatibilityChecker;

beforeEach(function (): void {
    $this->checker = new CompatibilityChecker;
});

// ─── check() ──────────────────────────────────────────────────────────────────

it('returns empty results when no requirements are given', function (): void {
    $results = $this->checker->check('test.package', []);

    expect($results)->toBeArray()->toBeEmpty();
});

it('returns a php result when php requirement is set', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=8.1']);

    expect($results)->toHaveCount(1);
    expect($results[0])->toHaveKeys(['type', 'satisfied', 'requirement', 'current']);
    expect($results[0]['type'])->toBe('php');
});

it('reports php requirement satisfied when current php meets constraint', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=8.1']);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('reports php requirement not satisfied for impossibly high version', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=99.0']);

    expect($results[0]['satisfied'])->toBeFalse();
    expect($results[0]['requirement'])->toContain('PHP');
});

it('reports ti_version satisfied when requirement is low enough', function (): void {
    $results = $this->checker->check('test.package', ['ti_version' => '>=1.0']);

    expect($results)->toHaveCount(1);
    expect($results[0]['type'])->toBe('ti_version');
    expect($results[0]['satisfied'])->toBeTrue();
});

it('reports ti_version not satisfied for impossibly high version', function (): void {
    $results = $this->checker->check('test.package', ['ti_version' => '>=99.0']);

    expect($results[0]['type'])->toBe('ti_version');
    expect($results[0]['satisfied'])->toBeFalse();
    expect($results[0]['requirement'])->toContain('TastyIgniter');
});

it('returns results for both php and ti_version requirements together', function (): void {
    $results = $this->checker->check('test.package', [
        'php' => '>=8.1',
        'ti_version' => '>=1.0',
    ]);

    expect($results)->toHaveCount(2);

    $types = array_column($results, 'type');
    expect($types)->toContain('php')->toContain('ti_version');
});

// ─── isSatisfied() ────────────────────────────────────────────────────────────

it('returns true when all requirements are met', function (): void {
    expect($this->checker->isSatisfied('test.package', ['php' => '>=8.1']))->toBeTrue();
});

it('returns false when any requirement fails', function (): void {
    expect($this->checker->isSatisfied('test.package', ['php' => '>=99.0']))->toBeFalse();
});

it('returns true with no requirements', function (): void {
    expect($this->checker->isSatisfied('test.package', []))->toBeTrue();
});

// ─── getFailures() ────────────────────────────────────────────────────────────

it('extracts failed requirements from check results', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=99.0']);

    $failures = $this->checker->getFailures($results);

    expect($failures)->toBeArray()->not->toBeEmpty();
    expect($failures[0])->toContain('PHP');
});

it('returns empty failures when all requirements pass', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=8.1']);

    expect($this->checker->getFailures($results))->toBeArray()->toBeEmpty();
});

it('returns empty failures for empty check results', function (): void {
    expect($this->checker->getFailures([]))->toBeArray()->toBeEmpty();
});

// ─── assertSatisfied() ────────────────────────────────────────────────────────

it('does not throw when all requirements are satisfied', function (): void {
    expect(fn () => $this->checker->assertSatisfied('test.package', ['php' => '>=8.1']))
        ->not->toThrow(CompatibilityException::class);
});

it('throws CompatibilityException when a requirement is not satisfied', function (): void {
    expect(fn () => $this->checker->assertSatisfied('test.package', ['php' => '>=99.0']))
        ->toThrow(CompatibilityException::class);
});

it('includes the package code and failed requirement in the exception message', function (): void {
    try {
        $this->checker->assertSatisfied('tipowerup.darkmode', ['php' => '>=99.0']);
        $this->fail('Expected CompatibilityException was not thrown.');
    } catch (CompatibilityException $e) {
        expect($e->getMessage())->toContain('tipowerup.darkmode');
        expect($e->getMessage())->toContain('PHP');
    }
});

// ─── operator / version extraction (via check results) ────────────────────────

it('satisfies php requirement using greater-than-or-equal operator', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>=8.1']);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('satisfies php requirement using strict greater-than operator', function (): void {
    $results = $this->checker->check('test.package', ['php' => '>8.0']);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('satisfies php requirement using less-than-or-equal operator', function (): void {
    $results = $this->checker->check('test.package', ['php' => '<=99.0']);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('satisfies php requirement using strict less-than operator', function (): void {
    $results = $this->checker->check('test.package', ['php' => '<99.0']);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('satisfies php requirement using single equals operator', function (): void {
    $currentPhp = PHP_VERSION;
    $results = $this->checker->check('test.package', ['php' => "={$currentPhp}"]);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('satisfies php requirement using double equals operator normalised to single', function (): void {
    $currentPhp = PHP_VERSION;
    $results = $this->checker->check('test.package', ['php' => "=={$currentPhp}"]);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('defaults to greater-than-or-equal when no operator is provided in constraint', function (): void {
    $results = $this->checker->check('test.package', ['php' => '8.1']);

    expect($results[0]['satisfied'])->toBeTrue();
});

// ─── extension dependency ─────────────────────────────────────────────────────

it('reports extension not satisfied when extension is not installed', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')
            ->with('igniter.cart')
            ->andReturn(false);
    });

    $results = $this->checker->check('test.package', [
        'extensions' => ['igniter.cart' => '>=1.0'],
    ]);

    expect($results)->toHaveCount(1);
    expect($results[0]['type'])->toBe('extension');
    expect($results[0]['satisfied'])->toBeFalse();
    expect($results[0]['current'])->toBe('not installed');
    expect($results[0]['extension_code'])->toBe('igniter.cart');
});

it('reports extension satisfied when extension is installed with wildcard version', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')
            ->with('igniter.cart')
            ->andReturn(true);

        $mock->shouldReceive('findExtension')
            ->with('igniter.cart')
            ->andReturn(null);
    });

    $results = $this->checker->check('test.package', [
        'extensions' => ['igniter.cart' => '*'],
    ]);

    expect($results[0]['type'])->toBe('extension');
    expect($results[0]['satisfied'])->toBeTrue();
});

it('checks multiple extension dependencies in one call', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->with('igniter.cart')->andReturn(false);
        $mock->shouldReceive('hasExtension')->with('igniter.local')->andReturn(false);
    });

    $results = $this->checker->check('test.package', [
        'extensions' => [
            'igniter.cart' => '>=1.0',
            'igniter.local' => '>=1.0',
        ],
    ]);

    expect($results)->toHaveCount(2);

    foreach ($results as $result) {
        expect($result['type'])->toBe('extension');
        expect($result['satisfied'])->toBeFalse();
    }
});
