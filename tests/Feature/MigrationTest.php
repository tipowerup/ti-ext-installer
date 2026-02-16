<?php

declare(strict_types=1);

use Tipowerup\Testbench\Concerns\TestsMigrations;

uses(TestsMigrations::class);

$migrationPath = dirname(__DIR__, 2).'/database/migrations';

$tables = [
    'tipowerup_licenses',
    'tipowerup_install_logs',
    'tipowerup_install_progress',
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
