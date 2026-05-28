<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Tipowerup\Installer\Models\License;

/**
 * Resolves marketplace package dependencies for the direct installer.
 *
 * The marketplace backend pre-flattens each package's dependency tree at publish
 * time and returns it via the install API. This service answers two questions:
 *
 *  - Which of the deps does the user not yet have (or have at too old a version)?
 *  - Is a given package safe to uninstall, or is something else still using it?
 *
 * It owns no install logic itself; it just inspects the local License table.
 */
class MarketplaceDependencyResolver
{
    /**
     * Given the marketplace dep list returned by the API, return only the entries
     * that are NOT already satisfied by an active local license.
     *
     * Each entry is expected to be: ['name' => string, 'code' => string, 'min_version' => ?string].
     *
     * @param  array<int, array{name?: string, code?: string, min_version?: string}>  $deps
     * @return array<int, array{name: string, code: string, min_version: ?string}>
     */
    public function missing(array $deps): array
    {
        $missing = [];

        foreach ($deps as $dep) {
            $code = $dep['code'] ?? null;
            if ($code === null || $code === '') {
                continue;
            }

            $minVersion = $dep['min_version'] ?? null;
            $license = License::byPackage($code)->active()->first();

            if (!$license || !$this->satisfies($license->version, $minVersion)) {
                $missing[] = [
                    'name' => $dep['name'] ?? $code,
                    'code' => $code,
                    'min_version' => $minVersion,
                ];
            }
        }

        return $missing;
    }

    /**
     * Return the package codes of every active license that lists $packageCode
     * in its requires_marketplace_packages field. Used to block uninstall when
     * other installed packages still depend on this one.
     *
     * @return array<int, string>
     */
    public function dependents(string $packageCode): array
    {
        return License::query()
            ->active()
            ->where('package_code', '!=', $packageCode)
            ->get()
            ->filter(function (License $license) use ($packageCode): bool {
                $deps = $license->requires_marketplace_packages ?? [];
                foreach ($deps as $dep) {
                    if (($dep['code'] ?? null) === $packageCode) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('package_code')
            ->values()
            ->all();
    }

    /**
     * True when $installedVersion is >= $minVersion. A null minVersion is always satisfied.
     * Falls back to string comparison if version_compare can't parse the inputs.
     */
    private function satisfies(?string $installedVersion, ?string $minVersion): bool
    {
        if ($minVersion === null || $minVersion === '') {
            return true;
        }

        if ($installedVersion === null || $installedVersion === '') {
            return false;
        }

        return version_compare($installedVersion, $minVersion, '>=');
    }
}
