<?php

declare(strict_types=1);

namespace Tipowerup\Installer\ValueObjects;

/**
 * Unified package representation across installed scans, license API, marketplace API.
 * All fields beyond identity are optional; context determines which are populated.
 */
final readonly class Package
{
    public function __construct(
        public string $code,
        public string $name,
        public string $type,
        public Icon $icon,
        public string $description = '',
        public ?string $extensionCode = null,
        public ?string $themeCode = null,
        public ?string $version = null,
        public ?string $latestVersion = null,
        public ?string $installMethod = null,
        public ?bool $isActive = null,
        public ?string $expiresAt = null,
        public ?bool $hasUpdate = null,
        public ?bool $isOwned = null,
        public ?bool $purchased = null,
        public ?float $price = null,
        public ?string $priceFormatted = null,
        public ?string $url = null,
        public ?string $settingsUrl = null,
        public ?string $editUrl = null,
        public ?string $customizeUrl = null,
    ) {}

    /**
     * Build from raw extension meta on disk.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function fromExtensionMeta(string $code, array $meta, string $basePath, ?string $version, bool $isActive, ?string $settingsUrl): self
    {
        return new self(
            code: $code,
            name: $meta['name'] ?? $code,
            type: 'extension',
            icon: Icon::fromAny($meta['icon'] ?? null, $basePath),
            description: $meta['description'] ?? '',
            extensionCode: $meta['code'] ?? null,
            version: $version,
            isActive: $isActive,
            settingsUrl: $settingsUrl,
        );
    }

    /**
     * Build from a TI Theme on disk.
     */
    public static function fromTheme(string $composerName, string $themeCode, mixed $rawIcon, string $basePath, string $name, string $description, ?string $version, bool $isActive, string $editUrl, ?string $customizeUrl): self
    {
        return new self(
            code: $composerName,
            name: $name,
            type: 'theme',
            icon: Icon::fromAny($rawIcon, $basePath),
            description: $description,
            themeCode: $themeCode,
            version: $version,
            isActive: $isActive,
            editUrl: $editUrl,
            customizeUrl: $customizeUrl,
        );
    }

    /**
     * Build from a marketplace API row.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromMarketplaceApi(array $raw): self
    {
        return new self(
            code: (string) ($raw['code'] ?? ''),
            name: (string) ($raw['name'] ?? $raw['code'] ?? ''),
            type: (string) ($raw['type'] ?? 'extension'),
            icon: Icon::fromAny($raw['icon'] ?? null),
            description: (string) ($raw['description'] ?? ''),
            version: $raw['version'] ?? null,
            isOwned: isset($raw['is_owned']) ? (bool) $raw['is_owned'] : null,
            purchased: isset($raw['purchased']) ? (bool) $raw['purchased'] : null,
            price: isset($raw['price']) ? (float) $raw['price'] : null,
            priceFormatted: $raw['price_formatted'] ?? null,
            url: $raw['url'] ?? null,
        );
    }

    /**
     * Return a copy with overridden fields. Useful for layering remote/license data on top of a base package.
     */
    public function with(
        ?string $name = null,
        ?string $description = null,
        ?Icon $icon = null,
        ?string $version = null,
        ?string $latestVersion = null,
        ?string $installMethod = null,
        ?string $expiresAt = null,
        ?bool $hasUpdate = null,
        ?bool $isOwned = null,
        ?bool $purchased = null,
    ): self {
        return new self(
            code: $this->code,
            name: $name ?? $this->name,
            type: $this->type,
            icon: $icon ?? $this->icon,
            description: $description ?? $this->description,
            extensionCode: $this->extensionCode,
            themeCode: $this->themeCode,
            version: $version ?? $this->version,
            latestVersion: $latestVersion ?? $this->latestVersion,
            installMethod: $installMethod ?? $this->installMethod,
            isActive: $this->isActive,
            expiresAt: $expiresAt ?? $this->expiresAt,
            hasUpdate: $hasUpdate ?? $this->hasUpdate,
            isOwned: $isOwned ?? $this->isOwned,
            purchased: $purchased ?? $this->purchased,
            price: $this->price,
            priceFormatted: $this->priceFormatted,
            url: $this->url,
            settingsUrl: $this->settingsUrl,
            editUrl: $this->editUrl,
            customizeUrl: $this->customizeUrl,
        );
    }

    /**
     * Serialize to the snake_case array shape consumed by Livewire state and Blade views.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'icon' => $this->icon->toArray(),
            'description' => $this->description,
            'extension_code' => $this->extensionCode,
            'theme_code' => $this->themeCode,
            'version' => $this->version,
            'latest_version' => $this->latestVersion,
            'install_method' => $this->installMethod,
            'is_active' => $this->isActive,
            'expires_at' => $this->expiresAt,
            'has_update' => $this->hasUpdate,
            'is_owned' => $this->isOwned,
            'purchased' => $this->purchased,
            'price' => $this->price,
            'price_formatted' => $this->priceFormatted,
            'url' => $this->url,
            'settings_url' => $this->settingsUrl,
            'edit_url' => $this->editUrl,
            'customize_url' => $this->customizeUrl,
        ];
    }
}
