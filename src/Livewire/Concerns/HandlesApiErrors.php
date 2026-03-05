<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire\Concerns;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;
use Tipowerup\Installer\Exceptions\LicenseValidationException;

trait HandlesApiErrors
{
    public ?string $errorMessage = null;

    public bool $isKeyError = false;

    protected function handleApiError(Throwable $e): void
    {
        $this->isKeyError = false;

        match (true) {
            $e instanceof LicenseValidationException => $this->setKeyError(),
            $e instanceof ConnectionException => $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed'),
            $e instanceof RuntimeException && ($e->getCode() === 401 || $e->getCode() === 403) => $this->setKeyError(),
            default => $this->errorMessage = $e->getMessage(),
        };
    }

    protected function resetApiError(): void
    {
        $this->errorMessage = null;
        $this->isKeyError = false;
    }

    protected function showToast(string $level, string $message): void
    {
        $safeLevel = json_encode($level, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $safeMessage = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $this->js("$.ti.flashMessage({level: {$safeLevel}, html: {$safeMessage}})");
    }

    private function setKeyError(): void
    {
        $this->isKeyError = true;
        $this->errorMessage = lang('tipowerup.installer::default.error_powerup_key_invalid_alert');
    }
}
