<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\CompatibilityChecker;

it('checks PHP version requirement correctly', function (): void {
    $checker = new CompatibilityChecker;

    $results = $checker->check('test.package', [
        'php' => '>=8.1',
    ]);

    expect($results)->toBeArray();
    expect($results)->not->toBeEmpty();
    expect($results[0])->toHaveKey('type');
    expect($results[0]['type'])->toBe('php');
    expect($results[0])->toHaveKey('satisfied');
});

it('validates operator extraction using reflection', function (): void {
    $checker = new CompatibilityChecker;
    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('extractOperator');

    expect($method->invoke($checker, '>=8.1'))->toBe('>=');
    expect($method->invoke($checker, '>8.1'))->toBe('>');
    expect($method->invoke($checker, '<=8.1'))->toBe('<=');
    expect($method->invoke($checker, '<8.1'))->toBe('<');
    expect($method->invoke($checker, '=8.1'))->toBe('=');
    expect($method->invoke($checker, '==8.1'))->toBe('=');
    expect($method->invoke($checker, '8.1'))->toBe('>=');
});

it('validates version extraction using reflection', function (): void {
    $checker = new CompatibilityChecker;
    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('extractVersion');

    expect($method->invoke($checker, '>=8.1'))->toBe('8.1');
    expect($method->invoke($checker, '>8.1'))->toBe('8.1');
    expect($method->invoke($checker, '8.1'))->toBe('8.1');
    expect($method->invoke($checker, '  8.1  '))->toBe('8.1');
});

it('checks current PHP version satisfies requirement', function (): void {
    $checker = new CompatibilityChecker;

    // Current PHP version should be 8.3+
    $results = $checker->check('test.package', [
        'php' => '>=8.1',
    ]);

    expect($results[0]['satisfied'])->toBeTrue();
});

it('fails when PHP version requirement not met', function (): void {
    $checker = new CompatibilityChecker;

    $results = $checker->check('test.package', [
        'php' => '>=9.0', // Future version
    ]);

    expect($results[0]['satisfied'])->toBeFalse();
    expect($results[0]['requirement'])->toContain('PHP');
});

it('returns true when all requirements satisfied', function (): void {
    $checker = new CompatibilityChecker;

    $isSatisfied = $checker->isSatisfied('test.package', [
        'php' => '>=8.1',
    ]);

    expect($isSatisfied)->toBeTrue();
});

it('returns false when any requirement fails', function (): void {
    $checker = new CompatibilityChecker;

    $isSatisfied = $checker->isSatisfied('test.package', [
        'php' => '>=9.0',
    ]);

    expect($isSatisfied)->toBeFalse();
});

it('extracts failures from check results', function (): void {
    $checker = new CompatibilityChecker;

    $results = $checker->check('test.package', [
        'php' => '>=9.0',
    ]);

    $failures = $checker->getFailures($results);

    expect($failures)->toBeArray();
    expect($failures)->not->toBeEmpty();
    expect($failures[0])->toContain('PHP');
});

it('returns empty failures when all pass', function (): void {
    $checker = new CompatibilityChecker;

    $results = $checker->check('test.package', [
        'php' => '>=8.1',
    ]);

    $failures = $checker->getFailures($results);

    expect($failures)->toBeArray();
    expect($failures)->toBeEmpty();
});

it('checks multiple PHP version formats', function (): void {
    $checker = new CompatibilityChecker;

    // Test >=
    $results1 = $checker->check('test.package', ['php' => '>=8.1']);
    expect($results1[0]['satisfied'])->toBeTrue();

    // Test >
    $results2 = $checker->check('test.package', ['php' => '>8.0']);
    expect($results2[0]['satisfied'])->toBeTrue();

    // Test <=
    $results3 = $checker->check('test.package', ['php' => '<=9.0']);
    expect($results3[0]['satisfied'])->toBeTrue();
});

// Note: TI version and extension dependency checks require app() helper
// which is not available in minimal test environment.
