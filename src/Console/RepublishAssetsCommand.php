<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RepublishAssetsCommand extends Command
{
    protected $signature = 'tipowerup:republish-assets
                            {theme? : Specific theme slug (e.g. tipowerup-orange-tw); omit to republish all}
                            {--dry-run : Show what would be published without copying}';

    protected $description = 'Republish public/ and assets/ directories from storage-installed themes to public/vendor/.';

    public function handle(): int
    {
        $themesRoot = Storage::disk('local')->path('tipowerup/themes');

        if (!File::isDirectory($themesRoot)) {
            $this->warn('No storage themes directory found.');

            return self::SUCCESS;
        }

        $themeArg = $this->argument('theme');

        if ($themeArg !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) $themeArg) !== 1) {
            $this->error('Invalid theme slug. Use lowercase letters, digits, and hyphens only.');

            return self::FAILURE;
        }

        $themes = $themeArg !== null
            ? [$themesRoot.'/'.$themeArg]
            : File::directories($themesRoot);

        if ($themes === []) {
            $this->warn('No themes to publish.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $published = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($themes as $themePath) {
            if (!File::isDirectory($themePath)) {
                $this->error(sprintf('Theme not found: %s', basename((string) $themePath)));
                $failed++;

                continue;
            }

            $slug = basename((string) $themePath);
            $target = public_path('vendor/'.$slug);

            $sources = array_values(array_filter([
                $themePath.'/assets',
                $themePath.'/public',
            ], fn (string $path): bool => File::isDirectory($path)));

            if ($sources === []) {
                $this->line(sprintf('  <fg=gray>↷</> %s — no assets/ or public/ directory; skipped', $slug));
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('  <fg=yellow>·</> %s — would copy %s → %s', $slug, implode(' + ', array_map('basename', $sources)), $target));
                $published++;

                continue;
            }

            try {
                if (!File::isDirectory(dirname($target))) {
                    File::makeDirectory(dirname($target), 0755, true);
                }

                // Clean the target before copy so stale files from a previous
                // theme version don't linger when the new dist no longer ships them.
                if (File::isDirectory($target)) {
                    File::deleteDirectory($target);
                }

                foreach ($sources as $source) {
                    File::copyDirectory($source, $target);
                }

                $this->line(sprintf('  <fg=green>✓</> %s — published from %s', $slug, implode(' + ', array_map('basename', $sources))));
                $published++;
            } catch (Throwable $e) {
                $this->line(sprintf('  <fg=red>✗</> %s — %s', $slug, $e->getMessage()));
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf('Done. %d published, %d skipped, %d failed.', $published, $skipped, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
