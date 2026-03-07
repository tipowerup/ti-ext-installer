<?php

declare(strict_types=1);

use Tipowerup\Testbench\Concerns\TestsMigrations;

uses(TestsMigrations::class);

$migrationPath = dirname(__DIR__, 2).'/database/migrations';

$tables = [
    'tip_licenses',
    'tip_install_logs',
];

it('creates and rolls back all tables', function () use ($migrationPath, $tables): void {
    $this->assertMigrationCycle($migrationPath, $tables);
});

it('survives multiple install cycles', function () use ($migrationPath, $tables): void {
    $this->assertSurvivesInstallCycles($migrationPath, $tables, cycles: 3);
});

it('does not touch core TI tables', function () use ($migrationPath): void {
    $this->assertNoCoreTables($migrationPath);
});

it('has proper down methods', function () use ($migrationPath): void {
    $this->assertProperDownMethods($migrationPath);
});
