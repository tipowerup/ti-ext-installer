<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\PowerUpApiClient;
use Tipowerup\Installer\ValueObjects\Package;

class PackageDetail extends Component
{
    public string $packageCode = '';

    public array $packageData = [];

    public bool $isLoading = true;

    public ?string $errorMessage = null;

    public string $activeDetailTab = 'description';

    #[Locked]
    public bool $installed = false;

    public function mount(string $packageCode, array $initialData = []): void
    {
        $this->packageCode = $packageCode;
        $this->installed = $this->isInstalled($packageCode);

        if ($initialData !== [] && (!empty($initialData['local']) || ($initialData['type'] ?? '') === 'bundle')) {
            $this->applyPackageData($initialData);
            $this->isLoading = false;
        } else {
            $this->loadPackageDetails($initialData);
        }
    }

    public function loadPackageDetails(array $fallbackData = []): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $response = $apiClient->getPackageDetail($this->packageCode);
            $this->applyPackageData($response);
        } catch (ConnectionException) {
            if ($fallbackData !== []) {
                $this->applyPackageData($fallbackData);
            } else {
                $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
            }
        } catch (Throwable $e) {
            if ($fallbackData !== []) {
                $this->applyPackageData($fallbackData);
            } else {
                $this->errorMessage = $e->getMessage();
            }
        } finally {
            $this->isLoading = false;
        }
    }

    private function applyPackageData(array $data): void
    {
        $base = Package::fromMarketplaceApi($data)->toArray();

        $this->packageData = $base + [
            'code' => $data['code'] ?? $this->packageCode,
            'name' => $base['name'] !== '' ? $base['name'] : lang('tipowerup.installer::default.detail_unknown_name'),
            'version' => $base['version'] ?? '0.0.0',
            'author' => $data['author'] ?? 'Unknown',
            'description_html' => self::sanitizeMarkdown($data['description'] ?? ''),
            'cover_image' => $data['cover_image'] ?? null,
            'screenshots' => $data['screenshots'] ?? [],
            'changelog' => array_map(
                fn (array $entry): array => $entry + ['notes_html' => self::sanitizeMarkdown($entry['notes'] ?? '')],
                $data['changelog'] ?? [],
            ),
            'requirements' => $data['requirements'] ?? [],
            'updated_at' => $data['updated_at'] ?? null,
            'local' => $data['local'] ?? false,
            'installed' => $this->installed,
        ];
    }

    /**
     * Check whether a package has an existing license row. Defensively returns
     * false if the licenses table is unreachable (fresh install before our
     * migration has run, test DB without the schema, etc.).
     */
    private function isInstalled(string $packageCode): bool
    {
        try {
            return License::byPackage($packageCode)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Render Markdown and sanitize the result through a DOM walk: only allowlisted
     * tags survive, only allowlisted attributes per tag, and href/src must use a
     * safe URL scheme. The output is meant to be rendered with `{!! !!}`.
     */
    public static function sanitizeMarkdown(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        return self::sanitizeHtml((string) Str::markdown($markdown));
    }

    private static function sanitizeHtml(string $html): string
    {
        $allowedTags = [
            'p' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'a' => ['href', 'title'],
            'strong' => [], 'em' => [], 'b' => [], 'i' => [],
            'code' => [], 'pre' => [], 'blockquote' => [], 'br' => [], 'hr' => [],
            'img' => ['src', 'alt', 'title'],
            'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
            'dl' => [], 'dt' => [], 'dd' => [], 'sub' => [], 'sup' => [],
        ];

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::scrubNode($root, $allowedTags);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $allowedTags
     */
    private static function scrubNode(DOMNode $node, array $allowedTags): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                self::scrubNode($child, $allowedTags);

                continue;
            }

            $tag = strtolower($child->nodeName);

            if (!array_key_exists($tag, $allowedTags)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!in_array($attr->nodeName, $allowedTags[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);

                    continue;
                }

                if (in_array($attr->nodeName, ['href', 'src'], true) && !self::isSafeUrl($attr->nodeValue ?? '')) {
                    $child->removeAttribute($attr->nodeName);
                }
            }

            self::scrubNode($child, $allowedTags);
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    public function switchDetailTab(string $tab): void
    {
        if (in_array($tab, ['description', 'screenshots', 'changelog', 'compatibility'], true)) {
            $this->activeDetailTab = $tab;
        }
    }

    public function closeDetail(): void
    {
        $this->dispatch('package-detail-closed');
    }

    public function installPackage(): void
    {
        $packageName = $this->packageData['name'] ?? $this->packageCode;

        $this->dispatch('begin-install', packageCode: $this->packageCode, packageName: $packageName)->to(InstallerMain::class);
        $this->dispatch('package-detail-closed')->to(InstallerMain::class);
    }

    public function placeholder(): string
    {
        return '<div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">'.lang('tipowerup.installer::default.detail_loading').'</p>
        </div>';
    }

    public function render(): View
    {
        $type = $this->packageData['type'] ?? 'extension';
        $defaultColor = match ($type) {
            'theme' => '#F97316',
            'bundle' => '#8B5CF6',
            default => '#3B82F6',
        };

        $coverImage = $this->packageData['cover_image'] ?? null;
        $screenshots = array_values(array_filter(
            $this->packageData['screenshots'] ?? [],
            fn ($s): bool => $s !== $coverImage,
        ));
        $allImages = $coverImage !== null && $coverImage !== ''
            ? array_merge([$coverImage], $screenshots)
            : $screenshots;

        return view('tipowerup.installer::livewire.package-detail', [
            'icon' => $this->packageData['icon'] ?? null,
            'defaultColor' => $defaultColor,
            'allImages' => $allImages,
        ]);
    }
}
