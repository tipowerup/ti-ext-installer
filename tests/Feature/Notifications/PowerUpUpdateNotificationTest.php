<?php

declare(strict_types=1);

use Tipowerup\Installer\Notifications\PowerUpUpdateNotification;

it('returns correct title', function (): void {
    $notification = new PowerUpUpdateNotification(1);

    expect($notification->getTitle())->toBeString();
});

it('returns singular message for one update', function (): void {
    $notification = new PowerUpUpdateNotification(1);

    $message = $notification->getMessage();

    expect($message)->toBeString()
        ->and($message)->not->toContain('%');
});

it('returns plural message for multiple updates', function (): void {
    $notification = new PowerUpUpdateNotification(3);

    $message = $notification->getMessage();

    expect($message)->toBeString()
        ->and($message)->toContain('3');
});

it('returns correct icon and color', function (): void {
    $notification = new PowerUpUpdateNotification;

    expect($notification->getIcon())->toBe('fa-download');
    expect($notification->getIconColor())->toBe('info');
});

it('returns correct alias', function (): void {
    $notification = new PowerUpUpdateNotification;

    expect($notification->getAlias())->toBe('tipowerup-update-available');
});

it('returns URL pointing to installer', function (): void {
    $notification = new PowerUpUpdateNotification;

    expect($notification->getUrl())->toContain('tipowerup/installer');
});
