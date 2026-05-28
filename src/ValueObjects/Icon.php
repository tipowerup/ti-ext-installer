<?php

declare(strict_types=1);

namespace Tipowerup\Installer\ValueObjects;

/**
 * Normalized package icon. Single source of truth for the partial.
 */
final readonly class Icon
{
    /**
     * Max bytes we'll inline as a base64 data URI. Anything larger is dropped
     * to a class-only icon to avoid bloating every page render.
     */
    private const int MAX_INLINE_BYTES = 65536;

    public function __construct(
        public ?string $url = null,
        public ?string $class = null,
        public ?string $backgroundColor = null,
        public ?string $color = null,
    ) {}

    /**
     * Build from any raw shape: array (url/class/image + backgroundColor/background_color),
     * string (Font Awesome class), or null. Resolves relative `image` paths to data URIs.
     */
    public static function fromAny(mixed $raw, ?string $basePath = null): self
    {
        if (is_string($raw) && $raw !== '') {
            return new self(class: 'fa fa-'.preg_replace('/^fa-/', '', $raw));
        }

        if (!is_array($raw)) {
            return new self;
        }

        $bg = $raw['background_color'] ?? $raw['backgroundColor'] ?? null;
        $color = $raw['color'] ?? null;
        $class = isset($raw['class']) && $raw['class'] !== '' ? $raw['class'] : null;
        $url = isset($raw['url']) && $raw['url'] !== '' ? $raw['url'] : null;

        if ($url === null && $basePath !== null && isset($raw['image']) && $raw['image'] !== '') {
            $imagePath = $basePath.'/'.$raw['image'];
            if (file_exists($imagePath) && filesize($imagePath) <= self::MAX_INLINE_BYTES) {
                $mime = mime_content_type($imagePath) ?: 'image/svg+xml';
                $url = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($imagePath));
            }
        }

        return new self(url: $url, class: $class, backgroundColor: $bg, color: $color);
    }

    public function isEmpty(): bool
    {
        return $this->url === null && $this->class === null;
    }

    /**
     * Serialize to the snake_case array shape consumed by the package-icon partial.
     *
     * @return array{url: ?string, class: ?string, background_color: ?string, color: ?string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'class' => $this->class,
            'background_color' => $this->backgroundColor,
            'color' => $this->color,
        ];
    }
}
