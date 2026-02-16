<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\System\Classes\ExtensionManager;
use Throwable;
use Tipowerup\Installer\Exceptions\CompatibilityException;

class CompatibilityChecker
{
    /**
     * Run all compatibility checks for a package.
     */
    public function check(string $packageCode, array $packageRequirements): array
    {
        $results = [];

        // Check PHP version requirement
        if (isset($packageRequirements['php'])) {
            $results[] = $this->checkPhpVersion($packageRequirements['php']);
        }

        // Check TastyIgniter version requirement
        if (isset($packageRequirements['ti_version'])) {
            $results[] = $this->checkTiVersion($packageRequirements['ti_version']);
        }

        // Check extension dependencies
        if (isset($packageRequirements['extensions']) && is_array($packageRequirements['extensions'])) {
            foreach ($packageRequirements['extensions'] as $extensionCode => $versionConstraint) {
                $results[] = $this->checkExtensionDependency($extensionCode, $versionConstraint);
            }
        }

        return $results;
    }

    /**
     * Check if all requirements are satisfied.
     */
    public function isSatisfied(string $packageCode, array $packageRequirements): bool
    {
        $results = $this->check($packageCode, $packageRequirements);

        foreach ($results as $result) {
            if (!($result['satisfied'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check PHP version requirement.
     */
    private function checkPhpVersion(string $requiredVersion): array
    {
        $currentVersion = PHP_VERSION;
        $operator = $this->extractOperator($requiredVersion);
        $version = $this->extractVersion($requiredVersion);

        $satisfied = version_compare($currentVersion, $version, $operator);

        return [
            'requirement' => 'PHP '.$requiredVersion,
            'satisfied' => $satisfied,
            'current' => $currentVersion,
            'type' => 'php',
        ];
    }

    /**
     * Check TastyIgniter version requirement.
     */
    private function checkTiVersion(string $requiredVersion): array
    {
        $currentVersion = app()->version();
        $operator = $this->extractOperator($requiredVersion);
        $version = $this->extractVersion($requiredVersion);

        $satisfied = version_compare($currentVersion, $version, $operator);

        return [
            'requirement' => 'TastyIgniter '.$requiredVersion,
            'satisfied' => $satisfied,
            'current' => $currentVersion,
            'type' => 'ti_version',
        ];
    }

    /**
     * Check extension dependency.
     */
    private function checkExtensionDependency(string $extensionCode, string $versionConstraint): array
    {
        $extensionManager = resolve(ExtensionManager::class);

        // Check if extension is installed
        $hasExtension = $extensionManager->hasExtension($extensionCode);

        if (!$hasExtension) {
            return [
                'requirement' => sprintf('%s %s', $extensionCode, $versionConstraint),
                'satisfied' => false,
                'current' => 'not installed',
                'type' => 'extension',
                'extension_code' => $extensionCode,
            ];
        }

        // If wildcard version, any version is acceptable
        if ($versionConstraint === '*' || $versionConstraint === '') {
            return [
                'requirement' => sprintf('%s %s', $extensionCode, $versionConstraint),
                'satisfied' => true,
                'current' => $this->getExtensionVersion($extensionCode),
                'type' => 'extension',
                'extension_code' => $extensionCode,
            ];
        }

        // Check version constraint
        $currentVersion = $this->getExtensionVersion($extensionCode);
        $operator = $this->extractOperator($versionConstraint);
        $requiredVersionNum = $this->extractVersion($versionConstraint);

        $satisfied = version_compare($currentVersion, $requiredVersionNum, $operator);

        return [
            'requirement' => sprintf('%s %s', $extensionCode, $versionConstraint),
            'satisfied' => $satisfied,
            'current' => $currentVersion,
            'type' => 'extension',
            'extension_code' => $extensionCode,
        ];
    }

    /**
     * Get the version of an installed extension.
     */
    private function getExtensionVersion(string $extensionCode): string
    {
        try {
            $extensionManager = resolve(ExtensionManager::class);
            $extension = $extensionManager->findExtension($extensionCode);

            if (!$extension) {
                return '0.0.0';
            }

            // Try to get version from extension meta
            if (method_exists($extension, 'getVersion')) {
                return $extension->getVersion() ?: '0.0.0';
            }

            // Fallback: check composer.json
            $vendor = explode('.', $extensionCode)[0] ?? 'igniter';
            $name = explode('.', $extensionCode)[1] ?? $extensionCode;
            $composerPath = base_path(sprintf('extensions/%s/%s/composer.json', $vendor, $name));

            if (!file_exists($composerPath)) {
                $composerPath = base_path(sprintf('vendor/%s/%s/composer.json', $vendor, $name));
            }

            if (file_exists($composerPath)) {
                $composerData = json_decode(file_get_contents($composerPath), true);

                return $composerData['version'] ?? '0.0.0';
            }

            return '0.0.0';
        } catch (Throwable) {
            return '0.0.0';
        }
    }

    /**
     * Extract comparison operator from version constraint.
     */
    private function extractOperator(string $constraint): string
    {
        // Common operators: >=, <=, >, <, =, ==
        if (preg_match('/^(>=|<=|>|<|==?)/', $constraint, $matches)) {
            $operator = $matches[1];

            // Normalize == to =
            return $operator === '==' ? '=' : $operator;
        }

        // Default to >=
        return '>=';
    }

    /**
     * Extract version number from constraint.
     */
    private function extractVersion(string $constraint): string
    {
        // Remove operator prefix
        $version = preg_replace('/^(>=|<=|>|<|==?)/', '', $constraint);

        // Trim whitespace
        return trim((string) $version);
    }

    /**
     * Get failures from check results.
     */
    public function getFailures(array $checkResults): array
    {
        $failures = [];

        foreach ($checkResults as $result) {
            if (!($result['satisfied'] ?? false)) {
                $failures[] = $result['requirement'];
            }
        }

        return $failures;
    }

    /**
     * Throw exception if requirements are not satisfied.
     */
    public function assertSatisfied(string $packageCode, array $packageRequirements): void
    {
        $results = $this->check($packageCode, $packageRequirements);
        $failures = $this->getFailures($results);

        if ($failures !== []) {
            throw CompatibilityException::unsatisfied($packageCode, $failures);
        }
    }
}
