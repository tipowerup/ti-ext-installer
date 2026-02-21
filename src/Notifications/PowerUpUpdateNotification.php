<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Notifications;

use Igniter\System\Contracts\StickyNotification;
use Igniter\User\Classes\Notification;
use Igniter\User\Models\User;
use Override;

class PowerUpUpdateNotification extends Notification implements StickyNotification
{
    public function __construct(protected int $count = 0) {}

    #[Override]
    public function getRecipients(): array
    {
        return User::whereIsEnabled()->whereIsSuperUser()->get()->all();
    }

    #[Override]
    public function getTitle(): string
    {
        return lang('tipowerup.installer::default.notify_update_found_title');
    }

    #[Override]
    public function getUrl(): string
    {
        return admin_url('tipowerup/installer');
    }

    #[Override]
    public function getMessage(): string
    {
        if ($this->count > 1) {
            return sprintf(
                lang('tipowerup.installer::default.notify_updates_found'),
                $this->count
            );
        }

        return lang('tipowerup.installer::default.notify_update_found');
    }

    #[Override]
    public function getIcon(): ?string
    {
        return 'fa-download';
    }

    #[Override]
    public function getIconColor(): ?string
    {
        return 'info';
    }

    #[Override]
    public function getAlias(): string
    {
        return 'tipowerup-update-available';
    }
}
