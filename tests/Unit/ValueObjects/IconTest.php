<?php

declare(strict_types=1);

use Tipowerup\Installer\ValueObjects\Icon;

it('returns an empty icon for null', function (): void {
    $icon = Icon::fromAny(null);

    expect($icon->isEmpty())->toBeTrue()
        ->and($icon->url)->toBeNull()
        ->and($icon->class)->toBeNull();
});

it('returns an empty icon for an empty string', function (): void {
    expect(Icon::fromAny('')->isEmpty())->toBeTrue();
});

it('returns an empty icon for an unsupported scalar', function (): void {
    expect(Icon::fromAny(42)->isEmpty())->toBeTrue();
});

it('builds a Font Awesome icon from a bare string', function (): void {
    $icon = Icon::fromAny('cog');

    expect($icon->class)->toBe('fa fa-cog')
        ->and($icon->url)->toBeNull();
});

it('strips a single leading fa- prefix without truncating internal letters', function (): void {
    expect(Icon::fromAny('fa-cog')->class)->toBe('fa fa-cog');
});

it('does not corrupt strings that happen to share letters with the fa- prefix', function (): void {
    // Guards against the previous ltrim('arrow', 'fa-') bug which returned 'rrow'.
    expect(Icon::fromAny('arrow')->class)->toBe('fa fa-arrow');
});

it('reads array shape with class + colors', function (): void {
    $icon = Icon::fromAny([
        'class' => 'fa fa-star',
        'background_color' => '#fff',
        'color' => '#000',
    ]);

    expect($icon->class)->toBe('fa fa-star')
        ->and($icon->backgroundColor)->toBe('#fff')
        ->and($icon->color)->toBe('#000');
});

it('accepts backgroundColor (camelCase) as an alias for background_color', function (): void {
    expect(Icon::fromAny(['backgroundColor' => '#abc'])->backgroundColor)->toBe('#abc');
});

it('prefers an explicit url over an image path', function (): void {
    $icon = Icon::fromAny([
        'url' => 'https://example.com/i.png',
        'image' => 'should-be-ignored.png',
    ], basePath: sys_get_temp_dir());

    expect($icon->url)->toBe('https://example.com/i.png');
});

it('inlines an image under the size cap as a base64 data URI', function (): void {
    $base = sys_get_temp_dir().'/tipowerup-icon-'.uniqid();
    mkdir($base);
    $imagePath = $base.'/icon.svg';
    file_put_contents($imagePath, '<svg/>');

    $icon = Icon::fromAny(['image' => 'icon.svg'], $base);

    expect($icon->url)->toStartWith('data:')
        ->and($icon->url)->toContain('base64,');

    unlink($imagePath);
    rmdir($base);
});

it('skips inlining when the image file is larger than the 64KB cap', function (): void {
    $base = sys_get_temp_dir().'/tipowerup-icon-'.uniqid();
    mkdir($base);
    $imagePath = $base.'/big.png';
    file_put_contents($imagePath, str_repeat('a', 70000));

    $icon = Icon::fromAny(['image' => 'big.png'], $base);

    expect($icon->url)->toBeNull();

    unlink($imagePath);
    rmdir($base);
});

it('skips inlining when the image file does not exist', function (): void {
    $base = sys_get_temp_dir().'/tipowerup-icon-missing-'.uniqid();
    mkdir($base);

    $icon = Icon::fromAny(['image' => 'nope.svg'], $base);

    expect($icon->url)->toBeNull();

    rmdir($base);
});

it('skips inlining when no basePath is provided', function (): void {
    $icon = Icon::fromAny(['image' => 'icon.svg']);

    expect($icon->url)->toBeNull();
});

it('isEmpty is false when only a class is set', function (): void {
    expect((new Icon(class: 'fa fa-cog'))->isEmpty())->toBeFalse();
});

it('isEmpty is false when only a url is set', function (): void {
    expect((new Icon(url: 'https://x'))->isEmpty())->toBeFalse();
});

it('toArray serializes to the snake_case shape the blade partial consumes', function (): void {
    $icon = new Icon(url: 'u', class: 'c', backgroundColor: '#bg', color: '#fg');

    expect($icon->toArray())->toBe([
        'url' => 'u',
        'class' => 'c',
        'background_color' => '#bg',
        'color' => '#fg',
    ]);
});
