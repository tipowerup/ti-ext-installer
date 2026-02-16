<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\BackupManager;
use Tipowerup\Installer\Services\CompatibilityChecker;
use Tipowerup\Installer\Services\InstallationPipeline;
use Tipowerup\Installer\Services\PowerUpApiClient;

it('can be resolved from the container', function (): void {
    $this->app->bind(PowerUpApiClient::class, fn () => new class extends PowerUpApiClient
    {
        public function __construct() {}
    });

    $pipeline = resolve(InstallationPipeline::class);

    expect($pipeline)->toBeInstanceOf(InstallationPipeline::class);
});

it('can be instantiated with dependencies', function (): void {
    $backupManager = new BackupManager;
    $compatibilityChecker = new CompatibilityChecker;

    $apiClient = new class extends PowerUpApiClient
    {
        public function __construct() {}
    };

    $pipeline = new InstallationPipeline(
        $backupManager,
        $compatibilityChecker,
        $apiClient
    );

    expect($pipeline)->toBeInstanceOf(InstallationPipeline::class);
});
